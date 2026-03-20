<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorAdRequestController extends Controller
{
    public function create()
    {
        return "Ad Request Create Page - Route is working!";
    }
    
    public function store(Request $request)
    {
        return "Ad Request Store Method - Route is working!";
    }
}