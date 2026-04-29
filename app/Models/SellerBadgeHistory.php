<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerBadgeHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'seller_id',
        'old_badge_key',
        'new_badge_key',
        'changed_by',
        'change_type',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'changed_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
}
