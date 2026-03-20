<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorShipping extends Model
{
    protected $fillable = ['vendor_id','shipping_company_id','account_key'];

    public function company() {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }
}
