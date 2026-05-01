<?php

namespace App\Services\Shipping\Drivers;

use App\DTO\Shipping\ShipmentCreateResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Models\Order;
use App\Models\Wilaya;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class AbstractYalidineLikeDriver extends AbstractShippingCarrierDriver
{
    abstract protected function carrierKey(): string;

    abstract protected function carrierName(): string;

    abstract protected function baseUrl(): string;

    public function validateCredentials(array $credentials): array
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        $response = $this->request('get', '/wilayas', $preparedCredentials, [
            'page_size' => 1,
        ]);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        return [
            'supported' => true,
            'success' => true,
            'message' => 'connection_successful',
            'data' => $this->normalizeCollection($response['data']),
            'carrier_key' => $this->carrierKey(),
            'carrier_name' => $this->carrierName(),
        ];
    }

    public function getAvailableWilayas(array $credentials): array
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        $response = $this->request('get', '/wilayas', $preparedCredentials);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $data = collect($this->normalizeCollection($response['data']))
            ->map(fn ($item) => [
                'id' => (int) Arr::get($item, 'id'),
                'name' => (string) Arr::get($item, 'name', ''),
                'has_stop_desk' => filter_var(Arr::get($item, 'has_stop_desk', false), FILTER_VALIDATE_BOOLEAN),
                'is_deliverable' => filter_var(Arr::get($item, 'is_deliverable', true), FILTER_VALIDATE_BOOLEAN),
            ])
            ->filter(fn ($item) => $item['id'] > 0 && $item['name'] !== '')
            ->values()
            ->all();

        return $this->successArrayResult($data);
    }

    public function getAvailableCommunes(array $credentials, mixed $wilaya): array
    {
        return $this->unsupportedArrayResult('carrier_feature_not_supported_yet');
    }

    public function getDesks(array $credentials, mixed $wilaya = null): array
    {
        return $this->unsupportedArrayResult('carrier_feature_not_supported_yet');
    }

    public function getRates(array $credentials, array $payload): array
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        $fromWilayaId = (int) ($payload['from_wilaya_id'] ?? $preparedCredentials['from_wilaya_id'] ?? 0);
        $toWilayaId = (int) ($payload['to_wilaya_id'] ?? $payload['wilaya_id'] ?? 0);
        $communeId = $payload['commune_id'] ?? null;

        if ($fromWilayaId <= 0 || $toWilayaId <= 0 || blank($communeId)) {
            return $this->failedArrayResult('invalid_shipping_location');
        }

        $response = $this->request('get', '/fees', $preparedCredentials, [
            'from_wilaya_id' => $fromWilayaId,
            'to_wilaya_id' => $toWilayaId,
        ]);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $ratePayload = is_array($response['data']) ? $response['data'] : [];
        $perCommune = Arr::get($ratePayload, 'per_commune', []);
        $communeRate = Arr::get($perCommune, (string) $communeId);

        if (!is_array($communeRate)) {
            $communeRate = collect($perCommune)
                ->first(fn ($item) => (int) Arr::get($item, 'commune_id') === (int) $communeId);
        }

        if (!is_array($communeRate)) {
            return $this->failedArrayResult('shipping_rate_not_available', $ratePayload);
        }

        $deliveryMethods = collect([
            [
                'type' => 'home_delivery',
                'price' => $this->extractNumericValue($communeRate, ['express_home', 'economic_home']),
                'currency' => 'DZD',
                'estimated_delivery' => '24-72h',
            ],
            [
                'type' => 'desk_delivery',
                'price' => $this->extractNumericValue($communeRate, ['express_desk', 'economic_desk']),
                'currency' => 'DZD',
                'estimated_delivery' => '24-72h',
            ],
        ])
            ->filter(fn ($item) => is_numeric($item['price']))
            ->map(fn ($item) => [
                'carrier_key' => $this->carrierKey(),
                'type' => $item['type'],
                'price' => (float) $item['price'],
                'currency' => $item['currency'],
                'estimated_delivery' => $item['estimated_delivery'],
            ])
            ->when(!blank($payload['delivery_type'] ?? null), function ($collection) use ($payload) {
                return $collection->filter(fn ($item) => $item['type'] === $payload['delivery_type']);
            })
            ->values()
            ->all();

        if (empty($deliveryMethods)) {
            return $this->failedArrayResult('shipping_rate_not_available', $ratePayload);
        }

        return $this->successArrayResult($deliveryMethods);
    }

    public function createShipment(Order $order, array $credentials, array $options = []): ShipmentCreateResult
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedShipmentResult('shipping_credentials_are_required', $options);
        }

        $payloadData = $this->buildCreateShipmentPayload($order, $preparedCredentials, $options);
        if (!$payloadData['success']) {
            return $this->failedShipmentResult($payloadData['message'], $options, $payloadData['payload'] ?? []);
        }

        $response = $this->request('post', '/parcels', $preparedCredentials, [], [$payloadData['payload']]);

        if (!$response['success']) {
            return $this->failedShipmentResult(
                $response['message'],
                $options,
                $payloadData['payload'],
                is_array($response['data']) ? $response['data'] : []
            );
        }

        $shipment = $this->extractCreatedShipmentData(is_array($response['data']) ? $response['data'] : [], (string) $payloadData['payload']['order_id']);
        $trackingNumber = $this->stringValue(Arr::get($shipment, 'tracking'));
        $success = filter_var(Arr::get($shipment, 'success', $trackingNumber !== null), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $isSuccess = $success ?? ($trackingNumber !== null);
        $message = $this->stringValue(Arr::get($shipment, 'message')) ?? ($isSuccess ? 'shipment_created_successfully' : 'shipment_creation_failed');

        return new ShipmentCreateResult(
            supported: true,
            success: $isSuccess,
            message: $message,
            carrierKey: $this->carrierKey(),
            carrierName: $this->carrierName(),
            deliveryType: $options['delivery_type'] ?? 'home_delivery',
            trackingNumber: $trackingNumber,
            shippingStatus: $isSuccess ? 'created' : 'failed',
            payload: $payloadData['payload'],
            response: is_array($response['data']) ? $response['data'] : [],
            errorMessage: $isSuccess ? null : $message,
            remoteOrderId: $trackingNumber,
            deliveryPrice: $this->floatValue(Arr::get($shipment, 'delivery_fee')),
        );
    }

    public function trackShipment(string $trackingNumber, array $credentials): ShipmentTrackingResult
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedTrackingResult($trackingNumber, 'shipping_credentials_are_required');
        }

        $parcelResponse = $this->request('get', '/parcels/' . rawurlencode($trackingNumber), $preparedCredentials);

        if (!$parcelResponse['success']) {
            return $this->failedTrackingResult($trackingNumber, $parcelResponse['message'], $parcelResponse['data'] ?? []);
        }

        $parcel = $this->extractTrackedShipmentData(is_array($parcelResponse['data']) ? $parcelResponse['data'] : []);
        $shippingStatus = $this->mapRemoteStatusToShippingStatus((string) Arr::get($parcel, 'last_status'));

        $events = [];
        $historyResponse = $this->request('get', '/histories/' . rawurlencode($trackingNumber), $preparedCredentials);
        if ($historyResponse['success']) {
            $events = collect($this->normalizeCollection($historyResponse['data']))
                ->map(function ($item) {
                    $remoteStatus = (string) Arr::get($item, 'status', '');

                    return [
                        'shipping_status' => $this->mapRemoteStatusToShippingStatus($remoteStatus),
                        'label' => $remoteStatus,
                        'description' => (string) (Arr::get($item, 'reason') ?: $remoteStatus),
                        'event_at' => Arr::get($item, 'date_status') ?: now(),
                    ];
                })
                ->values()
                ->all();
        }

        return new ShipmentTrackingResult(
            supported: true,
            success: true,
            message: 'connection_successful',
            carrierKey: $this->carrierKey(),
            carrierName: $this->carrierName(),
            trackingNumber: $trackingNumber,
            shippingStatus: $shippingStatus,
            events: $events,
            response: is_array($parcelResponse['data']) ? $parcelResponse['data'] : [],
            errorMessage: null,
        );
    }

    public function cancelShipment(string $trackingNumber, array $credentials): array
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        $response = $this->request('delete', '/parcels/' . rawurlencode($trackingNumber), $preparedCredentials);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        return $this->successArrayResult(is_array($response['data']) ? $response['data'] : []);
    }

    public function getLabel(string $trackingNumber, array $credentials): mixed
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        $response = $this->request('get', '/parcels/' . rawurlencode($trackingNumber), $preparedCredentials);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $parcel = $this->extractTrackedShipmentData(is_array($response['data']) ? $response['data'] : []);
        $labelUrl = $this->stringValue(Arr::get($parcel, 'label') ?: Arr::get($parcel, 'labels'));

        if (!$labelUrl) {
            return $this->failedArrayResult('carrier_feature_not_supported_yet', is_array($response['data']) ? $response['data'] : []);
        }

        return [
            'supported' => true,
            'success' => true,
            'message' => 'connection_successful',
            'data' => [
                'type' => 'url',
                'url' => $labelUrl,
            ],
            'carrier_key' => $this->carrierKey(),
            'carrier_name' => $this->carrierName(),
        ];
    }

    protected function request(
        string $method,
        string $uri,
        array $credentials,
        array $query = [],
        array $payload = []
    ): array {
        try {
            $request = Http::timeout((int) config('shipping_carriers.request_timeout', 15))
                ->connectTimeout((int) config('shipping_carriers.connect_timeout', 5))
                ->acceptJson()
                ->withHeaders([
                    'X-API-ID' => $credentials['api_id'],
                    'X-API-TOKEN' => $credentials['api_token'],
                ]);

            $response = match (Str::lower($method)) {
                'post' => $request->post($this->baseUrl() . $uri, $payload),
                'delete' => $request->delete($this->baseUrl() . $uri, $query),
                default => $request->get($this->baseUrl() . $uri, $query),
            };

            $decodedJson = $response->json();
            $responseData = is_array($decodedJson) ? $decodedJson : [];

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->extractErrorMessage($responseData, $response->body()),
                    'data' => $responseData,
                    'status' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'message' => 'connection_successful',
                'data' => $responseData,
                'status' => $response->status(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage() ?: 'connection_failed',
                'data' => [],
                'status' => 0,
            ];
        }
    }

    protected function prepareCredentials(array $credentials): array
    {
        $apiId = trim((string) ($credentials['api_id'] ?? $credentials['id'] ?? ''));
        $apiToken = trim((string) ($credentials['api_token'] ?? $credentials['token'] ?? ''));

        return [
            'valid' => $apiId !== '' && $apiToken !== '',
            'api_id' => $apiId,
            'api_token' => $apiToken,
            'from_wilaya_id' => isset($credentials['from_wilaya_id']) && is_numeric($credentials['from_wilaya_id'])
                ? (int) $credentials['from_wilaya_id']
                : null,
            'from_wilaya_name' => blank($credentials['from_wilaya_name'] ?? null)
                ? null
                : (string) $credentials['from_wilaya_name'],
        ];
    }

    protected function successArrayResult(array $data): array
    {
        return [
            'supported' => true,
            'success' => true,
            'message' => null,
            'data' => $data,
            'carrier_key' => $this->carrierKey(),
            'carrier_name' => $this->carrierName(),
        ];
    }

    protected function failedArrayResult(string $message, array $data = []): array
    {
        return [
            'supported' => true,
            'success' => false,
            'message' => $message,
            'data' => $data,
            'carrier_key' => $this->carrierKey(),
            'carrier_name' => $this->carrierName(),
        ];
    }

    protected function failedShipmentResult(string $message, array $options = [], array $payload = [], array $response = []): ShipmentCreateResult
    {
        return new ShipmentCreateResult(
            supported: true,
            success: false,
            message: $message,
            carrierKey: $this->carrierKey(),
            carrierName: $this->carrierName(),
            deliveryType: $options['delivery_type'] ?? 'home_delivery',
            trackingNumber: null,
            shippingStatus: 'failed',
            payload: $payload,
            response: $response,
            errorMessage: $message,
        );
    }

    protected function failedTrackingResult(string $trackingNumber, string $message, array $response = []): ShipmentTrackingResult
    {
        return new ShipmentTrackingResult(
            supported: true,
            success: false,
            message: $message,
            carrierKey: $this->carrierKey(),
            carrierName: $this->carrierName(),
            trackingNumber: $trackingNumber,
            shippingStatus: null,
            response: $response,
            errorMessage: $message,
        );
    }

    protected function normalizeCollection(array $payload): array
    {
        if (Arr::isAssoc($payload) && isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return $payload;
    }

    protected function extractCreatedShipmentData(array $payload, string $orderId): array
    {
        if (isset($payload[$orderId]) && is_array($payload[$orderId])) {
            return $payload[$orderId];
        }

        if (!Arr::isAssoc($payload) && isset($payload[0]) && is_array($payload[0])) {
            return $payload[0];
        }

        if (Arr::isAssoc($payload) && isset($payload['data'][0]) && is_array($payload['data'][0])) {
            return $payload['data'][0];
        }

        return $payload;
    }

    protected function extractTrackedShipmentData(array $payload): array
    {
        if (Arr::isAssoc($payload) && isset($payload['data'][0]) && is_array($payload['data'][0])) {
            return $payload['data'][0];
        }

        return $payload;
    }

    protected function buildCreateShipmentPayload(Order $order, array $credentials, array $options): array
    {
        $shippingAddress = (array) ($order->shipping_address_data ?? []);
        $originWilayaName = $credentials['from_wilaya_name']
            ?: optional(Wilaya::find($credentials['from_wilaya_id']))->name;
        $destinationWilayaName = $options['wilaya_name']
            ?? $shippingAddress['wilaya_name']
            ?? optional(Wilaya::find($options['wilaya_id'] ?? $shippingAddress['wilaya_id'] ?? null))->name;
        $destinationCommuneName = $this->firstNotBlank(
            $shippingAddress['commune_name'] ?? null,
            $shippingAddress['commune'] ?? null,
            $shippingAddress['baladiya_name'] ?? null,
            $shippingAddress['city'] ?? null,
            $options['commune_name'] ?? null
        );
        $deliveryType = (string) ($options['delivery_type'] ?? $shippingAddress['delivery_type'] ?? 'home_delivery');
        $stopdeskId = $options['desk_code'] ?? $shippingAddress['desk_code'] ?? $shippingAddress['station_code'] ?? null;
        [$firstName, $familyName] = $this->splitName((string) ($shippingAddress['contact_person_name'] ?? ''));

        $orderDetails = $order->relationLoaded('details') ? $order->details : $order->details()->get();
        $productList = collect($orderDetails)
            ->map(function ($detail) {
                $productDetails = is_array($detail->product_details)
                    ? $detail->product_details
                    : (json_decode((string) $detail->product_details, true) ?: []);

                return (string) ($productDetails['name'] ?? $detail->product?->name ?? '');
            })
            ->filter()
            ->implode(', ');

        if (
            blank($originWilayaName)
            || blank($destinationWilayaName)
            || blank($destinationCommuneName)
            || blank($firstName)
            || blank((string) ($shippingAddress['phone'] ?? ''))
            || blank((string) ($shippingAddress['address'] ?? ''))
            || blank($productList)
        ) {
            return [
                'success' => false,
                'message' => 'invalid_shipping_location',
                'payload' => [],
            ];
        }

        if ($deliveryType === 'desk_delivery' && blank($stopdeskId)) {
            return [
                'success' => false,
                'message' => 'invalid_shipping_location',
                'payload' => [],
            ];
        }

        $payload = [
            'order_id' => (string) ($order->order_group_id ?: $order->id),
            'from_wilaya_name' => (string) $originWilayaName,
            'firstname' => $firstName,
            'familyname' => $familyName,
            'contact_phone' => preg_replace('/\s+/', '', (string) ($shippingAddress['phone'] ?? '')),
            'address' => (string) ($shippingAddress['address'] ?? ''),
            'to_commune_name' => (string) $destinationCommuneName,
            'to_wilaya_name' => (string) $destinationWilayaName,
            'product_list' => $productList,
            'price' => (float) ($order->order_amount ?? 0),
            'do_insurance' => false,
            'declared_value' => (float) ($order->order_amount ?? 0),
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'weight' => 0,
            'freeshipping' => (float) ($options['shipping_cost'] ?? $shippingAddress['shipping_cost'] ?? $order->shipping_cost ?? 0) <= 0,
            'is_stopdesk' => $deliveryType === 'desk_delivery',
            'has_exchange' => false,
        ];

        if ($deliveryType === 'desk_delivery') {
            $payload['stopdesk_id'] = (int) $stopdeskId;
        }

        return [
            'success' => true,
            'message' => null,
            'payload' => $payload,
        ];
    }

    protected function mapRemoteStatusToShippingStatus(?string $status): ?string
    {
        $normalizedStatus = Str::lower(Str::ascii((string) $status));

        return match (true) {
            $normalizedStatus === '' => null,
            str_contains($normalizedStatus, 'livr') => 'delivered',
            str_contains($normalizedStatus, 'retour') => 'returned',
            str_contains($normalizedStatus, 'sorti en livraison'), str_contains($normalizedStatus, 'pret pour livreur') => 'out_for_delivery',
            str_contains($normalizedStatus, 'annul') => 'canceled',
            str_contains($normalizedStatus, 'echec'), str_contains($normalizedStatus, 'echou'), str_contains($normalizedStatus, 'bloqu'), str_contains($normalizedStatus, 'alerte') => 'failed',
            default => 'processing',
        };
    }

    protected function extractErrorMessage(mixed $json, string $body): string
    {
        if (is_array($json)) {
            foreach (['message', 'detail', 'error'] as $key) {
                $value = Arr::get($json, $key);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return $body !== '' ? $body : 'connection_failed';
    }

    protected function stringValue(mixed $value): ?string
    {
        return blank($value) ? null : (string) $value;
    }

    protected function floatValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function extractNumericValue(array $payload, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    protected function firstNotBlank(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (!blank($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function splitName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName));

        if ($fullName === '') {
            return ['', ''];
        }

        $parts = explode(' ', $fullName, 2);

        return [
            $parts[0] ?? '',
            $parts[1] ?? '.',
        ];
    }
}
