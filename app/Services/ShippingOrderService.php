<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShippingOrderService
{
    public static function createShipment(Order $order): void
    {
        if (!self::shouldCreateNoestShipment($order)) {
            return;
        }

        $vendorNoest = self::getVendorNoestConfig($order);
        if (!$vendorNoest) {
            return;
        }

        $payload = self::buildNoestPayload($order, $vendorNoest);
        if (!$payload) {
            self::persistShipmentData(
                order: $order,
                tracking: null,
                status: 'failed',
                payload: [],
                response: ['message' => 'Invalid NOEST payload'],
                error: 'Invalid NOEST payload'
            );
            return;
        }

        try {
            $httpResponse = Http::timeout(15)
                ->connectTimeout(5)
                ->acceptJson()
                ->withToken($vendorNoest->api_token)
                ->asForm()
                ->post('https://app.noest-dz.com/api/public/create/order', $payload);

            $responseData = $httpResponse->json() ?? [];
            $tracking = self::extractTracking($responseData);

            $success = $httpResponse->successful()
                && (($responseData['success'] ?? true) === true);

            self::persistShipmentData(
                order: $order,
                tracking: $tracking,
                status: $success ? 'created' : 'failed',
                payload: $payload,
                response: $responseData,
                error: $success ? null : ($responseData['message'] ?? 'NOEST create order failed')
            );
        } catch (\Throwable $exception) {
            Log::error('NOEST create shipment failed', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            self::persistShipmentData(
                order: $order,
                tracking: null,
                status: 'failed',
                payload: $payload,
                response: [],
                error: $exception->getMessage()
            );
        }
    }

    private static function shouldCreateNoestShipment(Order $order): bool
    {
        return (int)($order->shipping_method_id ?? 0) === 0
            && ($order->shipping_type ?? '') === 'order_wise'
            && !empty($order->shipping_address_data);
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
        $wilayaId = (int)ltrim((string)($shippingAddress['noest_wilaya_code'] ?? ''), '0');
        $commune = trim((string)($shippingAddress['noest_baladiya_name'] ?? ($shippingAddress['city'] ?? '')));
        $deliveryMethod = (string)($shippingAddress['noest_delivery_method'] ?? 'home_delivery');

        $products = collect($order->details ?? [])
            ->map(function ($detail) {
                $productDetails = is_string($detail->product_details)
                    ? json_decode($detail->product_details, true)
                    : (array)$detail->product_details;

                return $productDetails['name'] ?? null;
            })
            ->filter()
            ->implode(', ');

        if (!$client || !$phone || !$address || !$wilayaId || !$commune || !$products) {
            return null;
        }

        return [
            'api_token' => $vendorNoest->api_token,
            'user_guid' => $vendorNoest->noest_guid,
            'reference' => (string)$order->order_group_id,
            'client' => mb_substr($client, 0, 255),
            'phone' => $phone,
            'phone_2' => null,
            'adresse' => mb_substr($address, 0, 255),
            'wilaya_id' => $wilayaId,
            'commune' => mb_substr($commune, 0, 255),
            'montant' => (float)$order->order_amount,
            'remarque' => mb_substr((string)($order->order_note ?? ''), 0, 255),
            'produit' => mb_substr($products, 0, 255),
            'type_id' => 1,
            'type' => 1,
            'stop_desk' => $deliveryMethod === 'desk_delivery' ? 1 : 0,
        ];
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
}
