<?php

namespace App\Services;

use App\Models\AdRequest;
use App\Models\AdPricingPlan;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdRequestService
{
    public function getPaymentSettings(): array
    {
        $defaults = config('ad_requests.payment_settings', []);
        $settings = [];

        foreach ($defaults as $key => $defaultValue) {
            $value = getWebConfig($key);
            $settings[$key] = $value !== null && $value !== '' ? $value : $defaultValue;
        }

        $settings['ad_default_price'] = (float) ($settings['ad_default_price'] ?? 0);
        $normalizedReceiptRequired = filter_var(
            $settings['ad_receipt_required'] ?? 0,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        $settings['ad_receipt_required'] = $normalizedReceiptRequired === null
            ? (int) ($settings['ad_receipt_required'] ?? 0)
            : (int) $normalizedReceiptRequired;

        return $settings;
    }

    public function updatePaymentSettings(array $data): void
    {
        $currentSettings = $this->getPaymentSettings();
        $settings = [
            'ad_payment_method_name' => trim((string) ($data['ad_payment_method_name'] ?? ($currentSettings['ad_payment_method_name'] ?? ''))),
            'ad_payment_account_name' => trim((string) ($data['ad_payment_account_name'] ?? ($currentSettings['ad_payment_account_name'] ?? ''))),
            'ad_payment_account_number' => trim((string) ($data['ad_payment_account_number'] ?? ($currentSettings['ad_payment_account_number'] ?? ''))),
            'ad_payment_instructions' => trim((string) ($data['ad_payment_instructions'] ?? ($currentSettings['ad_payment_instructions'] ?? ''))),
            'ad_default_price' => (string) ($data['ad_default_price'] ?? ($currentSettings['ad_default_price'] ?? 0)),
            'ad_currency' => trim((string) ($data['ad_currency'] ?? ($currentSettings['ad_currency'] ?? 'DZD'))),
            'ad_receipt_required' => (string) ((int) ($data['ad_receipt_required'] ?? ($currentSettings['ad_receipt_required'] ?? 0))),
        ];

        foreach ($settings as $type => $value) {
            BusinessSetting::updateOrInsert(
                ['type' => $type],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        clearWebConfigCacheKeys();
    }

    public function getPlacements(): array
    {
        return config('ad_requests.placements', []);
    }

    public function getVendorPlacements(): array
    {
        return config('ad_requests.vendor_placements', []);
    }

    public function getAdminOnlyPlacements(): array
    {
        return config('ad_requests.admin_only_placements', []);
    }

    public function getDefaultVendorPlacement(): string
    {
        return (string) config('ad_requests.default_vendor_placement', 'featured_products');
    }

    public function getPricingPlans(bool $onlyActive = false)
    {
        return AdPricingPlan::query()
            ->when($onlyActive, fn ($query) => $query->where('status', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getVendorPricingPlans(?int $selectedPlanId = null)
    {
        return AdPricingPlan::query()
            ->whereIn('placement', array_keys($this->getVendorPlacements()))
            ->where(function ($query) use ($selectedPlanId) {
                $query->where('status', true);

                if ($selectedPlanId) {
                    $query->orWhere('id', $selectedPlanId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getAdminPricingPlacements(?string $currentPlacement = null): array
    {
        $placements = array_merge($this->getVendorPlacements(), $this->getAdminOnlyPlacements());

        if ($currentPlacement && !isset($placements[$currentPlacement])) {
            $legacyPlacement = $this->getPlacements()[$currentPlacement] ?? null;

            if ($legacyPlacement) {
                $placements[$currentPlacement] = $legacyPlacement;
            }
        }

        return $placements;
    }

    public function getPlacementKeys(): array
    {
        return array_keys($this->getPlacements());
    }

    public function getLegacyAdTypes(): array
    {
        return config('ad_requests.legacy_ad_types', []);
    }

    public function resolveLegacyAdType(?string $placement, ?string $legacyType = null): string
    {
        if ($legacyType && in_array($legacyType, $this->getLegacyAdTypes(), true)) {
            return $legacyType;
        }

        $placementConfig = $this->getPlacements()[$placement ?? ''] ?? null;
        return $placementConfig['legacy_ad_type'] ?? 'banner';
    }

    public function createPricingPlan(array $validated): AdPricingPlan
    {
        return AdPricingPlan::query()->create([
            'name' => trim((string) $validated['name']),
            'placement' => (string) $validated['placement'],
            'description' => $validated['description'] ?? null,
            'price' => (float) $validated['price'],
            'duration_days' => (int) $validated['duration_days'],
            'currency' => $validated['currency'] ?? 'DZD',
            'status' => (bool) ($validated['status'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);
    }

    public function updatePricingPlan(AdPricingPlan $plan, array $validated): AdPricingPlan
    {
        $plan->fill([
            'name' => trim((string) ($validated['name'] ?? $plan->name)),
            'placement' => (string) ($validated['placement'] ?? $plan->placement),
            'description' => $validated['description'] ?? $plan->description,
            'price' => isset($validated['price']) ? (float) $validated['price'] : $plan->price,
            'duration_days' => isset($validated['duration_days']) ? (int) $validated['duration_days'] : $plan->duration_days,
            'currency' => $validated['currency'] ?? $plan->currency,
            'status' => array_key_exists('status', $validated) ? (bool) $validated['status'] : $plan->status,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $plan->sort_order,
        ]);
        $plan->save();

        return $plan->fresh();
    }

    public function togglePricingPlanStatus(AdPricingPlan $plan): AdPricingPlan
    {
        $plan->status = !$plan->status;
        $plan->save();

        return $plan->fresh();
    }

    public function deletePricingPlan(AdPricingPlan $plan): bool
    {
        if ($plan->adRequests()->exists()) {
            $plan->status = false;
            $plan->save();

            return false;
        }

        $plan->delete();

        return true;
    }

    public function createVendorAdRequest(Seller $seller, array $validated, ?UploadedFile $adImage, ?UploadedFile $receiptFile): AdRequest
    {
        $pricingPlan = $this->resolveActivePricingPlan((int) $validated['ad_pricing_plan_id']);
        $legacyType = $this->resolveLegacyAdType($pricingPlan->placement, $validated['ad_type'] ?? null);

        $adRequest = new AdRequest();
        $adRequest->fill([
            'vendor_id' => $seller->id,
            'shop_id' => $seller->shop?->id,
            'product_id' => $validated['product_id'] ?? null,
            'ad_pricing_plan_id' => $pricingPlan->id,
            'plan_name' => $pricingPlan->name,
            'plan_price' => $pricingPlan->price,
            'plan_duration_days' => $pricingPlan->duration_days,
            'plan_currency' => $pricingPlan->currency,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'ad_type' => $legacyType,
            'placement' => $pricingPlan->placement,
            'duration_days' => (int) $pricingPlan->duration_days,
            'price' => (float) $pricingPlan->price,
            'redirect_type' => $validated['redirect_type'] ?? null,
            'redirect_id' => $validated['redirect_id'] ?? null,
            'redirect_url' => $validated['redirect_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'payment_status' => $receiptFile ? 'uploaded' : 'pending',
            'is_paid' => false,
            'priority' => 0,
        ]);

        if ($adImage) {
            $adRequest->image_path = $this->storeFile($adImage, 'ad-request');
        }

        if ($receiptFile) {
            $adRequest->payment_receipt = $this->storeFile($receiptFile, 'ad-request/receipts');
            $adRequest->payment_receipt_storage_type = config('filesystems.disks.default', 'public');
        }

        $adRequest->save();

        return $adRequest->fresh(['vendor.shop']);
    }

    public function updateVendorAdRequest(AdRequest $adRequest, array $validated, ?UploadedFile $adImage, ?UploadedFile $receiptFile): AdRequest
    {
        if (!$adRequest->isEditableByVendor()) {
            throw ValidationException::withMessages([
                'ad_request' => translate('ad_request_can_not_be_modified_now'),
            ]);
        }

        $pricingPlan = $this->resolveActivePricingPlan((int) $validated['ad_pricing_plan_id'], $adRequest->ad_pricing_plan_id);
        $legacyType = $this->resolveLegacyAdType($pricingPlan->placement, $validated['ad_type'] ?? $adRequest->ad_type);

        $adRequest->fill([
            'product_id' => $validated['product_id'] ?? null,
            'ad_pricing_plan_id' => $pricingPlan->id,
            'plan_name' => $pricingPlan->name,
            'plan_price' => $pricingPlan->price,
            'plan_duration_days' => $pricingPlan->duration_days,
            'plan_currency' => $pricingPlan->currency,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'ad_type' => $legacyType,
            'placement' => $pricingPlan->placement,
            'duration_days' => (int) $pricingPlan->duration_days,
            'price' => (float) $pricingPlan->price,
            'redirect_type' => $validated['redirect_type'] ?? null,
            'redirect_id' => $validated['redirect_id'] ?? null,
            'redirect_url' => $validated['redirect_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'admin_note' => null,
        ]);

        if ($adImage) {
            $this->deleteStoredFile($adRequest->image_path, 'ad-request');
            $adRequest->image_path = $this->storeFile($adImage, 'ad-request');
        }

        if ($receiptFile) {
            $this->deleteStoredFile($adRequest->payment_receipt, 'ad-request/receipts');
            $adRequest->payment_receipt = $this->storeFile($receiptFile, 'ad-request/receipts');
            $adRequest->payment_receipt_storage_type = config('filesystems.disks.default', 'public');
            $adRequest->payment_status = 'uploaded';
        }

        $adRequest->save();

        return $adRequest->fresh(['vendor.shop']);
    }

    public function updateAdminMetadata(AdRequest $adRequest, array $validated, int $adminId): AdRequest
    {
        $placement = $validated['placement'] ?? $adRequest->placement ?? $this->getDefaultVendorPlacement();

        $adRequest->fill([
            'placement' => $placement,
            'price' => $validated['price'] ?? $adRequest->price,
            'start_date' => !empty($validated['start_date']) ? Carbon::parse($validated['start_date']) : $adRequest->start_date,
            'end_date' => !empty($validated['end_date']) ? Carbon::parse($validated['end_date']) : $adRequest->end_date,
            'priority' => isset($validated['priority']) ? (int) $validated['priority'] : $adRequest->priority,
            'admin_note' => $validated['admin_note'] ?? $adRequest->admin_note,
            'redirect_type' => $validated['redirect_type'] ?? $adRequest->redirect_type,
            'redirect_id' => $validated['redirect_id'] ?? $adRequest->redirect_id,
            'redirect_url' => $validated['redirect_url'] ?? $adRequest->redirect_url,
        ]);

        if (!empty($validated['duration_days'])) {
            $adRequest->duration_days = (int) $validated['duration_days'];
        }

        if ($adRequest->start_date && !$adRequest->end_date) {
            $adRequest->end_date = (clone $adRequest->start_date)->addDays((int) ($adRequest->duration_days ?: 1));
        }

        if ($adRequest->start_date && $adRequest->end_date && ($adRequest->approved_at || in_array($adRequest->status, ['approved', 'active', 'expired'], true))) {
            $adRequest->status = $this->determineApprovalStatus($adRequest->start_date, $adRequest->end_date);
        }

        $adRequest->save();

        return $adRequest->fresh(['vendor.shop']);
    }

    public function approve(AdRequest $adRequest, int $adminId): AdRequest
    {
        $startDate = $adRequest->start_date ?: now();
        $endDate = $adRequest->end_date ?: (clone $startDate)->addDays((int) ($adRequest->duration_days ?: 1));

        if ($endDate->lessThanOrEqualTo($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => translate('The_end_date_must_be_after_the_start_date'),
            ]);
        }

        $adRequest->start_date = $startDate;
        $adRequest->end_date = $endDate;
        $adRequest->approved_at = now();
        $adRequest->approved_by = $adminId;
        $adRequest->rejection_reason = null;
        $adRequest->rejected_at = null;
        $adRequest->rejected_by = null;
        $adRequest->status = $this->determineApprovalStatus($startDate, $endDate);
        $adRequest->save();

        return $adRequest->fresh(['vendor.shop']);
    }

    public function reject(AdRequest $adRequest, string $reason, int $adminId): AdRequest
    {
        $adRequest->status = 'rejected';
        $adRequest->rejection_reason = $reason;
        $adRequest->rejected_at = now();
        $adRequest->rejected_by = $adminId;
        $adRequest->save();

        return $adRequest->fresh(['vendor.shop']);
    }

    public function getActiveAdsQuery(?string $placement = null)
    {
        return AdRequest::query()
            ->with(['vendor.shop', 'shop', 'product'])
            ->whereIn('status', ['approved', 'active'])
            ->whereNotNull('image_path')
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->whereHas('vendor', function ($query) {
                $query->where('status', 'approved');
            })
            ->when($placement, function ($query) use ($placement) {
                $query->where('placement', $placement);
            })
            ->where(function ($query) {
                $query->whereNull('shop_id')
                    ->orWhereHas('shop', function ($shopQuery) {
                        $shopQuery->where('temporary_close', 0);
                    });
            })
            ->orderByDesc('priority')
            ->orderByDesc('approved_at')
            ->orderByDesc('id');
    }

    public function formatActiveAd(AdRequest $adRequest): array
    {
        $placementConfig = $this->getPlacements()[$adRequest->placement ?? ''] ?? [];

        return [
            'id' => $adRequest->id,
            'type' => 'vendor_ad',
            'label' => translate('ad'),
            'title' => $adRequest->title,
            'image_url' => $adRequest->image_full_url['path'] ?? $adRequest->image_url,
            'redirect_type' => $adRequest->redirect_type,
            'redirect_id' => $adRequest->redirect_id,
            'redirect_url' => $this->resolvePublicRedirectUrl($adRequest),
            'seller_id' => $adRequest->vendor_id,
            'shop_id' => $adRequest->shop_id,
            'placement' => $adRequest->placement,
            'placement_label' => translate($placementConfig['label'] ?? ($adRequest->placement ?? 'featured_products')),
            'priority' => (int) ($adRequest->priority ?? 0),
            'start_date' => optional($adRequest->start_date)->toDateTimeString(),
            'end_date' => optional($adRequest->end_date)->toDateTimeString(),
            'impression_url' => route('api.v1.ad-requests.impression', $adRequest->id),
            'click_url' => route('api.v1.ad-requests.click', $adRequest->id),
            'visit_url' => route('web.ad-requests.visit', $adRequest->id),
        ];
    }

    public function getFeaturedProductAds(int $limit = 3)
    {
        return $this->getActiveAdsQuery('featured_products')
            ->limit(max(1, $limit))
            ->get();
    }

    public function trackActiveAdEvent(int $adRequestId, string $source, string $eventType): bool
    {
        $adRequest = AdRequest::query()
            ->whereKey($adRequestId)
            ->whereIn('status', ['approved', 'active'])
            ->whereNotNull('image_path')
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->first();

        if (!$adRequest) {
            return false;
        }

        $counterColumn = $eventType === 'click'
            ? ($source === 'app' ? 'clicks_app' : 'clicks_web')
            : ($source === 'app' ? 'impressions_app' : 'impressions_web');

        $timestampColumn = $eventType === 'click' ? 'last_click_at' : 'last_impression_at';

        $adRequest->increment($counterColumn);
        $adRequest->forceFill([$timestampColumn => now()])->save();

        return true;
    }

    public function getActiveAdForVisit(int $adRequestId): ?AdRequest
    {
        return $this->getActiveAdsQuery()
            ->whereKey($adRequestId)
            ->first();
    }

    public function resolvePublicRedirectUrl(AdRequest $adRequest): ?string
    {
        return match ($this->resolveRedirectType($adRequest)) {
            'url' => filter_var($adRequest->redirect_url, FILTER_VALIDATE_URL) ? $adRequest->redirect_url : null,
            'product' => $this->resolveProductRedirectUrl($adRequest),
            'shop' => $this->resolveShopRedirectUrl($adRequest),
            default => filter_var($adRequest->redirect_url, FILTER_VALIDATE_URL) ? $adRequest->redirect_url : null,
        };
    }

    public function resolveProductAttribution(?int $productId, ?int $adRequestId = null): ?array
    {
        $productId = (int) $productId;
        $adRequestId = $adRequestId ?: (int) (session('ad_attribution.ad_request_id') ?: request()->cookie('ad_request_id'));

        if (!$productId || !$adRequestId) {
            return null;
        }

        $adRequest = AdRequest::query()->find($adRequestId);

        if (!$adRequest || $this->resolveRedirectType($adRequest) !== 'product') {
            return null;
        }

        $targetProductId = $this->resolveProductAttributionId($adRequest);

        if (!$targetProductId || (int) $targetProductId !== $productId) {
            return null;
        }

        return [
            'ad_request_id' => $adRequest->id,
            'ad_attribution_source' => 'web',
        ];
    }

    public function recordCompletedPurchaseFromOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $details = OrderDetail::query()
                ->where('order_id', $order->id)
                ->whereNotNull('ad_request_id')
                ->whereNull('ad_purchase_counted_at')
                ->lockForUpdate()
                ->get();

            foreach ($details as $detail) {
                $this->recordCompletedPurchaseFromOrderDetail($detail);
            }
        });
    }

    public function recordCompletedPurchaseFromOrderDetail(OrderDetail $orderDetail): void
    {
        if (!$orderDetail->ad_request_id || $orderDetail->ad_purchase_counted_at) {
            return;
        }

        DB::transaction(function () use ($orderDetail) {
            $detail = OrderDetail::query()
                ->whereKey($orderDetail->id)
                ->whereNotNull('ad_request_id')
                ->whereNull('ad_purchase_counted_at')
                ->lockForUpdate()
                ->first();

            if (!$detail) {
                return;
            }

            $adRequest = AdRequest::query()
                ->whereKey($detail->ad_request_id)
                ->lockForUpdate()
                ->first();

            if (!$adRequest) {
                return;
            }

            $amount = max(0, ((float) $detail->price * (int) $detail->qty) + (float) $detail->tax - (float) $detail->discount);

            $adRequest->forceFill([
                'completed_purchases_count' => (int) ($adRequest->completed_purchases_count ?? 0) + 1,
                'completed_purchases_amount' => round((float) ($adRequest->completed_purchases_amount ?? 0) + $amount, 2),
                'last_purchase_at' => now(),
            ])->save();

            $detail->forceFill([
                'ad_purchase_counted_at' => now(),
            ])->save();
        });
    }

    public function determineApprovalStatus(?Carbon $startDate, ?Carbon $endDate): string
    {
        if ($endDate && $endDate->isPast()) {
            return 'expired';
        }

        if ($startDate && $startDate->isFuture()) {
            return 'approved';
        }

        if ($startDate && $endDate && now()->between($startDate, $endDate)) {
            return 'active';
        }

        return 'approved';
    }

    private function resolvePrice(?float $explicitPrice, mixed $defaultPrice, string $legacyAdType, int $durationDays): float
    {
        if ($explicitPrice !== null && $explicitPrice > 0) {
            return round($explicitPrice, 2);
        }

        $defaultPrice = (float) $defaultPrice;
        if ($defaultPrice > 0) {
            return round($defaultPrice, 2);
        }

        return $this->calculateLegacyPrice($legacyAdType, $durationDays);
    }

    private function resolveActivePricingPlan(int $planId, ?int $currentPlanId = null): AdPricingPlan
    {
        return AdPricingPlan::query()
            ->where('id', $planId)
            ->whereIn('placement', array_keys($this->getVendorPlacements()))
            ->where(function ($query) use ($currentPlanId) {
                $query->where('status', true);

                if ($currentPlanId) {
                    $query->orWhere('id', $currentPlanId);
                }
            })
            ->firstOr(function () {
                throw ValidationException::withMessages([
                    'ad_pricing_plan_id' => translate('no_active_ad_plans'),
                ]);
            });
    }

    private function resolveProductRedirectUrl(AdRequest $adRequest): ?string
    {
        $product = $adRequest->product;

        if (!$product && $adRequest->redirect_id) {
            $product = Product::query()->find($adRequest->redirect_id);
        }

        if (!$product && $adRequest->product_id) {
            $product = Product::query()->find($adRequest->product_id);
        }

        return $product?->slug
            ? route('product', $product->slug)
            : (filter_var($adRequest->redirect_url, FILTER_VALIDATE_URL) ? $adRequest->redirect_url : null);
    }

    private function resolveShopRedirectUrl(AdRequest $adRequest): ?string
    {
        return $adRequest->shop?->slug
            ? route('shopView', ['slug' => $adRequest->shop->slug])
            : (filter_var($adRequest->redirect_url, FILTER_VALIDATE_URL) ? $adRequest->redirect_url : null);
    }

    private function resolveProductAttributionId(AdRequest $adRequest): ?int
    {
        $productId = $adRequest->redirect_id ?: $adRequest->product_id;

        return $productId ? (int) $productId : null;
    }

    private function resolveRedirectType(AdRequest $adRequest): ?string
    {
        if (!empty($adRequest->redirect_type)) {
            return $adRequest->redirect_type;
        }

        if (($adRequest->ad_type ?? null) === 'product' || $adRequest->product_id || $adRequest->redirect_id) {
            return 'product';
        }

        if ($adRequest->shop_id) {
            return 'shop';
        }

        if (!empty($adRequest->redirect_url)) {
            return 'url';
        }

        return null;
    }

    private function calculateLegacyPrice(string $legacyAdType, int $durationDays): float
    {
        $basePrices = [
            'banner' => 25,
            'sidebar' => 15,
            'product' => 20,
            'popup' => 30,
            'email' => 35,
        ];

        $weeks = max($durationDays, 1) / 7;
        $basePrice = $basePrices[$legacyAdType] ?? 0;
        $totalPrice = round($basePrice * $weeks, 2);

        if ($durationDays >= 30) {
            $totalPrice = round($totalPrice * 0.9, 2);
        } elseif ($durationDays >= 14) {
            $totalPrice = round($totalPrice * 0.95, 2);
        }

        return $totalPrice;
    }

    private function storeFile(UploadedFile $file, string $directory): string
    {
        $filename = now()->format('YmdHis') . '_' . uniqid('', true) . '.' . $file->getClientOriginalExtension();
        return $file->storeAs($directory, $filename, 'public');
    }

    private function deleteStoredFile(?string $path, string $directory): void
    {
        if (!$path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return;
        }

        $legacyPath = trim($directory . '/' . basename($path), '/');
        if (Storage::disk('public')->exists($legacyPath)) {
            Storage::disk('public')->delete($legacyPath);
        }
    }
}
