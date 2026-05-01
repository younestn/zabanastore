<?php

namespace App\Console\Commands;

use App\Services\Shipping\ShippingCarrierManager;
use Illuminate\Console\Command;

class SyncCarrierStatuses extends Command
{
    protected $signature = 'shipping:sync-carrier-statuses';

    protected $description = 'Sync shipping statuses for unfinished third-party carrier shipments.';

    public function handle(ShippingCarrierManager $shippingCarrierManager): int
    {
        $updatedCount = $shippingCarrierManager->syncOpenShipments();

        $this->info("Synced {$updatedCount} shipment(s).");

        return self::SUCCESS;
    }
}
