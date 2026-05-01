<?php

namespace App\Services\Shipping\Drivers;

class AbstractPlannedCarrierDriver extends AbstractShippingCarrierDriver
{
    public function __construct(
        protected string $carrierKey,
        protected string $carrierName,
        protected string $reason = 'missing_api_docs',
    ) {
    }

    public function validateCredentials(array $credentials): array
    {
        return $this->unsupportedArrayResult('api_driver_not_implemented');
    }

    protected function unsupportedArrayResult(?string $reason = null): array
    {
        return [
            'supported' => false,
            'success' => false,
            'message' => $reason ?? $this->reason,
            'data' => [],
            'carrier_key' => $this->carrierKey,
            'carrier_name' => $this->carrierName,
        ];
    }
}
