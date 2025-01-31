<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DiscountService;
use Illuminate\Http\JsonResponse;
use Sentry\State\Scope;

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
        try {
            return response()->json(
                $this->discountService->calculateDiscounts($order)
            );
        } catch (\Exception $e) {
            \Sentry\withScope(function (Scope $scope) use ($order, $e): void {
                $scope->setExtra('order_id', $order->id);
                $scope->setExtra('order_total', $order->total);
                \Sentry\captureException($e);
            });

            return response()->json([
                'message' => 'Error calculating discounts',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
