<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class DiscountController extends Controller
{
    // Discount rules
    private const CATEGORY_MIN_ITEMS = 6;
    private const CATEGORY_DISCOUNT_RATE = 0.10; // 10%
    private const TOTAL_MIN_AMOUNT = 1000;
    private const TOTAL_DISCOUNT_RATE = 0.10; // 10%

    /**
     * Calculate discounts for the given order.
     */
    public function calculate(Order $order): JsonResponse
    {
        // Load order items with products if not loaded
        if (!$order->relationLoaded('items.product')) {
            $order->load('items.product');
        }

        $discounts = [];
        $totalDiscount = 0;

        // 1. Category Discount: 10% discount for orders with 6 or more items from the same category
        $productsByCategory = $order->getProductsByCategory();
        foreach ($productsByCategory as $categoryId => $items) {
            $categoryItemCount = collect($items)->sum('quantity');
            $categoryTotal = collect($items)->sum('total');

            if ($categoryItemCount >= self::CATEGORY_MIN_ITEMS) {
                $discountAmount = $categoryTotal * self::CATEGORY_DISCOUNT_RATE;
                $totalDiscount += $discountAmount;

                $discounts[] = [
                    'type' => 'category',
                    'category_id' => $categoryId,
                    'item_count' => $categoryItemCount,
                    'discount_rate' => self::CATEGORY_DISCOUNT_RATE * 100 . '%',
                    'amount' => round($discountAmount, 2)
                ];
            }
        }

        // 2. Total Amount Discount: 10% discount for orders over 1000 TL
        if ($order->isEligibleForTotalDiscount(self::TOTAL_MIN_AMOUNT)) {
            $discountAmount = $order->total * self::TOTAL_DISCOUNT_RATE;
            $totalDiscount += $discountAmount;

            $discounts[] = [
                'type' => 'total_amount',
                'min_amount' => self::TOTAL_MIN_AMOUNT,
                'order_total' => $order->total,
                'discount_rate' => self::TOTAL_DISCOUNT_RATE * 100 . '%',
                'amount' => round($discountAmount, 2)
            ];
        }

        // Calculate final amounts
        $subtotal = $order->total;
        $totalWithDiscount = $subtotal - $totalDiscount;

        return response()->json([
            'order_id' => $order->id,
            'subtotal' => round($subtotal, 2),
            'discounts' => $discounts,
            'total_discount' => round($totalDiscount, 2),
            'total' => round($totalWithDiscount, 2)
        ]);
    }
}
