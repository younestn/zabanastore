<?php

namespace App\Services\Shipping\Drivers;

use App\DTO\Shipping\ShipmentCreateResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Models\Order;
use App\Models\Wilaya;
use Illuminate\Support\Facades\Http;

class NoestDriver extends AbstractShippingCarrierDriver
{
    private const BASE_URL = 'https://app.noest-dz.com/api/public';

    public function validateCredentials(array $credentials): array
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('shipping_credentials_are_required'),
                'data' => [],
            ];
        }

        try {
            $response = Http::timeout((int)config('shipping_carriers.request_timeout', 15))
                ->connectTimeout((int)config('shipping_carriers.connect_timeout', 5))
                ->acceptJson()
                ->withToken($preparedCredentials['api_token'])
                ->get(self::BASE_URL . '/get/wilayas', [
                    'user_guid' => $preparedCredentials['noest_guid'],
                ]);

            $responseData = $response->json();

            return [
                'supported' => true,
                'success' => $response->successful() && is_array($responseData) && count($responseData) > 0,
                'message' => $response->successful() ? translate('NOEST_connection_successful') : translate('unable_to_connect_with_NOEST_please_check_GUID_and_API_token'),
                'data' => is_array($responseData) ? $responseData : [],
            ];
        } catch (\Throwable $exception) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('unable_to_connect_with_NOEST_please_check_GUID_and_API_token'),
                'data' => [],
            ];
        }
    }

    public function getAvailableWilayas(array $credentials): array
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('shipping_credentials_are_required'),
                'data' => [],
            ];
        }

        try {
            $response = Http::timeout((int)config('shipping_carriers.request_timeout', 15))
                ->connectTimeout((int)config('shipping_carriers.connect_timeout', 5))
                ->acceptJson()
                ->withToken($preparedCredentials['api_token'])
                ->get(self::BASE_URL . '/get/wilayas', [
                    'user_guid' => $preparedCredentials['noest_guid'],
                ]);

            $responseData = $response->json();

            return [
                'supported' => true,
                'success' => $response->successful() && is_array($responseData),
                'message' => $response->successful() ? null : translate('failed_to_fetch_shipping_rates'),
                'data' => is_array($responseData) ? array_values($responseData) : [],
            ];
        } catch (\Throwable $exception) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('failed_to_fetch_shipping_rates'),
                'data' => [],
            ];
        }
    }

    public function getAvailableCommunes(array $credentials, mixed $wilaya): array
    {
        return [
            'supported' => false,
            'success' => false,
            'message' => 'missing_api_docs',
            'data' => [],
        ];
    }

    public function getDesks(array $credentials, mixed $wilaya = null): array
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('shipping_credentials_are_required'),
                'data' => [],
            ];
        }

        try {
            $response = Http::timeout((int)config('shipping_carriers.request_timeout', 15))
                ->connectTimeout((int)config('shipping_carriers.connect_timeout', 5))
                ->acceptJson()
                ->withToken($preparedCredentials['api_token'])
                ->get(self::BASE_URL . '/desks');

            $responseData = $response->json();

            if (!$response->successful() || !is_array($responseData)) {
                return [
                    'supported' => true,
                    'success' => false,
                    'message' => translate('failed_to_fetch_NOEST_stations'),
                    'data' => [],
                ];
            }

            $wilayaCode = null;
            if (!is_null($wilaya)) {
                if (is_numeric($wilaya)) {
                    $wilayaModel = Wilaya::find((int)$wilaya);
                    $wilayaCode = $wilayaModel?->code;
                } elseif (is_array($wilaya)) {
                    $wilayaCode = $wilaya['code'] ?? $wilaya['wilaya_code'] ?? null;
                }
            }

            $normalizedWilayaCode = $wilayaCode ? (int)ltrim((string)$wilayaCode, '0') : null;

            $desks = collect($responseData)
                ->map(function ($desk, $key) {
                    return [
                        'key' => (string)$key,
                        'code' => (string)($desk['code'] ?? ''),
                        'name' => (string)($desk['name'] ?? ''),
                        'address' => (string)($desk['address'] ?? ''),
                        'phones' => $desk['phones'] ?? [],
                        'email' => (string)($desk['email'] ?? ''),
                    ];
                })
                ->filter(function (array $desk) use ($normalizedWilayaCode) {
                    if (is_null($normalizedWilayaCode)) {
                        return true;
                    }

                    $stationKeyDigits = (int)preg_replace('/\D/', '', (string)$desk['key']);
                    $stationCodeDigits = (int)preg_replace('/\D/', '', (string)$desk['code']);

                    return $stationKeyDigits === $normalizedWilayaCode || $stationCodeDigits === $normalizedWilayaCode;
                })
                ->values()
                ->all();

            return [
                'supported' => true,
                'success' => true,
                'message' => null,
                'data' => $desks,
            ];
        } catch (\Throwable $exception) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('failed_to_fetch_NOEST_stations'),
                'data' => [],
            ];
        }
    }

    public function getRates(array $credentials, array $payload): array
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('shipping_credentials_are_required'),
                'data' => [],
            ];
        }

        $wilayaCode = $payload['wilaya_code'] ?? null;

        if (!$wilayaCode && !empty($payload['wilaya_id'])) {
            $wilayaCode = Wilaya::find((int)$payload['wilaya_id'])?->code;
        }

        if (!$wilayaCode) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('invalid_shipping_location'),
                'data' => [],
            ];
        }

        try {
            $response = Http::timeout((int)config('shipping_carriers.request_timeout', 15))
                ->connectTimeout((int)config('shipping_carriers.connect_timeout', 5))
                ->acceptJson()
                ->withToken($preparedCredentials['api_token'])
                ->get(self::BASE_URL . '/fees', [
                    'user_guid' => $preparedCredentials['noest_guid'],
                ]);

            $responseData = $response->json();
            $normalizedWilayaCode = (int)ltrim((string)$wilayaCode, '0');
            $deliveryTarifs = $responseData['tarifs']['delivery'][$normalizedWilayaCode] ?? null;

            if (!$response->successful() || !$deliveryTarifs) {
                return [
                    'supported' => true,
                    'success' => false,
                    'message' => translate('failed_to_fetch_shipping_rates'),
                    'data' => [],
                ];
            }

            $deskDelivery = [
                'type' => 'desk_delivery',
                'price' => (float)($deliveryTarifs['tarif_stopdesk'] ?? 0),
                'currency' => 'DZD',
                'estimated_delivery' => '1-3_days',
                'desks' => [],
            ];

            $deskResponse = $this->getDesks($credentials, ['code' => $wilayaCode]);
            if ($deskResponse['success'] ?? false) {
                $deskDelivery['desks'] = $deskResponse['data'] ?? [];
            }

            return [
                'supported' => true,
                'success' => true,
                'message' => null,
                'data' => [
                    [
                        'type' => 'home_delivery',
                        'price' => (float)($deliveryTarifs['tarif'] ?? 0),
                        'currency' => 'DZD',
                        'estimated_delivery' => '1-3_days',
                    ],
                    $deskDelivery,
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'supported' => true,
                'success' => false,
                'message' => translate('failed_to_fetch_shipping_rates'),
                'data' => [],
            ];
        }
    }

    public function createShipment(Order $order, array $credentials, array $options = []): ShipmentCreateResult
    {
        $preparedCredentials = $this->prepareCredentials($credentials);
        $payload = $this->buildPayload($order, $preparedCredentials, $options);

        if (!$preparedCredentials['valid'] || !$payload) {
            return new ShipmentCreateResult(
                supported: true,
                success: false,
                message: translate('shipping_credentials_are_required'),
                carrierKey: 'noest',
                carrierName: 'NOEST',
                deliveryType: $options['delivery_type'] ?? null,
                shippingStatus: 'failed',
                payload: $payload ?? [],
                response: [],
                errorMessage: translate('shipping_credentials_are_required'),
            );
        }

        try {
            $response = Http::timeout((int)config('shipping_carriers.request_timeout', 15))
                ->connectTimeout((int)config('shipping_carriers.connect_timeout', 5))
                ->acceptJson()
                ->withToken($preparedCredentials['api_token'])
                ->asForm()
                ->post(self::BASE_URL . '/create/order', $payload);

            $responseData = $response->json() ?? [];
            $trackingNumber = $this->extractTracking($responseData);
            $success = $response->successful() && (($responseData['success'] ?? true) === true);

            return new ShipmentCreateResult(
                supported: true,
                success: $success,
                message: $success ? translate('shipment_created_successfully') : ($responseData['message'] ?? translate('shipment_creation_failed')),
                carrierKey: 'noest',
                carrierName: 'NOEST',
                deliveryType: (($payload['stop_desk'] ?? 0) == 1) ? 'desk_delivery' : 'home_delivery',
                trackingNumber: $trackingNumber,
                shippingStatus: $success ? 'created' : 'failed',
                payload: $payload,
                response: is_array($responseData) ? $responseData : [],
                errorMessage: $success ? null : ($responseData['message'] ?? translate('shipment_creation_failed')),
            );
        } catch (\Throwable $exception) {
            return new ShipmentCreateResult(
                supported: true,
                success: false,
                message: translate('shipment_creation_failed'),
                carrierKey: 'noest',
                carrierName: 'NOEST',
                deliveryType: (($payload['stop_desk'] ?? 0) == 1) ? 'desk_delivery' : 'home_delivery',
                shippingStatus: 'failed',
                payload: $payload,
                response: [],
                errorMessage: $exception->getMessage(),
            );
        }
    }

    public function trackShipment(string $trackingNumber, array $credentials): ShipmentTrackingResult
    {
        return new ShipmentTrackingResult(
            supported: false,
            success: false,
            message: 'missing_tracking_endpoint',
            carrierKey: 'noest',
            carrierName: 'NOEST',
            trackingNumber: $trackingNumber,
            shippingStatus: null,
            response: [],
            errorMessage: 'missing_tracking_endpoint',
        );
    }

    private function prepareCredentials(array $credentials): array
    {
        $noestGuid = trim((string)($credentials['noest_guid'] ?? $credentials['user_guid'] ?? ''));
        $apiToken = trim((string)($credentials['api_token'] ?? ''));

        return [
            'valid' => $noestGuid !== '' && $apiToken !== '',
            'noest_guid' => $noestGuid,
            'api_token' => $apiToken,
        ];
    }

    private function buildPayload(Order $order, array $credentials, array $options = []): ?array
    {
        $shippingAddress = (array)($order->shipping_address_data ?? []);

        $client = trim((string)($shippingAddress['contact_person_name'] ?? ''));
        $phone = $this->normalizePhone((string)($shippingAddress['phone'] ?? ''));
        $address = trim((string)($shippingAddress['address'] ?? ''));
        $wilayaId = (int)ltrim((string)($shippingAddress['noest_wilaya_code'] ?? ''), '0');
        $commune = $this->normalizeNoestCommune((string)($shippingAddress['noest_baladiya_name'] ?? ''));
        $deliveryMethod = (string)($shippingAddress['noest_delivery_method'] ?? $shippingAddress['delivery_type'] ?? 'home_delivery');
        $stationCode = trim((string)($shippingAddress['noest_station_code'] ?? $shippingAddress['desk_code'] ?? ''));

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

        $payload = [
            'api_token' => $credentials['api_token'],
            'user_guid' => $credentials['noest_guid'],
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

    private function normalizeNoestCommune(string $commune): string
    {
        $commune = preg_replace('/\s+/u', ' ', $commune);
        $commune = trim((string)$commune);

        if ($commune === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($commune, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '213')) {
            $phone = '0' . substr($phone, 3);
        }

        return substr($phone, 0, 10);
    }

    private function extractTracking(array $response): ?string
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

        $tracking = null;

        array_walk_recursive($response, function ($value) use (&$tracking) {
            if (!isset($tracking) && is_string($value) && preg_match('/^[A-Z0-9]{1,}-[A-Z0-9-]+$/i', $value)) {
                $tracking = $value;
            }
        });

        return $tracking;
    }
}
