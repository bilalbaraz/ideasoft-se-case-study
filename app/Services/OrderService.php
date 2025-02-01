<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        try {
            return Cache::store('redis')->remember('orders.all', 60 * 5, function () {
                return $this->orderRepository->getAllWithRelations();
            });
        } catch (\Exception $e) {
            Log::warning('Redis cache failed, falling back to database cache', [
                'error' => $e->getMessage()
            ]);

            return Cache::store('database')->remember('orders.all', 60 * 5, function () {
                return $this->orderRepository->getAllWithRelations();
            });
        }
    }

    /**
     * Get order by ID
     */
    public function getOrder(Order $order): Order
    {
        try {
            return Cache::store('redis')->remember('orders.' . $order->id, 60 * 5, function () use ($order) {
                return $this->orderRepository->findWithRelations($order);
            });
        } catch (\Exception $e) {
            Log::warning('Redis cache failed, falling back to database cache', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);

            return Cache::store('database')->remember('orders.' . $order->id, 60 * 5, function () use ($order) {
                return $this->orderRepository->findWithRelations($order);
            });
        }
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
            $order = $this->orderRepository->findWithRelations($order);

            // Flush cache
            try {
                Cache::store('redis')->tags(['orders'])->flush();
            } catch (\Exception $e) {
                Log::warning('Redis cache clear failed, falling back to database cache', [
                    'error' => $e->getMessage()
                ]);
                Cache::store('database')->tags(['orders'])->flush();
            }

            return $order;
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
            $order = $this->orderRepository->findWithRelations($order);

            // Flush cache
            try {
                Cache::store('redis')->forget('orders.' . $order->id);
                Cache::store('redis')->tags(['orders'])->flush();
            } catch (\Exception $e) {
                Log::warning('Redis cache clear failed, falling back to database cache', [
                    'error' => $e->getMessage()
                ]);
                Cache::store('database')->forget('orders.' . $order->id);
                Cache::store('database')->tags(['orders'])->flush();
            }

            return $order;
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
            $result = $this->orderRepository->delete($order);

            // Flush cache
            try {
                Cache::store('redis')->forget('orders.' . $order->id);
                Cache::store('redis')->tags(['orders'])->flush();
            } catch (\Exception $e) {
                Log::warning('Redis cache clear failed, falling back to database cache', [
                    'error' => $e->getMessage()
                ]);
                Cache::store('database')->forget('orders.' . $order->id);
                Cache::store('database')->tags(['orders'])->flush();
            }

            return $result;
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
