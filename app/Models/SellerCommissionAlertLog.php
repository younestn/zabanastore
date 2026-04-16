<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerCommissionAlertLog extends Model
{
    protected $fillable = [
        'seller_id',
        'threshold_amount',
        'unpaid_amount',
        'recipient_type',
        'alert_status',
        'sent_at',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'threshold_amount' => 'float',
        'unpaid_amount' => 'float',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
}
