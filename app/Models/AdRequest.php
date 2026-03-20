<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'product_id',
        'ad_type',
        'duration_days',
        'price',
        'image_path',
        'notes',
        'status'
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // Add the appends array to include accessors in JSON
    protected $appends = ['image_url'];

    /**
     * Get the image URL attribute
     */
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return asset($this->image_path);
        }
        return null;
    }

    public function vendor()
    {   
        return $this->belongsTo(Seller::class, 'vendor_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}