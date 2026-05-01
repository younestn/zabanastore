<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderShippingDetail extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'carrier_key',
        'carrier_name',
        'delivery_service_name',
        'delivery_type',
        'tracking_number',
        'tracking_id',
        'remote_order_id',
        'remote_display_id',
        'delivery_price',
        'shipping_status',
        'status',
        'shipment_payload',
        'request_payload',
        'shipment_response',
        'response_payload',
        'error_message',
        'desk_code',
        'desk_name',
        'last_synced_at',
    ];

    protected $casts = [
        'shipment_payload' => 'array',
        'request_payload' => 'array',
        'shipment_response' => 'array',
        'response_payload' => 'array',
        'delivery_price' => 'double',
        'last_synced_at' => 'datetime',
    ];
}
