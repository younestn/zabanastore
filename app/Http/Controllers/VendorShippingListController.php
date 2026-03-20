<?php

namespace App\Http\Controllers; // Changed namespace to app controllers

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\VendorShipping;
use App\Models\ShippingCompany;
use Auth;

class VendorShippingListController extends Controller // Renamed class
{
    public function index() {
        $companies = ShippingCompany::all();
        $vendorShippings = VendorShipping::where('vendor_id', Auth::id())->with('company')->get();
        return view('shippingvendor::vendors.index', compact('companies', 'vendorShippings'));
    }

    public function ship() { // Add this method for your route
        return 'Rote Work';
    }

    public function store(Request $request) {
        VendorShipping::create([
            'vendor_id' => Auth::id(),
            'shipping_company_id' => $request->shipping_company_id,
            'account_key' => $request->account_key,
        ]);
        return redirect()->back()->with('success', 'Shipping company added successfully');
    }
}