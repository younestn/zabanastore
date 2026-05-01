<?php

namespace App\DTO\Shipping;

class ShipmentTrackingResult
{
    public function __construct(
        public bool $supported = true,
        public bool $success = false,
        public ?string $message = null,
        public ?string $carrierKey = null,
        public ?string $carrierName = null,
        public ?string $trackingNumber = null,
        public ?string $shippingStatus = null,
        public array $events = [],
        public array $response = [],
        public ?string $errorMessage = null,
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
            'tracking_number' => $this->trackingNumber,
            'shipping_status' => $this->shippingStatus,
            'events' => $this->events,
            'response' => $this->response,
            'error_message' => $this->errorMessage,
        ];
    }
}
