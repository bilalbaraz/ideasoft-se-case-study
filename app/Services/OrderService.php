<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductService $productService
    ) {
    }

    /**
     * Get all orders
     */
    public function getAllOrders(): Collection
    {
        return $this->orderRepository->getAllWithRelations();
    }

    /**
     * Get order by ID
     */
    public function getOrder(Order $order): Order
    {
        return $this->orderRepository->findWithRelations($order);
    }

    /**
     * Create new order
     * 
     * @throws ValidationException
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // Create order
            $order = $this->orderRepository->create([
                'customer_id' => $data['customer_id'],
                'total' => 0
            ]);

            // Prepare items data and validate stock
            $items = $this->prepareAndValidateItems($data['items']);

            // Create order items
            $this->orderRepository->createOrderItems($order, $items);

            // Calculate order total
            $this->orderRepository->updateTotal($order);

            // Load relationships
            return $this->orderRepository->findWithRelations($order);
        });
    }

    /**
     * Update order
     * 
     * @throws ValidationException
     */
    public function updateOrder(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            // Restore previous product stocks
            $this->restoreProductStocks($order);

            // Delete old items
            $this->orderRepository->deleteOrderItems($order);

            // Prepare items data and validate stock
            $items = $this->prepareAndValidateItems($data['items']);

            // Create new items
            $this->orderRepository->createOrderItems($order, $items);

            // Calculate order total
            $this->orderRepository->updateTotal($order);

            // Load relationships
            return $this->orderRepository->findWithRelations($order);
        });
    }

    /**
     * Delete order
     */
    public function deleteOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            // Restore product stocks
            $this->restoreProductStocks($order);

            // Delete order
            return $this->orderRepository->delete($order);
        });
    }

    /**
     * Prepare items data and validate stock
     * 
     * @throws ValidationException
     */
    private function prepareAndValidateItems(array $items): array
    {
        $preparedItems = [];

        foreach ($items as $item) {
            $product = $this->productService->getProduct($item['product_id']);

            // Check stock
            $this->productService->validateStock($product, $item['quantity']);

            // Decrease product stock
            $this->productService->decreaseStock($product, $item['quantity']);

            // Prepare item data
            $preparedItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
            ];
        }

        return $preparedItems;
    }

    /**
     * Restore product stocks
     */
    private function restoreProductStocks(Order $order): void
    {
        foreach ($order->items as $item) {
            $this->productService->increaseStock($item->product, $item->quantity);
        }
    }
}
