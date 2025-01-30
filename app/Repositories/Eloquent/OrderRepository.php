<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly Order $order
    ) {
    }

    public function getAllWithRelations(): Collection
    {
        return $this->order->with(['customer', 'items.product'])->get();
    }

    public function findWithRelations(Order $order): Order
    {
        return $order->load(['customer', 'items.product']);
    }

    public function create(array $data): Order
    {
        return $this->order->create($data);
    }

    public function createOrderItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item['unit_price'] * $item['quantity']
            ]);
        }
    }

    public function deleteOrderItems(Order $order): void
    {
        $order->items()->delete();
    }

    public function delete(Order $order): bool
    {
        return $order->delete();
    }

    public function updateTotal(Order $order): void
    {
        $order->calculateTotal();
    }
}
