<?php

namespace App\Services\Shipping\Drivers;

use App\DTO\Shipping\ShipmentCreateResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class AbstractProcolisLikeDriver extends AbstractShippingCarrierDriver
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

        $response = $this->request('get', '/token', $preparedCredentials);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $status = Arr::get($response['data'], 'Statut');
        $isSuccess = is_string($status)
            ? str_contains(Str::lower(Str::ascii($status)), 'active')
                || str_contains(Str::lower(Str::ascii($status)), 'acces active')
                || str_contains(Str::lower(Str::ascii($status)), 'acces active')
            : true;

        return [
            'supported' => true,
            'success' => $isSuccess,
            'message' => $isSuccess ? 'connection_successful' : 'connection_failed',
            'data' => is_array($response['data']) ? $response['data'] : [],
            'carrier_key' => $this->carrierKey(),
            'carrier_name' => $this->carrierName(),
        ];
    }

    public function getAvailableWilayas(array $credentials): array
    {
        return $this->unsupportedArrayResult('carrier_feature_not_supported_yet');
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

        $toWilayaId = (int) ($payload['to_wilaya_id'] ?? $payload['wilaya_id'] ?? 0);
        if ($toWilayaId <= 0) {
            return $this->failedArrayResult('invalid_shipping_location');
        }

        $response = $this->request('post', '/tarification', $preparedCredentials);

        if (!$response['success']) {
            return $this->failedArrayResult($response['message'], $response['data'] ?? []);
        }

        $rateRow = collect($this->normalizeCollection($response['data']))
            ->first(function ($item) use ($toWilayaId) {
                return (int) ($item['IDWilaya'] ?? $item['wilaya_id'] ?? 0) === $toWilayaId;
            });

        if (!is_array($rateRow)) {
            return $this->failedArrayResult('shipping_rate_not_available', is_array($response['data']) ? $response['data'] : []);
        }

        $deliveryMethods = collect([
            [
                'type' => 'home_delivery',
                'price' => $this->extractNumericValue($rateRow, [
                    'TarifDomicile', 'PrixDomicile', 'Domicile', 'domicile',
                    'tarif_domicile', 'prix_domicile', 'domicile_price',
                ]),
            ],
            [
                'type' => 'desk_delivery',
                'price' => $this->extractNumericValue($rateRow, [
                    'TarifStopDesk', 'TarifStopdesk', 'PrixStopDesk', 'PrixStopdesk',
                    'StopDesk', 'Stopdesk', 'stopdesk', 'tarif_stopdesk',
                    'prix_stopdesk', 'stopdesk_price',
                ]),
            ],
        ])
            ->filter(fn ($item) => is_numeric($item['price']))
            ->map(fn ($item) => [
                'carrier_key' => $this->carrierKey(),
                'type' => $item['type'],
                'price' => (float) $item['price'],
                'currency' => 'DZD',
                'estimated_delivery' => '24-72h',
            ])
            ->when(!blank($payload['delivery_type'] ?? null), function ($collection) use ($payload) {
                return $collection->filter(fn ($item) => $item['type'] === $payload['delivery_type']);
            })
            ->values()
            ->all();

        if (empty($deliveryMethods)) {
            return $this->failedArrayResult('shipping_rate_not_available', $rateRow);
        }

        return $this->successArrayResult($deliveryMethods);
    }

    public function createShipment(Order $order, array $credentials, array $options = []): ShipmentCreateResult
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedShipmentResult('shipping_credentials_are_required', $options);
        }

        $payloadData = $this->buildCreateShipmentPayload($order, $options);
        if (!$payloadData['success']) {
            return $this->failedShipmentResult($payloadData['message'], $options, $payloadData['payload'] ?? []);
        }

        $response = $this->request('post', '/add_colis', $preparedCredentials, [
            'Colis' => [$payloadData['payload']],
        ]);

        if (!$response['success']) {
            return $this->failedShipmentResult(
                $response['message'],
                $options,
                $payloadData['payload'],
                is_array($response['data']) ? $response['data'] : []
            );
        }

        $shipment = $this->extractCreatedShipmentData(is_array($response['data']) ? $response['data'] : []);
        $messageRetour = (string) Arr::get($shipment, 'MessageRetour', '');
        $isSuccess = $messageRetour === '' || Str::lower($messageRetour) === 'good';
        $tracking = $this->stringValue(Arr::get($shipment, 'Tracking')) ?? $payloadData['payload']['Tracking'];

        return new ShipmentCreateResult(
            supported: true,
            success: $isSuccess,
            message: $isSuccess ? 'shipment_created_successfully' : ($messageRetour ?: 'shipment_creation_failed'),
            carrierKey: $this->carrierKey(),
            carrierName: $this->carrierName(),
            deliveryType: $options['delivery_type'] ?? 'home_delivery',
            trackingNumber: $tracking,
            shippingStatus: $isSuccess ? 'created' : 'failed',
            payload: $payloadData['payload'],
            response: is_array($response['data']) ? $response['data'] : [],
            errorMessage: $isSuccess ? null : ($messageRetour ?: 'shipment_creation_failed'),
            remoteOrderId: $tracking,
        );
    }

    public function trackShipment(string $trackingNumber, array $credentials): ShipmentTrackingResult
    {
        $preparedCredentials = $this->prepareCredentials($credentials);

        if (!$preparedCredentials['valid']) {
            return $this->failedTrackingResult($trackingNumber, 'shipping_credentials_are_required');
        }

        $response = $this->request('post', '/lire', $preparedCredentials, [
            'Colis' => [
                ['Tracking' => $trackingNumber],
            ],
        ]);

        if (!$response['success']) {
            return $this->failedTrackingResult($trackingNumber, $response['message'], $response['data'] ?? []);
        }

        $shipment = $this->extractTrackedShipmentData(is_array($response['data']) ? $response['data'] : []);
        $remoteStatus = $this->firstNotBlank(
            Arr::get($shipment, 'Statut'),
            Arr::get($shipment, 'status'),
            Arr::get($shipment, 'Status'),
            Arr::get($shipment, 'Etat'),
            Arr::get($shipment, 'Situation')
        );

        return new ShipmentTrackingResult(
            supported: true,
            success: true,
            message: 'connection_successful',
            carrierKey: $this->carrierKey(),
            carrierName: $this->carrierName(),
            trackingNumber: $trackingNumber,
            shippingStatus: $this->mapRemoteStatusToShippingStatus($remoteStatus),
            response: is_array($response['data']) ? $response['data'] : [],
            errorMessage: null,
        );
    }

    public function cancelShipment(string $trackingNumber, array $credentials): array
    {
        return $this->unsupportedArrayResult('carrier_feature_not_supported_yet');
    }

    public function getLabel(string $trackingNumber, array $credentials): mixed
    {
        return $this->unsupportedArrayResult('carrier_feature_not_supported_yet');
    }

    protected function request(string $method, string $uri, array $credentials, array $payload = []): array
    {
        try {
            $request = Http::timeout((int) config('shipping_carriers.request_timeout', 15))
                ->connectTimeout((int) config('shipping_carriers.connect_timeout', 5))
                ->acceptJson()
                ->withHeaders([
                    'token' => $credentials['token'],
                    'key' => $credentials['key'],
                    'Content-Type' => 'application/json',
                ]);

            $response = match (Str::lower($method)) {
                'post' => $request->post($this->baseUrl() . $uri, $payload),
                default => $request->get($this->baseUrl() . $uri),
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
        $token = trim((string) ($credentials['token'] ?? $credentials['api_token'] ?? ''));
        $key = trim((string) ($credentials['key'] ?? $credentials['api_secret'] ?? ''));

        return [
            'valid' => $token !== '' && $key !== '',
            'token' => $token,
            'key' => $key,
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
        if (!Arr::isAssoc($payload)) {
            return $payload;
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return [$payload];
    }

    protected function extractCreatedShipmentData(array $payload): array
    {
        if (isset($payload['Colis'][0]) && is_array($payload['Colis'][0])) {
            return $payload['Colis'][0];
        }

        if (!Arr::isAssoc($payload) && isset($payload[0]) && is_array($payload[0])) {
            return $payload[0];
        }

        return $payload;
    }

    protected function extractTrackedShipmentData(array $payload): array
    {
        if (isset($payload['Colis'][0]) && is_array($payload['Colis'][0])) {
            return $payload['Colis'][0];
        }

        return $payload;
    }

    protected function buildCreateShipmentPayload(Order $order, array $options): array
    {
        $shippingAddress = (array) ($order->shipping_address_data ?? []);
        $deliveryType = (string) ($options['delivery_type'] ?? $shippingAddress['delivery_type'] ?? 'home_delivery');
        $destinationWilayaId = (int) ($options['wilaya_id'] ?? $shippingAddress['wilaya_id'] ?? 0);
        $destinationCommune = $this->firstNotBlank(
            $shippingAddress['commune_name'] ?? null,
            $shippingAddress['commune'] ?? null,
            $shippingAddress['baladiya_name'] ?? null,
            $shippingAddress['city'] ?? null,
            $options['commune_name'] ?? null
        );

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
            blank((string) ($shippingAddress['contact_person_name'] ?? ''))
            || blank((string) ($shippingAddress['phone'] ?? ''))
            || blank((string) ($shippingAddress['address'] ?? ''))
            || $destinationWilayaId <= 0
            || blank($destinationCommune)
            || blank($productList)
        ) {
            return [
                'success' => false,
                'message' => 'invalid_shipping_location',
                'payload' => [],
            ];
        }

        $payload = [
            'Tracking' => (string) ($order->order_group_id ?: $order->id),
            'TypeLivraison' => $deliveryType === 'desk_delivery' ? 1 : 0,
            'TypeColis' => 0,
            'Confrimee' => 1,
            'Client' => (string) ($shippingAddress['contact_person_name'] ?? ''),
            'MobileA' => preg_replace('/\s+/', '', (string) ($shippingAddress['phone'] ?? '')),
            'MobileB' => null,
            'Adresse' => (string) ($shippingAddress['address'] ?? ''),
            'IDWilaya' => $destinationWilayaId,
            'Commune' => (string) $destinationCommune,
            'Total' => (float) ($order->order_amount ?? 0),
            'Note' => blank($order->order_note) ? null : (string) $order->order_note,
            'TProduit' => $productList,
            'id_Externe' => (string) ($order->order_group_id ?: $order->id),
            'Source' => 'Zabana Store',
        ];

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
            $normalizedStatus === '' => 'processing',
            str_contains($normalizedStatus, 'livr') || str_contains($normalizedStatus, 'deliv') => 'delivered',
            str_contains($normalizedStatus, 'retour') || str_contains($normalizedStatus, 'return') => 'returned',
            str_contains($normalizedStatus, 'annul') || str_contains($normalizedStatus, 'cancel') => 'canceled',
            str_contains($normalizedStatus, 'echec') || str_contains($normalizedStatus, 'failed') || str_contains($normalizedStatus, 'refus') => 'failed',
            str_contains($normalizedStatus, 'transit') || str_contains($normalizedStatus, 'exped') || str_contains($normalizedStatus, 'cours') => 'out_for_delivery',
            default => 'processing',
        };
    }

    protected function extractErrorMessage(mixed $json, string $body): string
    {
        if (is_array($json)) {
            foreach (['message', 'detail', 'error', 'Statut'] as $key) {
                $value = Arr::get($json, $key);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return $body !== '' ? $body : 'connection_failed';
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

    protected function stringValue(mixed $value): ?string
    {
        return blank($value) ? null : (string) $value;
    }
}
