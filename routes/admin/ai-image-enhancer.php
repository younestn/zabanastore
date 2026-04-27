<?php

use App\Http\Controllers\Admin\Product\ProductImageEnhancerController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['admin', 'actch:admin_panel']], function () {
    Route::group(['prefix' => 'products/ai-image-enhancer', 'as' => 'products.ai-image-enhancer.', 'middleware' => ['module:product_management']], function () {
        Route::get('/', [ProductImageEnhancerController::class, 'index'])->name('index');
        Route::post('/', [ProductImageEnhancerController::class, 'enhance'])->name('enhance');
    });
});
