<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerCommissionAdjustment extends Model
{
    protected $fillable = [
        'seller_id',
        'seller_commission_invoice_id',
        'adjustment_type',
        'amount',
        'reason',
        'created_by_admin_id',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'seller_commission_invoice_id' => 'integer',
        'amount' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SellerCommissionInvoice::class, 'seller_commission_invoice_id');
    }
}
