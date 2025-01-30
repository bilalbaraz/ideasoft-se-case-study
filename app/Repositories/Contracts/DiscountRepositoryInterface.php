<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

interface DiscountRepositoryInterface
{
    /**
     * Get order items grouped by category
     */
    public function getOrderItemsByCategory(Order $order): Collection;

    /**
     * Get order total
     */
    public function getOrderTotal(Order $order): float;

    /**
     * Get category total
     * 
     * @param Collection<OrderItem> $items
     */
    public function getCategoryTotal(Collection $items): float;
}
