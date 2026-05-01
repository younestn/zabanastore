<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingEvent extends Model
{
    protected $fillable = [
        'order_id',
        'order_shipping_detail_id',
        'carrier_key',
        'tracking_number',
        'shipping_status',
        'event_label',
        'event_description',
        'event_payload',
        'event_at',
    ];

    protected $casts = [
        'event_payload' => 'array',
        'event_at' => 'datetime',
    ];
}
