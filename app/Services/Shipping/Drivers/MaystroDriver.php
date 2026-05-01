<?php

namespace App\Services\Shipping\Drivers;

use App\DTO\Shipping\ShipmentCreateResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Models\Order;
use App\Models\Wilaya;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MaystroDriver extends AbstractShippingCarrierDriver
{
    private const CARRIER_KEY = 'maystro';
    private const CARRIER_NAME = 'Maystro Delivery';

    public function validateCredentials(array $credentials): array
    {
        if (!$this->hasRequiredCredentials($credentials)) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        $response = $this->request('get', '/api/base/wilayas/', $credentials, [
            'language' => $this->resolveLanguage(),
            'country' => 1,
        ]);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        return [
            'supported' => true,
            'success' => true,
            'message' => 'connection_successful',
            'data' => $response['data'],
            'carrier_key' => self::CARRIER_KEY,
            'carrier_name' => self::CARRIER_NAME,
        ];
    }

    public function getAvailableWilayas(array $credentials): array
    {
        if (!$this->hasRequiredCredentials($credentials)) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        $response = $this->request('get', '/api/base/wilayas/', $credentials, [
            'language' => $this->resolveLanguage(),
            'country' => 1,
        ]);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $data = collect($this->normalizeListPayload($response['data']))
            ->map(fn ($item) => [
                'id' => (int) Arr::get($item, 'id'),
                'name' => (string) (Arr::get($item, 'name') ?? Arr::get($item, 'name_ar') ?? Arr::get($item, 'name_lt') ?? ''),
            ])
            ->filter(fn ($item) => $item['id'] > 0 && $item['name'] !== '')
            ->values()
            ->all();

        return $this->successArrayResult($data);
    }

    public function getAvailableCommunes(array $credentials, mixed $wilaya): array
    {
        if (!$this->hasRequiredCredentials($credentials)) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        if (blank($wilaya)) {
            return $this->failedArrayResult('invalid_shipping_location');
        }

        $response = $this->request('get', '/api/base/communes/', $credentials, [
            'wilaya' => $wilaya,
        ]);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $data = collect($this->normalizeListPayload($response['data']))
            ->map(fn ($item) => [
                'id' => (int) Arr::get($item, 'id'),
                'wilaya_id' => (int) Arr::get($item, 'wilaya'),
                'name' => (string) Arr::get($item, 'name', ''),
                'postcode' => Arr::get($item, 'postcode'),
                'zone' => Arr::get($item, 'zone'),
            ])
            ->filter(fn ($item) => $item['id'] > 0)
            ->values()
            ->all();

        return $this->successArrayResult($data);
    }

    public function getDesks(array $credentials, mixed $wilaya = null): array
    {
        if (!$this->hasRequiredCredentials($credentials)) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        if (blank($wilaya)) {
            return $this->failedArrayResult('invalid_shipping_location');
        }

        $response = $this->request('get', '/api/base/pickup-points', $credentials, [
            'commune' => $wilaya,
        ]);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $data = collect($this->normalizeListPayload($response['data']))
            ->filter(fn ($item) => filter_var(Arr::get($item, 'active', false), FILTER_VALIDATE_BOOLEAN))
            ->map(function ($item) {
                $deliveryType = (int) Arr::get($item, 'delivery_type');
                $pickupPointId = Arr::get($item, 'pickup_point');

                return [
                    'id' => (int) Arr::get($item, 'id', $pickupPointId),
                    'commune_id' => Arr::get($item, 'commune'),
                    'name' => (string) (Arr::get($item, 'name_ar') ?: Arr::get($item, 'name_lt') ?: Arr::get($item, 'name') ?: ''),
                    'name_ar' => Arr::get($item, 'name_ar'),
                    'name_lt' => Arr::get($item, 'name_lt'),
                    'pickup_point_id' => $pickupPointId,
                    'remote_type' => $deliveryType === 3 ? 'pickup' : 'stopdesk',
                    'type' => $deliveryType === 3 ? 'pickup_point' : 'desk_delivery',
                    'delivery_type' => $deliveryType,
                    'active' => true,
                ];
            })
            ->filter(fn ($item) => in_array($item['delivery_type'], [2, 3], true))
            ->values()
            ->all();

        return $this->successArrayResult($data);
    }

    public function getRates(array $credentials, array $payload): array
    {
        if (!$this->hasRequiredCredentials($credentials)) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        $communeId = $payload['commune_id'] ?? $payload['commune'] ?? null;
        if (blank($communeId)) {
            return $this->failedArrayResult('invalid_shipping_location');
        }

        $response = $this->request('get', '/api/base/delivery-options/', $credentials, [
            'commune' => $communeId,
        ]);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $requestedType = $payload['delivery_type'] ?? null;

        $data = collect($this->normalizeListPayload($response['data']))
            ->map(function ($item) {
                $remoteType = Str::lower((string) Arr::get($item, 'type', ''));
                $mapping = $this->mapDeliveryTypeFromRemoteType($remoteType);
                if (!$mapping) {
                    return null;
                }

                return array_filter([
                    'carrier_key' => self::CARRIER_KEY,
                    'type' => $mapping['type'],
                    'remote_type' => $remoteType,
                    'delivery_type' => $mapping['delivery_type'],
                    'price' => (double) Arr::get($item, 'price', 0),
                    'currency' => 'DZD',
                    'min_total_price' => Arr::get($item, 'min_total_price'),
                    'name' => Arr::get($item, 'name'),
                    'pickup_point_id' => Arr::get($item, 'pickup_point_id') ?? Arr::get($item, 'pickup_point'),
                ], fn ($value) => !is_null($value));
            })
            ->filter()
            ->when($requestedType, function ($collection) use ($requestedType) {
                return $collection->filter(fn ($item) => $item['type'] === $requestedType);
            })
            ->values()
            ->all();

        if (empty($data)) {
            return $this->failedArrayResult('shipping_rate_not_available');
        }

        return $this->successArrayResult($data);
    }

    public function createShipment(Order $order, array $credentials, array $options = []): ShipmentCreateResult
    {
        if (!$this->hasRequiredCredentials($credentials)) {
            return $this->failedShipmentResult('shipping_credentials_are_required', $options);
        }

        $payload = $this->buildCreateShipmentPayload($order, $options);
        if (!$payload['success']) {
            return $this->failedShipmentResult($payload['message'], $options, $payload['payload'] ?? []);
        }

        $response = $this->request('post', '/api/orders', $credentials, [], $payload['payload']);
        if (!$response['success']) {
            return $this->failedShipmentResult(
                $response['message'],
                $options,
                $payload['payload'],
                $response['data'] ?? []
            );
        }

        $responseData = $this->extractCreateShipmentPayload($response['data']);
        $isCreated = filter_var(Arr::get($responseData, 'success', true), FILTER_VALIDATE_BOOLEAN);
        $errors = Arr::get($responseData, 'errors', []);

        if (!$isCreated || !empty($errors)) {
            return new ShipmentCreateResult(
                supported: true,
                success: false,
                message: Arr::first((array) $errors) ?: 'shipment_creation_failed',
                carrierKey: self::CARRIER_KEY,
                carrierName: self::CARRIER_NAME,
                deliveryType: $options['delivery_type'] ?? 'home_delivery',
                trackingNumber: null,
                shippingStatus: 'failed',
                payload: $payload['payload'],
                response: is_array($response['data']) ? $response['data'] : [],
                errorMessage: Arr::first((array) $errors) ?: 'shipment_creation_failed',
                remoteOrderId: $this->stringValue(Arr::get($responseData, 'id')),
                remoteDisplayId: $this->stringValue(Arr::get($responseData, 'display_id_order') ?? Arr::get($responseData, 'display_id')),
                deliveryPrice: $this->floatValue(Arr::get($responseData, 'delivery_price')),
            );
        }

        return new ShipmentCreateResult(
            supported: true,
            success: true,
            message: 'shipment_created_successfully',
            carrierKey: self::CARRIER_KEY,
            carrierName: self::CARRIER_NAME,
            deliveryType: $options['delivery_type'] ?? 'home_delivery',
            trackingNumber: $this->stringValue(Arr::get($responseData, 'tracking')),
            shippingStatus: 'created',
            payload: $payload['payload'],
            response: is_array($response['data']) ? $response['data'] : [],
            errorMessage: null,
            remoteOrderId: $this->stringValue(Arr::get($responseData, 'id')),
            remoteDisplayId: $this->stringValue(Arr::get($responseData, 'display_id_order') ?? Arr::get($responseData, 'display_id')),
            deliveryPrice: $this->floatValue(Arr::get($responseData, 'delivery_price')),
        );
    }

    public function trackShipment(string $trackingNumber, array $credentials): ShipmentTrackingResult
    {
        return new ShipmentTrackingResult(
            supported: false,
            success: false,
            message: 'carrier_feature_not_supported_yet',
            carrierKey: self::CARRIER_KEY,
            carrierName: self::CARRIER_NAME,
            trackingNumber: $trackingNumber,
            shippingStatus: null,
            response: [],
            errorMessage: 'carrier_feature_not_supported_yet',
        );
    }

    public function cancelShipment(string $trackingNumber, array $credentials): array
    {
        return $this->failedArrayResult('carrier_feature_not_supported_yet');
    }

    public function getLabel(string $trackingNumber, array $credentials): mixed
    {
        if (!$this->hasRequiredCredentials($credentials)) {
            return $this->failedArrayResult('shipping_credentials_are_required');
        }

        if (blank($trackingNumber)) {
            return $this->failedArrayResult('missing_remote_display_id');
        }

        $response = $this->request('post', '/api/starter_bordureau/', $credentials, [], [
            'orders_ids' => [$trackingNumber],
        ], ['Accept' => 'application/pdf']);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        return [
            'supported' => true,
            'success' => true,
            'message' => 'connection_successful',
            'data' => [
                'content' => $response['raw_body'] ?? null,
                'content_type' => $response['content_type'] ?? 'application/pdf',
            ],
            'carrier_key' => self::CARRIER_KEY,
            'carrier_name' => self::CARRIER_NAME,
        ];
    }

    public static function mapMaystroStatusCode(int|string|null $statusCode): ?string
    {
        return match ((int) $statusCode) {
            41 => 'delivered',
            31, 22, 15 => 'out_for_delivery',
            50, 53 => 'failed',
            51, 52, 10 => 'returned',
            default => null,
        };
    }

    public static function decodeMaystroWebhookPayload(?string $encodedPayload): ?array
    {
        if (blank($encodedPayload)) {
            return null;
        }

        $decoded = base64_decode((string) $encodedPayload, true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) ? $payload : null;
    }

    private function buildCreateShipmentPayload(Order $order, array $options): array
    {
        $shippingAddress = (array) ($order->shipping_address_data ?? []);
        $deliveryType = (string) ($options['delivery_type'] ?? $shippingAddress['delivery_type'] ?? 'home_delivery');
        $remoteDeliveryType = (int) ($options['remote_delivery_type'] ?? $shippingAddress['remote_delivery_type'] ?? $this->mapRemoteDeliveryType($deliveryType));
        $communeId = $options['commune_id'] ?? $shippingAddress['commune_id'] ?? null;
        $wilayaName = $options['wilaya_name'] ?? $shippingAddress['wilaya_name'] ?? optional(Wilaya::find($options['wilaya_id'] ?? $shippingAddress['wilaya_id'] ?? null))->name;
        $customerName = trim((string) ($shippingAddress['contact_person_name'] ?? ''));
        $customerPhone = trim((string) ($shippingAddress['phone'] ?? ''));
        $destinationText = trim((string) ($shippingAddress['address'] ?? ''));
        $pickupPointId = $options['pickup_point_id'] ?? $shippingAddress['pickup_point_id'] ?? null;

        $orderDetails = $order->relationLoaded('details') ? $order->details : $order->details()->get();
        $details = collect($orderDetails)
            ->map(function ($detail) {
                $productDetails = is_array($detail->product_details)
                    ? $detail->product_details
                    : (json_decode((string) $detail->product_details, true) ?: []);

                return [
                    'product' => '',
                    'description' => (string) ($productDetails['name'] ?? $detail->product?->name ?? 'Product'),
                    'quantity' => (int) ($detail->qty ?? 1),
                ];
            })
            ->filter(fn ($item) => !blank($item['description']) && $item['quantity'] > 0)
            ->values()
            ->all();

        if (
            blank($customerName)
            || blank($customerPhone)
            || blank($destinationText)
            || blank($communeId)
            || blank($wilayaName)
            || empty($details)
        ) {
            return [
                'success' => false,
                'message' => 'shipping_credentials_are_required',
                'payload' => [],
            ];
        }

        if ($remoteDeliveryType === 3 && blank($pickupPointId)) {
            return [
                'success' => false,
                'message' => 'invalid_shipping_location',
                'payload' => [],
            ];
        }

        $payload = [
            'external_id' => (string) ($order->order_group_id ?: $order->id),
            'destination_text' => $destinationText,
            'total_price' => (double) ($order->order_amount ?? 0),
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_phone2' => null,
            'express' => false,
            'commune' => (int) $communeId,
            'wilaya' => (string) $wilayaName,
            'note_to_driver' => blank($order->order_note) ? null : (string) $order->order_note,
            'details' => $details,
            'delivery_type' => $remoteDeliveryType,
        ];

        if ($remoteDeliveryType === 3) {
            $payload['pickup_point'] = $pickupPointId;
        }

        return [
            'success' => true,
            'message' => null,
            'payload' => $payload,
        ];
    }

    private function request(
        string $method,
        string $uri,
        array $credentials,
        array $query = [],
        array $payload = [],
        array $headers = []
    ): array {
        try {
            $request = Http::timeout((int) config('shipping_carriers.request_timeout', 15))
                ->connectTimeout((int) config('shipping_carriers.connect_timeout', 5))
                ->acceptJson()
                ->withHeaders(array_merge([
                    'Authorization' => 'token ' . $credentials['store_token'],
                    'Content-Type' => 'application/json',
                ], $headers));

            $response = match (Str::lower($method)) {
                'post' => $request->post($this->baseUrl() . $uri, $payload),
                default => $request->get($this->baseUrl() . $uri, $query),
            };

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->extractErrorMessage($response->json(), $response->body()),
                    'data' => is_array($response->json()) ? $response->json() : [],
                    'status' => $response->status(),
                ];
            }

            $decodedJson = $response->json();
            $responseData = is_array($decodedJson) ? $decodedJson : [];

            return [
                'success' => true,
                'message' => 'connection_successful',
                'data' => $responseData,
                'status' => $response->status(),
                'raw_body' => $response->body(),
                'content_type' => $response->header('Content-Type'),
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

    private function hasRequiredCredentials(array $credentials): bool
    {
        return !blank($credentials['store_token'] ?? null);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('shipping_carriers.carriers.maystro.base_url', 'https://orders-management.maystro-delivery.com'), '/');
    }

    private function resolveLanguage(): string
    {
        return in_array(app()->getLocale(), ['ae', 'ar'], true) ? 'ar' : 'fr';
    }

    private function successArrayResult(array $data): array
    {
        return [
            'supported' => true,
            'success' => true,
            'message' => null,
            'data' => $data,
            'carrier_key' => self::CARRIER_KEY,
            'carrier_name' => self::CARRIER_NAME,
        ];
    }

    private function failedArrayResult(string $message, array $data = []): array
    {
        return [
            'supported' => true,
            'success' => false,
            'message' => $message,
            'data' => $data,
            'carrier_key' => self::CARRIER_KEY,
            'carrier_name' => self::CARRIER_NAME,
        ];
    }

    private function failedShipmentResult(string $message, array $options = [], array $payload = [], array $response = []): ShipmentCreateResult
    {
        return new ShipmentCreateResult(
            supported: true,
            success: false,
            message: $message,
            carrierKey: self::CARRIER_KEY,
            carrierName: self::CARRIER_NAME,
            deliveryType: $options['delivery_type'] ?? 'home_delivery',
            trackingNumber: null,
            shippingStatus: 'failed',
            payload: $payload,
            response: $response,
            errorMessage: $message,
        );
    }

    private function mapDeliveryTypeFromRemoteType(string $remoteType): ?array
    {
        return match ($remoteType) {
            'home' => ['type' => 'home_delivery', 'delivery_type' => 1],
            'stopdesk' => ['type' => 'desk_delivery', 'delivery_type' => 2],
            'pickup' => ['type' => 'pickup_point', 'delivery_type' => 3],
            default => null,
        };
    }

    private function mapRemoteDeliveryType(string $deliveryType): int
    {
        return match ($deliveryType) {
            'desk_delivery' => 2,
            'pickup_point' => 3,
            default => 1,
        };
    }

    private function normalizeListPayload(array $payload): array
    {
        if (Arr::isAssoc($payload)) {
            $items = Arr::get($payload, 'results');
            if (is_array($items)) {
                return $items;
            }

            $items = Arr::get($payload, 'data');
            if (is_array($items)) {
                return $items;
            }
        }

        return $payload;
    }

    private function extractCreateShipmentPayload(array $payload): array
    {
        if (!Arr::isAssoc($payload) && isset($payload[0]) && is_array($payload[0])) {
            return $payload[0];
        }

        return $payload;
    }

    private function extractErrorMessage(mixed $json, string $body): string
    {
        if (is_array($json)) {
            foreach (['message', 'detail', 'error'] as $key) {
                $value = Arr::get($json, $key);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }

            $errors = Arr::get($json, 'errors');
            if (is_array($errors) && !empty($errors)) {
                $first = Arr::first($errors);
                if (is_string($first) && $first !== '') {
                    return $first;
                }
            }
        }

        return $body !== '' ? $body : 'connection_failed';
    }

    private function stringValue(mixed $value): ?string
    {
        return blank($value) ? null : (string) $value;
    }

    private function floatValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
