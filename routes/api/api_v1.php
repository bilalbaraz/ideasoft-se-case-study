<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\DiscountController;

Route::middleware('throttle:orders')->resource('orders', OrderController::class);
Route::middleware('throttle:discounts')->post('orders/{order}/calculate-discount', [DiscountController::class, 'calculate']);
