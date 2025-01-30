<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\DiscountRepositoryInterface;
use Illuminate\Support\Collection;

class DiscountRepository implements DiscountRepositoryInterface
{
    public function getOrderItemsByCategory(Order $order): Collection
    {
        return $order->items()
            ->with(['product.category'])
            ->get()
            ->groupBy('product.category.id');
    }

    public function getOrderTotal(Order $order): float
    {
        return $order->items()->sum('total');
    }

    public function getCategoryTotal(Collection $items): float
    {
        return $items->sum('total');
    }
}
