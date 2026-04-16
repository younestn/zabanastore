<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerCommissionInvoice extends Model
{
    protected $fillable = [
        'seller_id',
        'invoice_year',
        'invoice_month',
        'period_start',
        'period_end',
        'commission_type_snapshot',
        'commission_value_snapshot',
        'orders_count',
        'order_commission_total',
        'manual_adjustment_total',
        'total_commission',
        'payment_status',
        'paid_at',
        'paid_by_admin_id',
        'payment_note',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'invoice_year' => 'integer',
        'invoice_month' => 'integer',
        'orders_count' => 'integer',
        'commission_value_snapshot' => 'float',
        'order_commission_total' => 'float',
        'manual_adjustment_total' => 'float',
        'total_commission' => 'float',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SellerCommissionAdjustment::class, 'seller_commission_invoice_id');
    }
}
