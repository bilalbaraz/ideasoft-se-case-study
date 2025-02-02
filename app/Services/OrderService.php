<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Psr\Log\LoggerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Exceptions\OrderCreationException;
use App\Exceptions\CacheException;

class OrderService
{
    /**
     * Cache duration in minutes
     */
    private const CACHE_DURATION = 5;

    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'orders';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductService $productService,
        private readonly CacheFactory $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get all orders with their relationships
     *
     * @return Collection<Order>
     * @throws CacheException When both cache stores fail
     */
    public function getAllOrders(): Collection
    {
        return $this->cacheResult(
            'redis',
            self::CACHE_PREFIX . '.all',
            fn () => $this->orderRepository->getAllWithRelations()
        );
    }

    /**
     * Get a specific order with its relationships
     *
     * @param Order $order The order to retrieve
     * @return Order
     * @throws CacheException When both cache stores fail
     */
    public function getOrder(Order $order): Order
    {
        return $this->cacheResult(
            'redis',
            self::CACHE_PREFIX . '.' . $order->id,
            fn () => $this->orderRepository->findWithRelations($order)
        );
    }

    /**
     * Create a new order with the given data
     *
     * @param array{customer_id: int, items: array} $data Order creation data
     * @return Order
     * @throws ValidationException When stock validation fails
     * @throws OrderCreationException When order creation fails
     */
    public function createOrder(array $data): Order
    {
        try {
            return DB::transaction(function () use ($data) {
                $order = $this->orderRepository->create([
                    'customer_id' => $data['customer_id'],
                    'total' => 0
                ]);

                try {
                    $items = $this->prepareAndValidateItems($data['items']);
                    $this->orderRepository->createOrderItems($order, $items);
                    $this->orderRepository->updateTotal($order);
                    
                    $order = $this->orderRepository->findWithRelations($order);
                    $this->clearCache();

                    return $order;
                } catch (ValidationException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw new OrderCreationException(
                        'Failed to create order: ' . $e->getMessage(),
                        previous: $e
                    );
                }
            });
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            throw new OrderCreationException(
                'Failed to create order: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Prepare and validate order items
     *
     * @param array $items Raw items data from request
     * @return array Prepared items data with validated stock
     * @throws ValidationException When stock validation fails
     */
    private function prepareAndValidateItems(array $items): array
    {
        $preparedItems = [];

        foreach ($items as $item) {
            $product = $this->productService->getProduct($item['product_id']);

            if ($product->stock < $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for product {$product->name}"
                ]);
            }

            $preparedItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
                'total' => $product->price * $item['quantity']
            ];

            // Update product stock
            $this->productService->decreaseStock($product, $item['quantity']);
        }

        return $preparedItems;
    }

    /**
     * Cache the result of a callback using multiple cache stores
     *
     * @template T
     * @param string $store Cache store
     * @param string $key Cache key
     * @param callable(): T $callback Callback to execute if cache misses
     * @return T
     * @throws CacheException When both cache stores fail
     */
    private function cacheResult(string $store, string $key, \Closure $callback)
    {
        try {
            return $this->getFromCache($store, $key) ?? $this->setInCache($store, $key, $callback());
        } catch (\Exception $e) {
            return $this->handleCacheError($store, $key, $callback, $e);
        }
    }

    private function getFromCache(string $store, string $key)
    {
        $cache = $this->getCacheStore($store);
        return $cache->get($key);
    }

    private function setInCache(string $store, string $key, $value)
    {
        try {
            $cache = $this->getCacheStore($store);
            $cache->put($key, $value, self::CACHE_DURATION * 60);
            return $value;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to write to cache', [
                'store' => $store,
                'exception' => $e->getMessage()
            ]);
            return $value;
        }
    }

    private function getCacheStore(string $store)
    {
        $cache = $this->cache->store($store);
        if ($cache === null) {
            throw new \Exception('Cache store not found: ' . $store);
        }
        return $cache->tags(['orders']);
    }

    private function handleCacheError(string $store, string $key, \Closure $callback, \Exception $e)
    {
        if ($store === 'redis') {
            $this->logger->warning('Redis cache failed, falling back to database cache', [
                'exception' => $e->getMessage()
            ]);
            return $this->cacheResult('database', $key, $callback);
        }

        $this->logger->error('Failed to get result from both stores', [
            'exception' => $e->getMessage()
        ]);
        throw new CacheException('Both cache stores failed');
    }

    /**
     * Clear the order cache from all stores
     */
    private function clearCache(): void
    {
        try {
            $this->cache->store('redis')->tags([self::CACHE_PREFIX])->flush();
        } catch (\Exception $e) {
            $this->logger->warning('Redis cache clear failed, falling back to database cache', [
                'error' => $e->getMessage()
            ]);
            
            try {
                $this->cache->store('database')->tags([self::CACHE_PREFIX])->flush();
            } catch (\Exception $e) {
                $this->logger->error('Failed to clear cache from both stores', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
