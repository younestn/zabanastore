<?php

namespace App\DTO\Shipping;

class ShipmentCreateResult
{
    public function __construct(
        public bool $supported = true,
        public bool $success = false,
        public ?string $message = null,
        public ?string $carrierKey = null,
        public ?string $carrierName = null,
        public ?string $deliveryType = null,
        public ?string $trackingNumber = null,
        public ?string $shippingStatus = null,
        public array $payload = [],
        public array $response = [],
        public ?string $errorMessage = null,
        public ?string $remoteOrderId = null,
        public ?string $remoteDisplayId = null,
        public ?float $deliveryPrice = null,
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
            'delivery_type' => $this->deliveryType,
            'tracking_number' => $this->trackingNumber,
            'shipping_status' => $this->shippingStatus,
            'payload' => $this->payload,
            'response' => $this->response,
            'error_message' => $this->errorMessage,
            'remote_order_id' => $this->remoteOrderId,
            'remote_display_id' => $this->remoteDisplayId,
            'delivery_price' => $this->deliveryPrice,
        ];
    }
}
