<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorShippingCompany extends Model
{
    protected $table = 'vendor_shipping_companies';

    protected $fillable = [
        'vendor_id',
        'name',
        'website',
        'noest_guid',
        'api_token',
        'carrier_key',
        'display_name',
        'credentials',
        'status',
        'supports_home_delivery',
        'supports_desk_delivery',
        'connected_since',
        'last_tested_at',
        'last_error',
        'is_connected',
    ];

    protected $casts = [
        'status' => 'integer',
        'supports_home_delivery' => 'boolean',
        'supports_desk_delivery' => 'boolean',
        'is_connected' => 'boolean',
        'connected_since' => 'datetime',
        'last_tested_at' => 'datetime',
    ];
}
