<?php

namespace App\Services;

use App\DTO\Shipping\ShipmentCreateResult;
use App\Models\Order;
use App\Services\Shipping\ShippingCarrierManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShippingOrderService
{
    public static function createShipment(Order $order): void
    {
        $shippingCarrierManager = app(ShippingCarrierManager::class);
        $shippingMeta = $shippingCarrierManager->extractOrderShippingMeta($order);
        $hasLegacyNoestSelection = self::shouldCreateNoestShipment($order);

        if (empty($shippingMeta['carrier_key']) && !$hasLegacyNoestSelection) {
            return;
        }

        $shipmentResult = $shippingCarrierManager->createShipmentForOrder($order);

        if (!$shipmentResult->supported && !$hasLegacyNoestSelection) {
            return;
        }

        self::persistShipmentResult($order, $shipmentResult);
    }

        private static function shouldCreateNoestShipment(Order $order): bool
    {
        $shippingAddress = (array)($order->shipping_address_data ?? []);

    $deliveryMethod = (string)($shippingAddress['noest_delivery_method'] ?? '');

$hasHomeRequirements = $deliveryMethod === 'home_delivery'
    && !empty($shippingAddress['noest_baladiya_name']);

$hasDeskRequirements = $deliveryMethod === 'desk_delivery'
    && !empty($shippingAddress['noest_station_code']);

return ($order->shipping_type ?? '') === 'order_wise'
    && !empty($shippingAddress)
    && !empty($shippingAddress['noest_wilaya_code'])
    && !empty($shippingAddress['noest_delivery_method'])
    && ($hasHomeRequirements || $hasDeskRequirements);
    }
    private static function getVendorNoestConfig(Order $order): ?object
    {
        $vendorId = $order->seller_is === 'admin' ? 0 : $order->seller_id;

        return DB::table('vendor_shipping_companies')
            ->where('vendor_id', $vendorId)
            ->whereRaw('LOWER(name) = ?', ['noest'])
            ->where('status', 1)
            ->first();
    }

    private static function buildNoestPayload(Order $order, object $vendorNoest): ?array
{
    $shippingAddress = (array)($order->shipping_address_data ?? []);

    $client = trim((string)($shippingAddress['contact_person_name'] ?? ''));
    $phone = self::normalizePhone((string)($shippingAddress['phone'] ?? ''));
    $address = trim((string)($shippingAddress['address'] ?? ''));
    $wilayaId = (int) ltrim((string)($shippingAddress['noest_wilaya_code'] ?? ''), '0');
    $commune = self::normalizeNoestCommune((string)($shippingAddress['noest_baladiya_name'] ?? ''));
    $deliveryMethod = (string)($shippingAddress['noest_delivery_method'] ?? 'home_delivery');
    $stationCode = trim((string)($shippingAddress['noest_station_code'] ?? ''));

    $products = collect($order->details ?? [])
        ->map(function ($detail) {
            $productDetails = is_string($detail->product_details)
                ? json_decode($detail->product_details, true)
                : (array)$detail->product_details;

            return $productDetails['name'] ?? null;
        })
        ->filter()
        ->implode(', ');

    if (!$client || !$phone || !$address || !$wilayaId || !$products) {
        return null;
    }

    if ($deliveryMethod === 'home_delivery' && !$commune) {
        return null;
    }

    if ($deliveryMethod === 'desk_delivery' && !$stationCode) {
        return null;
    }

    Log::info('NOEST commune payload debug', [
        'order_id' => $order->id,
        'local_city' => $shippingAddress['city'] ?? null,
        'noest_baladiya_name' => $shippingAddress['noest_baladiya_name'] ?? null,
        'normalized_commune' => $commune,
        'wilaya_id' => $wilayaId,
        'station_code' => $stationCode,
        'delivery_method' => $deliveryMethod,
    ]);

    $payload = [
        'api_token' => $vendorNoest->api_token,
        'user_guid' => $vendorNoest->noest_guid,
        'reference' => (string)$order->order_group_id,
        'client' => mb_substr($client, 0, 255),
        'phone' => $phone,
        'phone_2' => null,
        'adresse' => mb_substr($address, 0, 255),
        'wilaya_id' => $wilayaId,
        'montant' => (float)$order->order_amount,
        'remarque' => mb_substr((string)($order->order_note ?? ''), 0, 255),
        'produit' => mb_substr($products, 0, 255),
        'type_id' => 1,
        'type' => 1,
        'stop_desk' => $deliveryMethod === 'desk_delivery' ? 1 : 0,
    ];

    if ($deliveryMethod === 'home_delivery') {
        $payload['commune'] = mb_substr($commune, 0, 255);
    }

    if ($deliveryMethod === 'desk_delivery') {
        $payload['station_code'] = $stationCode;
    }

    return $payload;
}
    private static function normalizeNoestCommune(string $commune): string
{
    $commune = preg_replace('/\s+/u', ' ', $commune);
    $commune = trim((string)$commune);

    if ($commune === '') {
        return '';
    }

    return mb_convert_case(mb_strtolower($commune, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '213')) {
            $phone = '0' . substr($phone, 3);
        }

        return substr($phone, 0, 10);
    }

    private static function extractTracking(array $response): ?string
    {
        $directKeys = ['tracking', 'tracking_number', 'track_number', 'barcode'];

        foreach ($directKeys as $key) {
            if (!empty($response[$key]) && is_string($response[$key])) {
                return $response[$key];
            }
        }

        if (isset($response['data']) && is_array($response['data'])) {
            foreach ($directKeys as $key) {
                if (!empty($response['data'][$key]) && is_string($response['data'][$key])) {
                    return $response['data'][$key];
                }
            }
        }

        array_walk_recursive($response, function ($value) use (&$tracking) {
            if (!isset($tracking) && is_string($value) && preg_match('/^[A-Z0-9]{1,}-[A-Z0-9-]+$/i', $value)) {
                $tracking = $value;
            }
        });

        return $tracking ?? null;
    }

    private static function persistShipmentData(
        Order $order,
        ?string $tracking,
        string $status,
        array $payload,
        array $response,
        ?string $error = null
    ): void {
        $deliveryType = (($payload['stop_desk'] ?? 0) == 1) ? 'desk_delivery' : 'home_delivery';

        $order->delivery_service_name = 'NOEST';
        $order->delivery_type = $deliveryType;
        if ($tracking) {
            $order->third_party_delivery_tracking_id = $tracking;
        }
        $order->save();

        if (!Schema::hasTable('order_shipping_details')) {
            return;
        }

        $columns = Schema::getColumnListing('order_shipping_details');
        if (!in_array('order_id', $columns)) {
            return;
        }

        $candidateData = [
            'order_id' => $order->id,
            'tracking_number' => $tracking,
            'tracking' => $tracking,
            'delivery_service_name' => 'NOEST',
            'service_name' => 'NOEST',
            'delivery_type' => $deliveryType,
            'shipping_status' => $status,
            'status' => $status,
            'shipment_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'shipment_response' => json_encode($response, JSON_UNESCAPED_UNICODE),
            'response_payload' => json_encode($response, JSON_UNESCAPED_UNICODE),
            'error_message' => $error,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $filteredData = [];
        foreach ($candidateData as $column => $value) {
            if (in_array($column, $columns)) {
                $filteredData[$column] = $value;
            }
        }

        if (!empty($filteredData)) {
            DB::table('order_shipping_details')->updateOrInsert(
                ['order_id' => $order->id],
                $filteredData
            );
        }
    }

    private static function persistShipmentResult(Order $order, ShipmentCreateResult $shipmentResult): void
    {
        $carrierName = $shipmentResult->carrierName ?: ($shipmentResult->carrierKey ? strtoupper($shipmentResult->carrierKey) : null);
        $deliveryType = $shipmentResult->deliveryType ?: 'home_delivery';

        if ($carrierName) {
            $order->delivery_service_name = $carrierName;
            $order->delivery_type = $deliveryType;
        }

        if ($shipmentResult->trackingNumber) {
            $order->third_party_delivery_tracking_id = $shipmentResult->trackingNumber;
        }

        $order->save();

        if (!Schema::hasTable('order_shipping_details')) {
            return;
        }

        $columns = Schema::getColumnListing('order_shipping_details');
        if (!in_array('order_id', $columns)) {
            return;
        }

        $candidateData = [
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'carrier_key' => $shipmentResult->carrierKey,
            'carrier_name' => $carrierName,
            'tracking_number' => $shipmentResult->trackingNumber,
            'tracking_id' => $shipmentResult->trackingNumber,
            'tracking' => $shipmentResult->trackingNumber,
            'remote_order_id' => $shipmentResult->remoteOrderId,
            'remote_display_id' => $shipmentResult->remoteDisplayId,
            'delivery_price' => $shipmentResult->deliveryPrice,
            'delivery_service_name' => $carrierName,
            'service_name' => $carrierName,
            'delivery_type' => $deliveryType,
            'shipping_status' => $shipmentResult->shippingStatus,
            'status' => $shipmentResult->shippingStatus,
            'shipment_payload' => json_encode($shipmentResult->payload, JSON_UNESCAPED_UNICODE),
            'request_payload' => json_encode($shipmentResult->payload, JSON_UNESCAPED_UNICODE),
            'shipment_response' => json_encode($shipmentResult->response, JSON_UNESCAPED_UNICODE),
            'response_payload' => json_encode($shipmentResult->response, JSON_UNESCAPED_UNICODE),
            'error_message' => $shipmentResult->errorMessage,
            'desk_code' => $shipmentResult->payload['station_code'] ?? $shipmentResult->payload['desk_code'] ?? null,
            'desk_name' => $shipmentResult->payload['desk_name'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $filteredData = [];
        foreach ($candidateData as $column => $value) {
            if (in_array($column, $columns)) {
                $filteredData[$column] = $value;
            }
        }

        if (!empty($filteredData)) {
            DB::table('order_shipping_details')->updateOrInsert(
                ['order_id' => $order->id],
                $filteredData
            );
        }
    }
}
