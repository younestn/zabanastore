<?php

namespace App\DTO\Shipping;

class ShippingRateResult
{
    public function __construct(
        public bool $supported = true,
        public bool $success = false,
        public ?string $message = null,
        public ?string $carrierKey = null,
        public ?string $carrierName = null,
        public array $deliveryMethods = [],
        public array $meta = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'supported' => $this->supported,
            'success' => $this->success,
            'message' => $this->message,
            'carrier_key' => $this->carrierKey,
            'carrier_name' => $this->carrierName,
            'delivery_methods' => $this->deliveryMethods,
            'meta' => $this->meta,
        ];
    }
}
