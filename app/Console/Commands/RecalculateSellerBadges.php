<?php

namespace App\Console\Commands;

use App\Models\Seller;
use App\Services\SellerBadgeService;
use Illuminate\Console\Command;

class RecalculateSellerBadges extends Command
{
    protected $signature = 'sellers:recalculate-badges {--seller_id= : Recalculate one seller badge}';

    protected $description = 'Recalculate seller badge compliance scores and badge levels.';

    public function handle(SellerBadgeService $sellerBadgeService): int
    {
        $sellerId = $this->option('seller_id');

        if ($sellerId) {
            $seller = Seller::query()->find($sellerId);
            if (!$seller) {
                $this->error('Seller not found.');
                return self::FAILURE;
            }

            $badge = $sellerBadgeService->recalculateSellerBadge($seller);
            $this->info('Seller badge recalculated: ' . ($badge?->badge_key ?? 'none'));

            return self::SUCCESS;
        }

        $processed = 0;

        Seller::query()->chunkById(100, function ($sellers) use (&$processed, $sellerBadgeService) {
            foreach ($sellers as $seller) {
                $sellerBadgeService->recalculateSellerBadge($seller);
                $processed++;
            }
        });

        $this->info("Seller badges recalculated for {$processed} sellers.");

        return self::SUCCESS;
    }
}
