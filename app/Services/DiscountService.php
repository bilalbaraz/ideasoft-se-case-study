<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\Contracts\DiscountRepositoryInterface;

class DiscountService
{
    private const CATEGORY_MIN_ITEMS = 6;
    private const CATEGORY_DISCOUNT_RATE = 0.10; // 10%
    private const TOTAL_MIN_AMOUNT = 1000;
    private const TOTAL_DISCOUNT_RATE = 0.10; // 10%

    public function __construct(
        private readonly DiscountRepositoryInterface $discountRepository
    ) {
    }

    /**
     * Calculate discounts for order
     */
    public function calculateDiscounts(Order $order): array
    {
        $discounts = [];
        $subtotal = $this->discountRepository->getOrderTotal($order);

        // Calculate category discounts
        $categoryDiscounts = $this->calculateCategoryDiscounts($order);
        if (!empty($categoryDiscounts)) {
            $discounts = array_merge($discounts, $categoryDiscounts);
        }

        // Calculate total amount discount
        $totalDiscount = $this->calculateTotalDiscount($subtotal);
        if ($totalDiscount) {
            $discounts[] = $totalDiscount;
        }

        // Calculate total discount amount
        $totalDiscountAmount = array_sum(array_column($discounts, 'amount'));

        return [
            'order_id' => $order->id,
            'subtotal' => $subtotal,
            'discounts' => $discounts,
            'total_discount' => $totalDiscountAmount,
            'total' => $subtotal - $totalDiscountAmount
        ];
    }

    /**
     * Calculate category discounts
     */
    private function calculateCategoryDiscounts(Order $order): array
    {
        $discounts = [];
        $itemsByCategory = $this->discountRepository->getOrderItemsByCategory($order);

        foreach ($itemsByCategory as $categoryId => $items) {
            if ($items->sum('quantity') >= self::CATEGORY_MIN_ITEMS) {
                $categoryTotal = $this->discountRepository->getCategoryTotal($items);
                $discountAmount = $categoryTotal * self::CATEGORY_DISCOUNT_RATE;

                $discounts[] = [
                    'type' => 'category',
                    'category_id' => $categoryId,
                    'item_count' => $items->sum('quantity'),
                    'discount_rate' => self::CATEGORY_DISCOUNT_RATE * 100 . '%',
                    'amount' => $discountAmount
                ];
            }
        }

        return $discounts;
    }

    /**
     * Calculate total amount discount
     */
    private function calculateTotalDiscount(float $total): ?array
    {
        if ($total >= self::TOTAL_MIN_AMOUNT) {
            $discountAmount = $total * self::TOTAL_DISCOUNT_RATE;

            return [
                'type' => 'total_amount',
                'min_amount' => self::TOTAL_MIN_AMOUNT,
                'order_total' => $total,
                'discount_rate' => self::TOTAL_DISCOUNT_RATE * 100 . '%',
                'amount' => $discountAmount
            ];
        }

        return null;
    }
}
