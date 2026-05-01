<?php

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShipmentCreateResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Models\Order;

interface ShippingCarrierDriverInterface
{
    public function validateCredentials(array $credentials): array;

    public function getAvailableWilayas(array $credentials): array;

    public function getAvailableCommunes(array $credentials, mixed $wilaya): array;

    public function getDesks(array $credentials, mixed $wilaya = null): array;

    public function getRates(array $credentials, array $payload): array;

    public function createShipment(Order $order, array $credentials, array $options = []): ShipmentCreateResult;

    public function trackShipment(string $trackingNumber, array $credentials): ShipmentTrackingResult;

    public function cancelShipment(string $trackingNumber, array $credentials): array;

    public function getLabel(string $trackingNumber, array $credentials): mixed;
}
