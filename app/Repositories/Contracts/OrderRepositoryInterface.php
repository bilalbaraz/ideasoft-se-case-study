<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface
{
    /**
     * Get all orders with relationships
     */
    public function getAllWithRelations(): Collection;

    /**
     * Find order by ID with relationships
     */
    public function findWithRelations(Order $order): Order;

    /**
     * Create new order
     */
    public function create(array $data): Order;

    /**
     * Create order items
     */
    public function createOrderItems(Order $order, array $items): void;

    /**
     * Delete order items
     */
    public function deleteOrderItems(Order $order): void;

    /**
     * Delete order
     */
    public function delete(Order $order): bool;

    /**
     * Update order total
     */
    public function updateTotal(Order $order): void;
}
