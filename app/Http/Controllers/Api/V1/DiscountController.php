<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DiscountService;
use Illuminate\Http\JsonResponse;

class DiscountController extends Controller
{
    public function __construct(
        private readonly DiscountService $discountService
    ) {
    }

    /**
     * Calculate discounts for the order
     */
    public function calculate(Order $order): JsonResponse
    {
        return response()->json(
            $this->discountService->calculateDiscounts($order)
        );
    }
}
