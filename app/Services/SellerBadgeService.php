<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Seller;
use App\Models\SellerBadge;
use App\Models\SellerBadgeHistory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class SellerBadgeService
{
    private static array $tableExists = [];
    private static array $columnExists = [];
    private static array $formattedBadgeCache = [];
    private ?array $settings = null;

    public function calculateComplianceScore(Seller $seller): array
    {
        $sellerId = (int)$seller->id;
        $settings = $this->getSettings();
        $weights = $settings['weights'] ?? [];

        $productMetrics = $this->getProductMetrics($sellerId);
        $orderMetrics = $this->getOrderMetrics($sellerId);
        $documentMetrics = $this->getDocumentMetrics($sellerId);

        $averageRating = $this->getAverageRating($productMetrics['product_ids']);
        $accountAgeDays = $seller->created_at ? Carbon::parse($seller->created_at)->diffInDays(now()) : 0;
        $salesVolumeTarget = max((int)($settings['sales_volume_target_orders'] ?? 50), 1);
        $accountAgeTarget = max((int)($settings['account_age_target_days'] ?? 180), 1);

        $weightedScore =
            ($orderMetrics['completion_rate'] * (($weights['completion_rate'] ?? 30) / 100)) +
            ((100 - $orderMetrics['cancellation_rate']) * (($weights['low_cancellation_rate'] ?? 20) / 100)) +
            ((100 - $orderMetrics['delay_rate']) * (($weights['low_delay_rate'] ?? 15) / 100)) +
            (($averageRating / 5 * 100) * (($weights['average_rating'] ?? 15) / 100)) +
            (min($orderMetrics['completed_orders'] / $salesVolumeTarget * 100, 100) * (($weights['sales_volume'] ?? 10) / 100)) +
            (min($accountAgeDays / $accountAgeTarget * 100, 100) * (($weights['account_age'] ?? 5) / 100)) +
            ($documentMetrics['score'] * (($weights['document_verification'] ?? 5) / 100));

        return [
            'score' => round(max(min($weightedScore, 100), 0), 2),
            'total_orders' => $orderMetrics['total_orders'],
            'completed_orders' => $orderMetrics['completed_orders'],
            'cancelled_orders' => $orderMetrics['cancelled_orders'],
            'delayed_orders' => $orderMetrics['delayed_orders'],
            'completion_rate' => $orderMetrics['completion_rate'],
            'cancellation_rate' => $orderMetrics['cancellation_rate'],
            'delay_rate' => $orderMetrics['delay_rate'],
            'average_rating' => round($averageRating, 2),
            'total_sales' => $orderMetrics['total_sales'],
            'net_profit' => $orderMetrics['net_profit'],
            'account_age_days' => $accountAgeDays,
            'seller_joined_at' => $seller->created_at,
            'product_count' => $productMetrics['product_count'],
            'published_products' => $productMetrics['published_products'],
            'document_score' => $documentMetrics['score'],
            'document_verified' => $documentMetrics['verified'],
            'documents' => $documentMetrics['documents'],
        ];
    }

    public function determineAutoBadge(Seller $seller, ?array $metrics = null): ?string
    {
        $metrics ??= $this->calculateComplianceScore($seller);
        $settings = $this->getSettings();

        if ((int)$metrics['published_products'] <= 0) {
            return null;
        }

        $newSellerRules = $settings['new_seller'] ?? [];
        if (
            $metrics['published_products'] >= (int)($newSellerRules['min_published_products'] ?? 1) &&
            $metrics['total_orders'] <= (int)($newSellerRules['max_orders_before_scoring'] ?? 2)
        ) {
            return 'new_seller';
        }

        $thresholds = $settings['thresholds'] ?? [];

        if ($this->isEligibleForEliteSeller($metrics, $thresholds['elite_seller'] ?? [])) {
            return 'elite_seller';
        }

        if ($this->isEligibleForTrustedSeller($metrics, $thresholds['trusted_seller'] ?? [])) {
            return 'trusted_seller';
        }

        if ($this->isEligibleForVerifiedSeller($metrics, $thresholds['verified_seller'] ?? [])) {
            return 'verified_seller';
        }

        if ($metrics['score'] >= (float)($thresholds['rising_seller']['score'] ?? 50)) {
            return 'rising_seller';
        }

        return null;
    }

    public function getCurrentBadge(Seller $seller): ?SellerBadge
    {
        $storedBadge = $this->getStoredBadge((int)$seller->id);
        if ($storedBadge) {
            return $storedBadge;
        }

        $metrics = $this->calculateComplianceScore($seller);
        $autoBadgeKey = $this->determineAutoBadge($seller, $metrics);

        if (!$autoBadgeKey) {
            return null;
        }

        return $this->makeTransientBadge((int)$seller->id, $autoBadgeKey, $metrics);
    }

    public function recalculateSellerBadge(Seller $seller): ?SellerBadge
    {
        $metrics = $this->calculateComplianceScore($seller);
        $autoBadgeKey = $this->determineAutoBadge($seller, $metrics);

        if (!$this->hasTable('seller_badges')) {
            return $autoBadgeKey ? $this->makeTransientBadge((int)$seller->id, $autoBadgeKey, $metrics) : null;
        }

        $badge = SellerBadge::query()->firstOrNew(['seller_id' => (int)$seller->id]);
        $oldBadgeKey = $badge->badge_key;
        $currentBadgeKey = $badge->manual_override && $badge->manual_badge_key ? $badge->manual_badge_key : $autoBadgeKey;

        $badge->fill([
            'auto_badge_key' => $autoBadgeKey,
            'badge_key' => $currentBadgeKey,
            'compliance_score' => $metrics['score'],
            'badge_level' => $this->getBadgeLevel($currentBadgeKey),
            'recalculated_at' => now(),
        ]);
        $badge->save();

        if ($oldBadgeKey !== $badge->badge_key) {
            $this->recordHistory((int)$seller->id, $oldBadgeKey, $badge->badge_key, 'automatic');
        }

        $this->clearFormattedCache((int)$seller->id);

        return $badge;
    }

    public function applyManualBadge(Seller $seller, string $badgeKey, string $reason): SellerBadge
    {
        $badgeKey = trim($badgeKey);
        $reason = trim($reason);

        if (!$this->isValidBadgeKey($badgeKey)) {
            throw new InvalidArgumentException('Invalid seller badge key.');
        }

        if ($reason === '') {
            throw new InvalidArgumentException('Manual override reason is required.');
        }

        if (!$this->hasTable('seller_badges')) {
            throw new InvalidArgumentException('Seller badge table is not available.');
        }

        $metrics = $this->calculateComplianceScore($seller);
        $autoBadgeKey = $this->determineAutoBadge($seller, $metrics);
        $badge = SellerBadge::query()->firstOrNew(['seller_id' => (int)$seller->id]);
        $oldBadgeKey = $badge->badge_key;

        $badge->fill([
            'badge_key' => $badgeKey,
            'auto_badge_key' => $autoBadgeKey,
            'manual_badge_key' => $badgeKey,
            'manual_override' => true,
            'manual_override_reason' => $reason,
            'compliance_score' => $metrics['score'],
            'badge_level' => $this->getBadgeLevel($badgeKey),
            'recalculated_at' => now(),
        ]);
        $badge->save();

        $this->recordHistory((int)$seller->id, $oldBadgeKey, $badgeKey, 'manual', $reason, $this->getCurrentAdminId());
        $this->clearFormattedCache((int)$seller->id);

        return $badge;
    }

    public function restoreAutomaticBadge(Seller $seller): ?SellerBadge
    {
        $metrics = $this->calculateComplianceScore($seller);
        $autoBadgeKey = $this->determineAutoBadge($seller, $metrics);

        if (!$this->hasTable('seller_badges')) {
            return $autoBadgeKey ? $this->makeTransientBadge((int)$seller->id, $autoBadgeKey, $metrics) : null;
        }

        $badge = SellerBadge::query()->firstOrNew(['seller_id' => (int)$seller->id]);
        $oldBadgeKey = $badge->badge_key;

        $badge->fill([
            'badge_key' => $autoBadgeKey,
            'auto_badge_key' => $autoBadgeKey,
            'manual_badge_key' => null,
            'manual_override' => false,
            'manual_override_reason' => null,
            'compliance_score' => $metrics['score'],
            'badge_level' => $this->getBadgeLevel($autoBadgeKey),
            'recalculated_at' => now(),
        ]);
        $badge->save();

        $this->recordHistory(
            (int)$seller->id,
            $oldBadgeKey,
            $badge->badge_key,
            'manual',
            'restore_automatic_badge',
            $this->getCurrentAdminId()
        );
        $this->clearFormattedCache((int)$seller->id);

        return $badge;
    }

    public function getFormattedBadgeForSeller(Seller $seller): ?array
    {
        $sellerId = (int)$seller->id;

        if (array_key_exists($sellerId, self::$formattedBadgeCache)) {
            return self::$formattedBadgeCache[$sellerId];
        }

        self::$formattedBadgeCache[$sellerId] = $this->formatBadge($this->getCurrentBadge($seller));

        return self::$formattedBadgeCache[$sellerId];
    }

    public function getFormattedBadgeForSellerId(?int $sellerId): ?array
    {
        if (!$sellerId || $sellerId <= 0 || !$this->hasTable('sellers')) {
            return null;
        }

        if (array_key_exists($sellerId, self::$formattedBadgeCache)) {
            return self::$formattedBadgeCache[$sellerId];
        }

        $seller = Seller::query()->find($sellerId);
        if (!$seller) {
            self::$formattedBadgeCache[$sellerId] = null;
            return null;
        }

        return $this->getFormattedBadgeForSeller($seller);
    }

    public function formatBadge(?SellerBadge $badge): ?array
    {
        if (!$badge || !$badge->badge_key) {
            return null;
        }

        return $this->formatBadgeKey(
            badgeKey: $badge->badge_key,
            complianceScore: (float)$badge->compliance_score,
            isManual: (bool)$badge->manual_override
        );
    }

    public function formatBadgeKey(?string $badgeKey, float $complianceScore = 0, bool $isManual = false): ?array
    {
        if (!$badgeKey || !$this->isValidBadgeKey($badgeKey)) {
            return null;
        }

        $definition = $this->getBadgeDefinitions()[$badgeKey];

        return [
            'key' => $badgeKey,
            'name' => $this->translate($definition['translation_key']),
            'icon' => $definition['icon'],
            'color' => $definition['color'],
            'level' => (int)$definition['level'],
            'compliance_score' => round($complianceScore, 2),
            'is_manual' => $isManual,
        ];
    }

    public function getBadgeDefinitions(): array
    {
        return $this->getSettings()['badges'] ?? [];
    }

    public function getEvaluationData(Seller $seller): array
    {
        $metrics = $this->calculateComplianceScore($seller);
        $autoBadgeKey = $this->determineAutoBadge($seller, $metrics);
        $currentBadge = $this->getCurrentBadge($seller);

        return [
            'seller' => $seller,
            'metrics' => $metrics,
            'current_badge' => $this->formatBadge($currentBadge),
            'automatic_badge' => $this->formatBadgeKey($autoBadgeKey, $metrics['score']),
            'manual_override' => (bool)($currentBadge?->manual_override ?? false),
            'manual_override_reason' => $currentBadge?->manual_override_reason,
            'badge_options' => $this->getFormattedBadgeOptions($metrics['score']),
            'eligibility_details' => $this->getEligibilityDetails($metrics),
            'histories' => $this->getBadgeHistories((int)$seller->id),
        ];
    }

    private function getProductMetrics(int $sellerId): array
    {
        if (!$this->hasTable('products')) {
            return ['product_count' => 0, 'published_products' => 0, 'product_ids' => []];
        }

        $baseQuery = DB::table('products')->where('user_id', $sellerId);
        if ($this->hasColumn('products', 'added_by')) {
            $baseQuery->where('added_by', 'seller');
        }

        $publishedQuery = clone $baseQuery;
        if ($this->hasColumn('products', 'status')) {
            $publishedQuery->where('status', 1);
        }
        if ($this->hasColumn('products', 'request_status')) {
            $publishedQuery->where('request_status', 1);
        }

        return [
            'product_count' => (clone $baseQuery)->count(),
            'published_products' => (clone $publishedQuery)->count(),
            'product_ids' => (clone $baseQuery)->pluck('id')->all(),
        ];
    }

    private function getOrderMetrics(int $sellerId): array
    {
        if (!$this->hasTable('orders')) {
            return [
                'total_orders' => 0,
                'completed_orders' => 0,
                'cancelled_orders' => 0,
                'delayed_orders' => 0,
                'completion_rate' => 0,
                'cancellation_rate' => 0,
                'delay_rate' => 0,
                'total_sales' => 0,
                'net_profit' => 0,
            ];
        }

        $settings = $this->getSettings();
        $completedStatus = $settings['completed_status'] ?? 'delivered';
        $cancelledStatuses = $settings['cancelled_statuses'] ?? ['canceled', 'failed', 'returned'];

        $baseQuery = $this->getSellerOrdersQuery($sellerId);
        $completedQuery = (clone $baseQuery)->where('order_status', $completedStatus);

        $totalOrders = (clone $baseQuery)->count();
        $completedOrders = (clone $completedQuery)->count();
        $cancelledOrders = (clone $baseQuery)->whereIn('order_status', $cancelledStatuses)->count();
        $delayedOrders = $this->getDelayedOrdersCount(clone $completedQuery);
        $totalSales = $this->getTotalSales(clone $completedQuery);

        return [
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'delayed_orders' => $delayedOrders,
            'completion_rate' => $this->getRate($completedOrders, $totalOrders),
            'cancellation_rate' => $this->getRate($cancelledOrders, $totalOrders),
            'delay_rate' => $this->getRate($delayedOrders, $completedOrders),
            'total_sales' => round($totalSales, 2),
            'net_profit' => round($this->getNetProfit($sellerId, $totalSales), 2),
        ];
    }

    private function getSellerOrdersQuery(int $sellerId): Builder
    {
        $query = DB::table('orders')->where('seller_id', $sellerId);

        if ($this->hasColumn('orders', 'seller_is')) {
            $query->where('seller_is', 'seller');
        }

        if ($this->hasColumn('orders', 'order_type')) {
            $query->where('order_type', 'default_type');
        }

        return $query;
    }

    private function getDelayedOrdersCount(Builder $completedQuery): int
    {
        if (!$this->hasColumn('orders', 'expected_delivery_date') || !$this->hasColumn('orders', 'updated_at')) {
            return 0;
        }

        return (int)$completedQuery
            ->whereNotNull('expected_delivery_date')
            ->whereRaw('DATE(updated_at) > DATE(expected_delivery_date)')
            ->count();
    }

    private function getTotalSales(Builder $completedQuery): float
    {
        if (!$this->hasColumn('orders', 'order_amount')) {
            return 0;
        }

        return (float)$completedQuery->sum('order_amount');
    }

    private function getNetProfit(int $sellerId, float $fallback): float
    {
        if (
            !$this->hasTable('order_transactions') ||
            !$this->hasColumn('order_transactions', 'seller_id') ||
            !$this->hasColumn('order_transactions', 'seller_amount')
        ) {
            return $fallback;
        }

        $query = DB::table('order_transactions')->where('seller_id', $sellerId);
        if ($this->hasColumn('order_transactions', 'seller_is')) {
            $query->where('seller_is', 'seller');
        }

        $netProfit = (float)$query->sum('seller_amount');

        return $netProfit > 0 ? $netProfit : $fallback;
    }

    private function getAverageRating(array $productIds): float
    {
        if (!$this->hasTable('reviews') || empty($productIds) || !$this->hasColumn('reviews', 'rating')) {
            return 0;
        }

        $query = DB::table('reviews')->whereIn('product_id', $productIds);
        if ($this->hasColumn('reviews', 'status')) {
            $query->where('status', 1);
        }
        if ($this->hasColumn('reviews', 'delivery_man_id')) {
            $query->whereNull('delivery_man_id');
        }

        return (float)($query->avg('rating') ?? 0);
    }

    private function getDocumentMetrics(int $sellerId): array
    {
        $documents = [
            'status' => 'missing',
            'tax_identification_number' => null,
            'tin_certificate' => null,
            'tin_expire_date' => null,
        ];

        if (!$this->hasTable('shops')) {
            return ['score' => 0, 'verified' => false, 'documents' => $documents];
        }

        $columns = ['id'];
        foreach (['tax_identification_number', 'tin_certificate', 'tin_expire_date'] as $column) {
            if ($this->hasColumn('shops', $column)) {
                $columns[] = $column;
            }
        }

        $shop = DB::table('shops')->where('seller_id', $sellerId)->first($columns);
        if (!$shop) {
            return ['score' => 0, 'verified' => false, 'documents' => $documents];
        }

        $tinNumber = data_get($shop, 'tax_identification_number');
        $tinCertificate = data_get($shop, 'tin_certificate');
        $tinExpireDate = data_get($shop, 'tin_expire_date');
        $isExpired = false;

        if ($tinExpireDate) {
            try {
                $isExpired = Carbon::parse($tinExpireDate)->isPast();
            } catch (Throwable) {
                $isExpired = false;
            }
        }

        $hasTinNumber = filled($tinNumber);
        $hasTinCertificate = filled($tinCertificate);
        $score = 0;
        $status = 'missing';

        if ($hasTinNumber || $hasTinCertificate) {
            $score = 50;
            $status = 'uploaded';
        }

        if ($hasTinNumber && $hasTinCertificate && !$isExpired) {
            $score = 100;
            $status = 'valid';
        }

        if ($isExpired) {
            $score = min($score, 50);
            $status = 'expired';
        }

        $documents = [
            'status' => $status,
            'tax_identification_number' => $tinNumber,
            'tin_certificate' => $tinCertificate,
            'tin_expire_date' => $tinExpireDate,
        ];

        return ['score' => $score, 'verified' => $score >= 100, 'documents' => $documents];
    }

    private function isEligibleForVerifiedSeller(array $metrics, array $threshold): bool
    {
        if ($metrics['score'] < (float)($threshold['score'] ?? 65)) {
            return false;
        }

        return !$this->requiresDocuments($threshold) || $metrics['document_verified'];
    }

    private function isEligibleForTrustedSeller(array $metrics, array $threshold): bool
    {
        if (!$this->isEligibleForVerifiedSeller($metrics, $threshold)) {
            return false;
        }

        return $metrics['cancellation_rate'] <= (float)($threshold['max_cancellation_rate'] ?? 10) &&
            $metrics['average_rating'] >= (float)($threshold['min_average_rating'] ?? 4);
    }

    private function isEligibleForEliteSeller(array $metrics, array $threshold): bool
    {
        if (!$this->isEligibleForVerifiedSeller($metrics, $threshold)) {
            return false;
        }

        return $metrics['completed_orders'] >= (int)($threshold['min_completed_orders'] ?? 50) &&
            $metrics['delay_rate'] <= (float)($threshold['max_delay_rate'] ?? 5) &&
            $metrics['average_rating'] >= (float)($threshold['min_average_rating'] ?? 4.7);
    }

    private function requiresDocuments(array $threshold): bool
    {
        return (bool)($threshold['requires_documents'] ?? false);
    }

    private function getEligibilityDetails(array $metrics): array
    {
        $thresholds = $this->getSettings()['thresholds'] ?? [];
        $newSellerRules = $this->getSettings()['new_seller'] ?? [];

        return [
            [
                'badge_key' => 'new_seller',
                'badge_name' => $this->translate('new_seller'),
                'eligible' => $metrics['published_products'] >= (int)($newSellerRules['min_published_products'] ?? 1) &&
                    $metrics['total_orders'] <= (int)($newSellerRules['max_orders_before_scoring'] ?? 2),
                'reason' => $this->translate('first_product_published_with_limited_order_data'),
            ],
            [
                'badge_key' => 'rising_seller',
                'badge_name' => $this->translate('rising_seller'),
                'eligible' => $metrics['score'] >= (float)($thresholds['rising_seller']['score'] ?? 50),
                'reason' => $this->translate('minimum_compliance_score') . ': ' . ($thresholds['rising_seller']['score'] ?? 50),
            ],
            [
                'badge_key' => 'verified_seller',
                'badge_name' => $this->translate('verified_seller'),
                'eligible' => $this->isEligibleForVerifiedSeller($metrics, $thresholds['verified_seller'] ?? []),
                'reason' => $this->translate('minimum_compliance_score') . ': ' . ($thresholds['verified_seller']['score'] ?? 65),
            ],
            [
                'badge_key' => 'trusted_seller',
                'badge_name' => $this->translate('trusted_seller'),
                'eligible' => $this->isEligibleForTrustedSeller($metrics, $thresholds['trusted_seller'] ?? []),
                'reason' => $this->translate('low_cancellation_and_good_rating_required'),
            ],
            [
                'badge_key' => 'elite_seller',
                'badge_name' => $this->translate('elite_seller'),
                'eligible' => $this->isEligibleForEliteSeller($metrics, $thresholds['elite_seller'] ?? []),
                'reason' => $this->translate('strong_sales_low_delay_excellent_rating_required'),
            ],
        ];
    }

    private function getFormattedBadgeOptions(float $score): array
    {
        $options = [];
        foreach (array_keys($this->getBadgeDefinitions()) as $badgeKey) {
            $options[$badgeKey] = $this->formatBadgeKey($badgeKey, $score);
        }

        return $options;
    }

    private function getBadgeHistories(int $sellerId)
    {
        if (!$this->hasTable('seller_badge_histories')) {
            return collect();
        }

        return SellerBadgeHistory::query()
            ->where('seller_id', $sellerId)
            ->latest('created_at')
            ->limit(20)
            ->get();
    }

    private function getStoredBadge(int $sellerId): ?SellerBadge
    {
        if (!$this->hasTable('seller_badges')) {
            return null;
        }

        return SellerBadge::query()->where('seller_id', $sellerId)->first();
    }

    private function makeTransientBadge(int $sellerId, string $badgeKey, array $metrics): SellerBadge
    {
        return new SellerBadge([
            'seller_id' => $sellerId,
            'badge_key' => $badgeKey,
            'auto_badge_key' => $badgeKey,
            'manual_override' => false,
            'compliance_score' => $metrics['score'],
            'badge_level' => $this->getBadgeLevel($badgeKey),
            'recalculated_at' => now(),
        ]);
    }

    private function recordHistory(
        int $sellerId,
        ?string $oldBadgeKey,
        ?string $newBadgeKey,
        string $changeType,
        ?string $reason = null,
        ?int $changedBy = null
    ): void {
        if (!$this->hasTable('seller_badge_histories')) {
            return;
        }

        SellerBadgeHistory::query()->create([
            'seller_id' => $sellerId,
            'old_badge_key' => $oldBadgeKey,
            'new_badge_key' => $newBadgeKey,
            'changed_by' => $changedBy,
            'change_type' => $changeType,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function getRate(int|float $value, int|float $total): float
    {
        if ($total <= 0) {
            return 0;
        }

        return round(($value / $total) * 100, 2);
    }

    private function getBadgeLevel(?string $badgeKey): ?int
    {
        if (!$badgeKey || !$this->isValidBadgeKey($badgeKey)) {
            return null;
        }

        return (int)$this->getBadgeDefinitions()[$badgeKey]['level'];
    }

    private function isValidBadgeKey(string $badgeKey): bool
    {
        return array_key_exists($badgeKey, $this->getBadgeDefinitions());
    }

    private function getSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $settings = config('seller_badges', []);
        $businessSettingKey = $settings['business_setting_key'] ?? 'seller_badge_settings';

        if ($this->hasTable('business_settings')) {
            try {
                $storedSettings = BusinessSetting::query()->where('type', $businessSettingKey)->value('value');
                $decodedSettings = is_string($storedSettings) ? json_decode($storedSettings, true) : null;
                if (is_array($decodedSettings)) {
                    $settings = array_replace_recursive($settings, $decodedSettings);
                }
            } catch (Throwable) {
            }
        }

        $this->settings = $settings;

        return $this->settings;
    }

    private function hasTable(string $table): bool
    {
        if (!array_key_exists($table, self::$tableExists)) {
            try {
                self::$tableExists[$table] = Schema::hasTable($table);
            } catch (Throwable) {
                self::$tableExists[$table] = false;
            }
        }

        return self::$tableExists[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, self::$columnExists)) {
            self::$columnExists[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }

        return self::$columnExists[$key];
    }

    private function translate(string $key): string
    {
        if (!function_exists('translate')) {
            return str_replace('_', ' ', $key);
        }

        try {
            return translate($key);
        } catch (Throwable) {
            return str_replace('_', ' ', $key);
        }
    }

    private function clearFormattedCache(int $sellerId): void
    {
        unset(self::$formattedBadgeCache[$sellerId]);
    }

    private function getCurrentAdminId(): ?int
    {
        try {
            return auth('admin')->id() ?: auth()->id();
        } catch (Throwable) {
            return null;
        }
    }
}
