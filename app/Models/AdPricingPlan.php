<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPricingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'placement',
        'description',
        'price',
        'duration_days',
        'currency',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function adRequests(): HasMany
    {
        return $this->hasMany(AdRequest::class, 'ad_pricing_plan_id');
    }
}
