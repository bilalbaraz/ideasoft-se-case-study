<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\OrderService;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    private OrderService $service;
    private OrderRepositoryInterface $orderRepository;
    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->productService = Mockery::mock(ProductService::class);
        
        $this->service = new OrderService(
            $this->orderRepository,
            $this->productService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_all_orders(): void
    {
        // Arrange
        $collection = new Collection([new Order()]);
        
        // Mock repository
        $this->orderRepository->shouldReceive('getAllWithRelations')
            ->once()
            ->andReturn($collection);

        // Mock Redis cache to execute the closure
        Cache::shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturnSelf();
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $callback() instanceof Collection;
            })
            ->andReturn($collection);

        // Act
        $result = $this->service->getAllOrders();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    public function test_get_order(): void
    {
        // Arrange
        $order = new Order();
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Act
        $result = $this->service->getOrder($order);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
    }

    public function test_create_order(): void
    {
        // Arrange
        $customer = new Customer();
        $customer->id = 1;

        $product = new Product();
        $product->id = 1;
        $product->price = 100;

        $order = new Order();
        $order->customer_id = $customer->id;

        $data = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2
                ]
            ]
        ];

        $preparedItems = [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => $product->price
            ]
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(['customer_id' => $customer->id, 'total' => 0])
            ->andReturn($order);

        $this->productService->shouldReceive('getProduct')
            ->once()
            ->with($product->id)
            ->andReturn($product);

        $this->productService->shouldReceive('validateStock')
            ->once()
            ->with($product, 2);

        $this->productService->shouldReceive('decreaseStock')
            ->once()
            ->with($product, 2);

        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->with($order, $preparedItems);

        $this->orderRepository->shouldReceive('updateTotal')
            ->once()
            ->with($order);

        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Act
        $result = $this->service->createOrder($data);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
    }

    public function test_update_order(): void
    {
        // Arrange
        $order = new Order();
        $product = new Product();
        $product->id = 1;
        $product->price = 100;

        $data = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2
                ]
            ]
        ];

        $preparedItems = [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => $product->price
            ]
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('deleteOrderItems')
            ->once()
            ->with($order);

        $this->productService->shouldReceive('getProduct')
            ->once()
            ->with($product->id)
            ->andReturn($product);

        $this->productService->shouldReceive('validateStock')
            ->once()
            ->with($product, 2);

        $this->productService->shouldReceive('decreaseStock')
            ->once()
            ->with($product, 2);

        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->with($order, $preparedItems);

        $this->orderRepository->shouldReceive('updateTotal')
            ->once()
            ->with($order);

        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Act
        $result = $this->service->updateOrder($order, $data);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
    }

    public function test_delete_order(): void
    {
        // Arrange
        $order = new Order();
        $product = new Product();
        $orderItem = new OrderItem();
        $orderItem->quantity = 2;
        $orderItem->product = $product;
        
        $order->items = new Collection([$orderItem]);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productService->shouldReceive('increaseStock')
            ->once()
            ->with($product, 2);

        $this->orderRepository->shouldReceive('delete')
            ->once()
            ->with($order)
            ->andReturn(true);

        // Act
        $result = $this->service->deleteOrder($order);

        // Assert
        $this->assertTrue($result);
    }

    public function test_create_order_throws_validation_exception_when_stock_is_invalid(): void
    {
        // Arrange
        $customer = new Customer();
        $customer->id = 1;

        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';

        $order = new Order();
        $order->customer_id = $customer->id;

        $data = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2
                ]
            ]
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(['customer_id' => $customer->id, 'total' => 0])
            ->andReturn($order);

        $this->productService->shouldReceive('getProduct')
            ->once()
            ->with($product->id)
            ->andReturn($product);

        $this->productService->shouldReceive('validateStock')
            ->once()
            ->with($product, 2)
            ->andThrow(ValidationException::withMessages([
                'items' => ["Product Test Product does not have enough stock"]
            ]));

        // Assert
        $this->expectException(ValidationException::class);

        // Act
        $this->service->createOrder($data);
    }

    public function test_get_all_orders_uses_redis_cache(): void
    {
        // Arrange
        $collection = new Collection([new Order()]);
        
        // Mock repository
        $this->orderRepository->shouldReceive('getAllWithRelations')
            ->once() // Should be called only once due to caching
            ->andReturn($collection);

        // Mock Redis cache
        Cache::shouldReceive('store')
            ->with('redis')
            ->twice()
            ->andReturnSelf();
        Cache::shouldReceive('remember')
            ->twice()
            ->withArgs(function ($key, $ttl, $callback) {
                return $callback() instanceof Collection;
            })
            ->andReturn($collection);

        // Act
        $result1 = $this->service->getAllOrders(); // First call should hit the repository
        $result2 = $this->service->getAllOrders(); // Second call should hit the cache

        // Assert
        $this->assertInstanceOf(Collection::class, $result1);
        $this->assertInstanceOf(Collection::class, $result2);
        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
    }

    public function test_get_all_orders_falls_back_to_database_cache(): void
    {
        // Arrange
        $collection = new Collection([new Order()]);
        
        // Mock repository
        $this->orderRepository->shouldReceive('getAllWithRelations')
            ->once() // Should be called only once due to caching
            ->andReturn($collection);

        // Mock Redis cache to throw exception
        Cache::shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturnSelf();
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return true; // We'll throw exception anyway
            })
            ->andThrow(new \Exception('Redis connection failed'));

        // Mock database cache
        Cache::shouldReceive('store')
            ->with('database')
            ->once()
            ->andReturnSelf();
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $callback() instanceof Collection;
            })
            ->andReturn($collection);

        // Act
        $result = $this->service->getAllOrders();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }
}
