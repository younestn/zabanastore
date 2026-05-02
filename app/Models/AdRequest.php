<?php

namespace App\Models;

use App\Traits\StorageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AdRequest extends Model
{
    use HasFactory, StorageTrait;

    protected $fillable = [
        'vendor_id',
        'shop_id',
        'product_id',
        'ad_pricing_plan_id',
        'plan_name',
        'plan_price',
        'plan_duration_days',
        'plan_currency',
        'title',
        'description',
        'ad_type',
        'placement',
        'duration_days',
        'price',
        'image_path',
        'payment_receipt',
        'payment_receipt_storage_type',
        'payment_status',
        'notes',
        'status',
        'redirect_type',
        'redirect_id',
        'redirect_url',
        'rejection_reason',
        'admin_note',
        'start_date',
        'end_date',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'priority',
        'is_paid',
        'impressions_web',
        'impressions_app',
        'clicks_web',
        'clicks_app',
        'last_impression_at',
        'last_click_at',
        'completed_purchases_count',
        'completed_purchases_amount',
        'last_purchase_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'plan_price' => 'decimal:2',
        'duration_days' => 'integer',
        'plan_duration_days' => 'integer',
        'redirect_id' => 'integer',
        'shop_id' => 'integer',
        'ad_pricing_plan_id' => 'integer',
        'priority' => 'integer',
        'is_paid' => 'boolean',
        'impressions_web' => 'integer',
        'impressions_app' => 'integer',
        'clicks_web' => 'integer',
        'clicks_app' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'last_impression_at' => 'datetime',
        'last_click_at' => 'datetime',
        'completed_purchases_count' => 'integer',
        'completed_purchases_amount' => 'decimal:2',
        'last_purchase_at' => 'datetime',
    ];

    protected $appends = ['image_url', 'image_full_url', 'payment_receipt_full_url', 'display_status'];

    public function getImageUrlAttribute()
    {
        return $this->image_full_url['path'] ?? ($this->image_path ? asset($this->image_path) : null);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'vendor_id', 'id');
    }

    public function seller(): BelongsTo
    {
        return $this->vendor();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(AdPricingPlan::class, 'ad_pricing_plan_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'rejected_by');
    }

    public function getImageFullUrlAttribute(): array
    {
        $value = $this->normalizeStoredPath($this->image_path);
        $storage = $this->storage->where('key', 'image_path')->first();

        return $this->storageLink('ad-request', $value, $storage['value'] ?? 'public');
    }

    public function getPaymentReceiptFullUrlAttribute(): array
    {
        $value = $this->normalizeStoredPath($this->payment_receipt);
        $storage = $this->storage->where('key', 'payment_receipt')->first();

        return $this->storageLink('ad-request/receipts', $value, $storage['value'] ?? ($this->payment_receipt_storage_type ?? 'public'));
    }

    public function getDisplayStatusAttribute(): string
    {
        if (in_array($this->status, ['pending', 'rejected'], true)) {
            return $this->status;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return 'expired';
        }

        if ($this->start_date && $this->end_date && now()->between($this->start_date, $this->end_date)) {
            return 'active';
        }

        return $this->status ?: 'pending';
    }

    public function getTotalImpressionsAttribute(): int
    {
        return (int) ($this->impressions_web ?? 0) + (int) ($this->impressions_app ?? 0);
    }

    public function getTotalClicksAttribute(): int
    {
        return (int) ($this->clicks_web ?? 0) + (int) ($this->clicks_app ?? 0);
    }

    public function getCtrAttribute(): float
    {
        $impressions = $this->total_impressions;
        if ($impressions <= 0) {
            return 0;
        }

        return round(($this->total_clicks / $impressions) * 100, 2);
    }

    public function isEditableByVendor(): bool
    {
        return in_array($this->status, ['pending', 'rejected'], true);
    }

    protected function normalizeStoredPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return basename(str_replace('\\', '/', $path));
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (self $model) {
            foreach (['image_path', 'payment_receipt'] as $fileKey) {
                if ($model->wasChanged($fileKey) || ($model->wasRecentlyCreated && !empty($model->{$fileKey}))) {
                    DB::table('storages')->updateOrInsert(
                        [
                            'data_type' => get_class($model),
                            'data_id' => $model->id,
                            'key' => $fileKey,
                        ],
                        [
                            'value' => $fileKey === 'payment_receipt'
                                ? ($model->payment_receipt_storage_type ?? (config('filesystems.disks.default') ?? 'public'))
                                : (config('filesystems.disks.default') ?? 'public'),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });
    }
}
