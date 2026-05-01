<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingCarrierDriverInterface;
use App\DTO\Shipping\ShipmentCreateResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Models\Order;
use App\Models\OrderShippingDetail;
use App\Models\ShippingEvent;
use App\Models\VendorShippingCompany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShippingCarrierManager
{
    public function getCarrierDefinitions(): array
    {
        return config('shipping_carriers.carriers', []);
    }

    public function getCarrierDefinition(string $carrierKey): ?array
    {
        return $this->getCarrierDefinitions()[Str::lower($carrierKey)] ?? null;
    }

    public function getDriver(string $carrierKey): ShippingCarrierDriverInterface
    {
        $carrier = $this->getCarrierDefinition($carrierKey);
        abort_unless($carrier && class_exists($carrier['driver']), 404, 'Carrier driver not configured.');

        return app($carrier['driver']);
    }

    public function getVendorCarrier(int $vendorId, string $carrierKey): ?VendorShippingCompany
    {
        return VendorShippingCompany::query()
            ->where('vendor_id', $vendorId)
            ->where(function ($query) use ($carrierKey) {
                $query->where('carrier_key', Str::lower($carrierKey))
                    ->orWhereRaw('LOWER(name) = ?', [Str::lower($carrierKey)]);
            })
            ->first();
    }

    public function getVendorCarrierCredentials(?VendorShippingCompany $carrierRecord): array
    {
        if (!$carrierRecord) {
            return [];
        }

        $credentials = [];

        if (!empty($carrierRecord->credentials)) {
            try {
                $decrypted = Crypt::decryptString($carrierRecord->credentials);
                $decoded = json_decode($decrypted, true);
                if (is_array($decoded)) {
                    $credentials = $decoded;
                }
            } catch (\Throwable $exception) {
            }
        }

        if (Str::lower((string)($carrierRecord->carrier_key ?? $carrierRecord->name)) === 'noest') {
            $credentials['noest_guid'] = $credentials['noest_guid'] ?? $carrierRecord->noest_guid;
            $credentials['api_token'] = $credentials['api_token'] ?? $carrierRecord->api_token;
        }

        return array_filter($credentials, fn ($value) => !is_null($value) && $value !== '');
    }

    public function listVendorCarriers(int $vendorId, bool $maskSecrets = true): array
    {
        $records = VendorShippingCompany::query()
            ->where('vendor_id', $vendorId)
            ->get()
            ->keyBy(function (VendorShippingCompany $item) {
                return Str::lower((string)($item->carrier_key ?: $item->name));
            });

        $result = [];

        foreach ($this->getCarrierDefinitions() as $carrierKey => $carrierDefinition) {
            $record = $records->get($carrierKey);
            $credentials = $this->getVendorCarrierCredentials($record);

            $result[] = [
                'carrier_key' => $carrierKey,
                'display_name' => $carrierDefinition['display_name'],
                'supported' => (bool)($carrierDefinition['supported'] ?? (($carrierDefinition['integration_status'] ?? 'supported') === 'supported')),
                'integration_status' => $carrierDefinition['integration_status'] ?? 'supported',
                'unsupported_reason' => $carrierDefinition['unsupported_reason'] ?? null,
                'base_url' => $carrierDefinition['base_url'] ?? null,
                'status' => (int)($record->status ?? 0),
                'is_connected' => (int)($record->is_connected ?? (!empty($record?->connected_since) ? 1 : 0)),
                'connected_since' => optional($record?->connected_since)->toDateTimeString(),
                'last_tested_at' => optional($record?->last_tested_at)->toDateTimeString(),
                'last_error' => $record?->last_error,
                'supports_home_delivery' => (bool)($carrierDefinition['supports_home_delivery'] ?? false),
                'supports_desk_delivery' => (bool)($carrierDefinition['supports_desk_delivery'] ?? false),
                'supports_pickup_point' => (bool)($carrierDefinition['supports_pickup_point'] ?? false),
                'supports_rate_lookup' => (bool)($carrierDefinition['supports_rate_lookup'] ?? false),
                'supports_tracking' => (bool)($carrierDefinition['supports_tracking'] ?? false),
                'supports_label' => (bool)($carrierDefinition['supports_label'] ?? false),
                'supports_webhooks' => (bool)($carrierDefinition['supports_webhooks'] ?? false),
                'supports_create_shipment' => (bool)($carrierDefinition['supports_create_shipment'] ?? false),
                'credential_fields' => $this->buildCredentialFields($carrierDefinition, $credentials, $maskSecrets),
            ];
        }

        return $result;
    }

    public function saveVendorCarrierSettings(int $vendorId, string $carrierKey, array $input): VendorShippingCompany
    {
        $carrierDefinition = $this->getCarrierDefinition($carrierKey);
        abort_unless($carrierDefinition, 404, 'Carrier not found.');

        $carrierKey = Str::lower($carrierKey);
        $carrierRecord = $this->getVendorCarrier($vendorId, $carrierKey) ?? new VendorShippingCompany();
        $existingCredentials = $this->getVendorCarrierCredentials($carrierRecord);
        $incomingCredentials = $this->extractCredentialValues($carrierDefinition, $input, $existingCredentials, true);
        $mergedCredentials = array_merge($existingCredentials, array_filter($incomingCredentials, fn ($value) => $value !== ''));
        $carrierWebsite = $carrierDefinition['website'] ?? $carrierDefinition['base_url'] ?? '#';

        $carrierRecord->vendor_id = $vendorId;
        $carrierRecord->name = $carrierKey;
        $carrierRecord->website = $carrierWebsite;
        $carrierRecord->carrier_key = $carrierKey;
        $carrierRecord->display_name = $carrierDefinition['display_name'];
        $carrierRecord->supports_home_delivery = (int)($carrierDefinition['supports_home_delivery'] ?? false);
        $carrierRecord->supports_desk_delivery = (int)($carrierDefinition['supports_desk_delivery'] ?? false);
        $carrierRecord->status = isset($input['status']) ? (int)((bool)$input['status']) : (int)($carrierRecord->status ?? 0);
        $carrierRecord->credentials = !empty($mergedCredentials)
            ? Crypt::encryptString(json_encode($mergedCredentials, JSON_UNESCAPED_UNICODE))
            : $carrierRecord->credentials;

        if ($carrierKey === 'noest') {
            $carrierRecord->name = 'noest';
            $carrierRecord->noest_guid = $mergedCredentials['noest_guid'] ?? $carrierRecord->noest_guid;
            $carrierRecord->api_token = $mergedCredentials['api_token'] ?? $carrierRecord->api_token;
        }

        $carrierRecord->save();

        return $carrierRecord->fresh();
    }

    public function testVendorCarrierConnection(int $vendorId, string $carrierKey, array $input = []): array
    {
        $carrierDefinition = $this->getCarrierDefinition($carrierKey);
        abort_unless($carrierDefinition, 404, 'Carrier not found.');

        $carrierRecord = $this->saveVendorCarrierSettings($vendorId, $carrierKey, $input);
        $driver = $this->getDriver($carrierKey);
        $credentials = $this->getVendorCarrierCredentials($carrierRecord);
        $result = $driver->validateCredentials($credentials);

        if (!isset($result['message']) || blank($result['message'])) {
            $result['message'] = ($result['success'] ?? false)
                ? translate('connection_successful')
                : translate('connection_failed');
        }

        $carrierRecord->last_tested_at = now();
        $carrierRecord->last_error = ($result['success'] ?? false) ? null : ($result['message'] ?? 'connection_failed');
        $carrierRecord->is_connected = ($result['success'] ?? false) ? 1 : 0;

        if (($result['success'] ?? false) && empty($carrierRecord->connected_since)) {
            $carrierRecord->connected_since = now();
        }

        $carrierRecord->save();

        return $result;
    }

    public function toggleVendorCarrier(int $vendorId, string $carrierKey, bool $status): VendorShippingCompany
    {
        $carrierDefinition = $this->getCarrierDefinition($carrierKey);
        abort_unless($carrierDefinition, 404, 'Carrier not found.');

        $carrierRecord = $this->getVendorCarrier($vendorId, $carrierKey);
        abort_unless($carrierRecord, 404, 'Carrier settings not found.');

        $carrierRecord->status = $status ? 1 : 0;
        $carrierRecord->save();

        return $carrierRecord->fresh();
    }

    public function getEnabledCarriersForVendor(int $vendorId): array
    {
        return VendorShippingCompany::query()
            ->where('vendor_id', $vendorId)
            ->where('status', 1)
            ->get()
            ->filter(function (VendorShippingCompany $item) {
                $carrierDefinition = $this->getCarrierDefinition((string)($item->carrier_key ?: $item->name));

                return ($carrierDefinition['integration_status'] ?? 'supported') === 'supported';
            })
            ->values()
            ->all();
    }

    public function getAvailableCarriersForSeller(int $sellerId, array $payload): array
    {
        $vendorId = ($payload['seller_is'] ?? 'seller') === 'admin' ? 0 : $sellerId;
        $availableCarriers = [];

        foreach ($this->getEnabledCarriersForVendor($vendorId) as $carrierRecord) {
            $carrierKey = Str::lower((string)($carrierRecord->carrier_key ?: $carrierRecord->name));
            $driver = $this->getDriver($carrierKey);
            $credentials = $this->getVendorCarrierCredentials($carrierRecord);
            $rateResult = $driver->getRates($credentials, $payload);

            if (!($rateResult['supported'] ?? false) || !($rateResult['success'] ?? false)) {
                continue;
            }

            $availableCarriers[] = [
                'carrier_key' => $carrierKey,
                'carrier_name' => $carrierRecord->display_name ?: $carrierRecord->name,
                'seller_id' => $sellerId,
                'delivery_methods' => $rateResult['data'] ?? [],
            ];
        }

        return $availableCarriers;
    }

    public function createShipmentForOrder(Order $order): ShipmentCreateResult
    {
        $shippingMeta = $this->extractOrderShippingMeta($order);
        $carrierKey = Str::lower((string)($shippingMeta['carrier_key'] ?? ''));

        if ($carrierKey === '') {
            return new ShipmentCreateResult(
                supported: false,
                success: false,
                message: 'no_carrier_selected',
                shippingStatus: 'skipped',
                errorMessage: 'no_carrier_selected',
            );
        }

        $vendorId = $order->seller_is === 'admin' ? 0 : (int)$order->seller_id;
        $carrierRecord = $this->getVendorCarrier($vendorId, $carrierKey);

        if (!$carrierRecord || (int)$carrierRecord->status !== 1) {
            return new ShipmentCreateResult(
                supported: true,
                success: false,
                message: 'carrier_not_enabled_for_vendor',
                carrierKey: $carrierKey,
                carrierName: $this->getCarrierDefinition($carrierKey)['display_name'] ?? strtoupper($carrierKey),
                deliveryType: $shippingMeta['delivery_type'] ?? null,
                shippingStatus: 'failed',
                errorMessage: 'carrier_not_enabled_for_vendor',
            );
        }

        $driver = $this->getDriver($carrierKey);
        $credentials = $this->getVendorCarrierCredentials($carrierRecord);

        return $driver->createShipment($order, $credentials, $shippingMeta);
    }

    public function syncOpenShipments(): int
    {
        $statusMap = config('shipping_carriers.supported_order_status_map', []);
        $updatedCount = 0;

        OrderShippingDetail::query()
            ->whereNotIn('shipping_status', ['delivered', 'returned', 'failed', 'canceled'])
            ->orWhereNull('shipping_status')
            ->chunkById(100, function ($shippingDetails) use ($statusMap, &$updatedCount) {
                foreach ($shippingDetails as $shippingDetail) {
                    $carrierKey = Str::lower((string)($shippingDetail->carrier_key ?: $shippingDetail->carrier_name));
                    if ($carrierKey === '') {
                        continue;
                    }

                    $order = Order::find($shippingDetail->order_id);
                    if (!$order || empty($shippingDetail->tracking_number)) {
                        continue;
                    }

                    $vendorId = $order->seller_is === 'admin' ? 0 : (int)$order->seller_id;
                    $carrierRecord = $this->getVendorCarrier($vendorId, $carrierKey);
                    $credentials = $this->getVendorCarrierCredentials($carrierRecord);
                    $trackingResult = $this->getDriver($carrierKey)->trackShipment((string)$shippingDetail->tracking_number, $credentials);

                    if (!($trackingResult->supported ?? false) || !($trackingResult->success ?? false)) {
                        continue;
                    }

                    $shippingDetail->shipping_status = $trackingResult->shippingStatus;
                    $shippingDetail->status = $trackingResult->shippingStatus;
                    $shippingDetail->shipment_response = $trackingResult->response;
                    $shippingDetail->response_payload = $trackingResult->response;
                    $shippingDetail->last_synced_at = now();
                    $shippingDetail->save();

                    foreach ($trackingResult->events as $event) {
                        ShippingEvent::query()->create([
                            'order_id' => $order->id,
                            'order_shipping_detail_id' => $shippingDetail->id,
                            'carrier_key' => $carrierKey,
                            'tracking_number' => $shippingDetail->tracking_number,
                            'shipping_status' => $event['shipping_status'] ?? $trackingResult->shippingStatus,
                            'event_label' => $event['label'] ?? null,
                            'event_description' => $event['description'] ?? null,
                            'event_payload' => $event,
                            'event_at' => $event['event_at'] ?? now(),
                        ]);
                    }

                    $mappedStatus = $statusMap[$trackingResult->shippingStatus] ?? null;
                    if ($mappedStatus) {
                        $order->order_status = $mappedStatus;
                        $order->save();
                    }

                    $updatedCount++;
                }
            });

        return $updatedCount;
    }

    public function extractOrderShippingMeta(Order $order): array
    {
        $shippingAddress = (array)($order->shipping_address_data ?? []);

        $carrierKey = Str::lower((string)($shippingAddress['carrier_key'] ?? ''));
        if (
            $carrierKey === ''
            && (
                !empty($shippingAddress['is_noest'])
                || !empty($shippingAddress['noest_delivery_method'])
                || !empty($shippingAddress['noest_wilaya_code'])
            )
        ) {
            $carrierKey = 'noest';
        }

        return [
            'carrier_key' => $carrierKey,
            'carrier_name' => $shippingAddress['carrier_name'] ?? $this->getCarrierDefinition($carrierKey)['display_name'] ?? null,
            'delivery_type' => $shippingAddress['delivery_type'] ?? $shippingAddress['noest_delivery_method'] ?? null,
            'remote_delivery_type' => $shippingAddress['remote_delivery_type'] ?? null,
            'desk_code' => $shippingAddress['desk_code'] ?? $shippingAddress['noest_station_code'] ?? null,
            'desk_name' => $shippingAddress['desk_name'] ?? $shippingAddress['noest_station_name'] ?? null,
            'pickup_point_id' => $shippingAddress['pickup_point_id'] ?? null,
            'delivery_option_payload' => $shippingAddress['delivery_option_payload'] ?? null,
            'wilaya_id' => $shippingAddress['wilaya_id'] ?? $shippingAddress['noest_wilaya_id'] ?? null,
            'wilaya_code' => $shippingAddress['wilaya_code'] ?? $shippingAddress['noest_wilaya_code'] ?? null,
            'wilaya_name' => $shippingAddress['wilaya_name'] ?? $shippingAddress['noest_wilaya_name'] ?? null,
            'commune_id' => $shippingAddress['commune_id'] ?? null,
            'baladiya_name' => $shippingAddress['baladiya_name'] ?? $shippingAddress['noest_baladiya_name'] ?? null,
            'shipping_cost' => $shippingAddress['shipping_cost'] ?? $order->shipping_cost ?? null,
        ];
    }

    private function buildCredentialFields(array $carrierDefinition, array $credentials, bool $maskSecrets): array
    {
        return collect($carrierDefinition['credential_fields'] ?? [])
            ->map(function (array $field) use ($credentials, $maskSecrets) {
                $value = $credentials[$field['key']] ?? null;
                $isSecret = ($field['type'] ?? 'text') === 'password';

                return [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'has_value' => !blank($value),
                    'value' => $maskSecrets && $isSecret ? null : $value,
                    'masked_value' => $maskSecrets && $isSecret ? $this->maskSecret((string)$value) : $value,
                ];
            })
            ->values()
            ->all();
    }

    private function extractCredentialValues(array $carrierDefinition, array $input, array $existingCredentials = [], bool $preserveMaskedSecrets = false): array
    {
        $credentials = [];

        foreach ($carrierDefinition['credential_fields'] ?? [] as $field) {
            $fieldKey = $field['key'];
            if (array_key_exists($fieldKey, $input)) {
                $value = is_string($input[$fieldKey])
                    ? trim($input[$fieldKey])
                    : $input[$fieldKey];

                if (
                    $preserveMaskedSecrets
                    && ($field['type'] ?? 'text') === 'password'
                    && is_string($value)
                    && $value !== ''
                ) {
                    $maskedExistingValue = $this->maskSecret((string)($existingCredentials[$fieldKey] ?? ''));
                    if ($maskedExistingValue && hash_equals($maskedExistingValue, $value)) {
                        $value = $existingCredentials[$fieldKey] ?? '';
                    }
                }

                $credentials[$fieldKey] = $value;
            }
        }

        return $credentials;
    }

    private function maskSecret(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $visiblePart = Str::substr($value, -4);

        return str_repeat('*', max(strlen($value) - 4, 4)) . $visiblePart;
    }
}
