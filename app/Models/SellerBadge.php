<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $seller_id
 * @property string|null $badge_key
 * @property string|null $auto_badge_key
 * @property string|null $manual_badge_key
 * @property bool $manual_override
 * @property string|null $manual_override_reason
 * @property float $compliance_score
 * @property int|null $badge_level
 * @property Carbon|null $recalculated_at
 */
class SellerBadge extends Model
{
    protected $fillable = [
        'seller_id',
        'badge_key',
        'auto_badge_key',
        'manual_badge_key',
        'manual_override',
        'manual_override_reason',
        'compliance_score',
        'badge_level',
        'recalculated_at',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'manual_override' => 'boolean',
        'compliance_score' => 'float',
        'badge_level' => 'integer',
        'recalculated_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
}
