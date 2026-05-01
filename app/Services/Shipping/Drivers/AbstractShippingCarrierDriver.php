<?php

namespace App\Services\Shipping\Drivers;

use App\Contracts\Shipping\ShippingCarrierDriverInterface;
use App\DTO\Shipping\ShipmentCreateResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Models\Order;

abstract class AbstractShippingCarrierDriver implements ShippingCarrierDriverInterface
{
    public function validateCredentials(array $credentials): array
    {
        return $this->unsupportedArrayResult();
    }

    public function getAvailableWilayas(array $credentials): array
    {
        return $this->unsupportedArrayResult();
    }

    public function getAvailableCommunes(array $credentials, mixed $wilaya): array
    {
        return $this->unsupportedArrayResult();
    }

    public function getDesks(array $credentials, mixed $wilaya = null): array
    {
        return $this->unsupportedArrayResult();
    }

    public function getRates(array $credentials, array $payload): array
    {
        return $this->unsupportedArrayResult();
    }

    public function createShipment(Order $order, array $credentials, array $options = []): ShipmentCreateResult
    {
        return new ShipmentCreateResult(
            supported: false,
            success: false,
            message: translate('carrier_feature_not_supported_yet'),
            shippingStatus: 'unsupported',
            errorMessage: translate('carrier_feature_not_supported_yet'),
        );
    }

    public function trackShipment(string $trackingNumber, array $credentials): ShipmentTrackingResult
    {
        return new ShipmentTrackingResult(
            supported: false,
            success: false,
            message: translate('carrier_feature_not_supported_yet'),
            trackingNumber: $trackingNumber,
            shippingStatus: 'unsupported',
            errorMessage: translate('carrier_feature_not_supported_yet'),
        );
    }

    public function cancelShipment(string $trackingNumber, array $credentials): array
    {
        return $this->unsupportedArrayResult();
    }

    public function getLabel(string $trackingNumber, array $credentials): mixed
    {
        return $this->unsupportedArrayResult();
    }

    protected function unsupportedArrayResult(?string $reason = null): array
    {
        return [
            'supported' => false,
            'success' => false,
            'message' => $reason ?? translate('carrier_feature_not_supported_yet'),
            'data' => [],
        ];
    }
}
