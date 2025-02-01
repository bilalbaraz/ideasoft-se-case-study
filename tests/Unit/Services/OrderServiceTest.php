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
use Illuminate\Support\Facades\Log;
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

        // Mock Log facade
        Log::shouldReceive('warning')->byDefault();
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
        
        // Mock repository - should be called only once for the first cache miss
        $this->orderRepository->shouldReceive('getAllWithRelations')
            ->once()
            ->andReturn($collection);

        // Mock Redis cache
        Cache::shouldReceive('store')
            ->with('redis')
            ->twice()
            ->andReturnSelf();

        // First call should execute closure (cache miss)
        // Second call should return cached value (cache hit)
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $callback() instanceof Collection;
            })
            ->andReturn($collection)
            ->ordered();

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return true; // Don't execute callback on cache hit
            })
            ->andReturn($collection)
            ->ordered();

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
        
        // Mock repository - should be called only once when database cache misses
        $this->orderRepository->shouldReceive('getAllWithRelations')
            ->once()
            ->andReturn($collection);

        // Create a Cache mock instance for Redis that throws exception
        $redisMock = Mockery::mock('Illuminate\Cache\Repository');
        $redisMock->shouldReceive('remember')
            ->twice()
            ->andThrow(new \Exception('Redis connection failed'));

        // Create a Cache mock instance for Database
        $databaseMock = Mockery::mock('Illuminate\Cache\Repository');
        $databaseMock->shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $callback() instanceof Collection;
            })
            ->andReturn($collection)
            ->ordered();
        $databaseMock->shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return true; // Don't execute callback on cache hit
            })
            ->andReturn($collection)
            ->ordered();

        // Setup Cache facade to return appropriate mock
        Cache::shouldReceive('store')
            ->with('redis')
            ->twice()
            ->andReturn($redisMock);
        Cache::shouldReceive('store')
            ->with('database')
            ->twice()
            ->andReturn($databaseMock);

        // Act
        $result1 = $this->service->getAllOrders(); // First call: Redis fails -> Database cache miss -> Repository
        $result2 = $this->service->getAllOrders(); // Second call: Redis fails -> Database cache hit

        // Assert
        $this->assertInstanceOf(Collection::class, $result1);
        $this->assertInstanceOf(Collection::class, $result2);
        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);

        // Log warning should have been called for Redis failures
        Log::shouldHaveReceived('warning')
            ->twice()
            ->with('Redis cache failed, falling back to database cache', [
                'error' => 'Redis connection failed'
            ]);
    }

    public function test_get_order_uses_redis_cache(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
        
        // Mock repository - should be called only once for cache miss
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Mock Redis cache
        Cache::shouldReceive('store')
            ->with('redis')
            ->twice()
            ->andReturnSelf();

        // First call should execute closure (cache miss)
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $callback() instanceof Order;
            })
            ->andReturn($order)
            ->ordered();

        // Second call should return cached value (cache hit)
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return true; // Don't execute callback on cache hit
            })
            ->andReturn($order)
            ->ordered();

        // Act
        $result1 = $this->service->getOrder($order); // First call should hit repository
        $result2 = $this->service->getOrder($order); // Second call should hit cache

        // Assert
        $this->assertInstanceOf(Order::class, $result1);
        $this->assertInstanceOf(Order::class, $result2);
    }

    public function test_get_order_falls_back_to_database_cache(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
        
        // Mock repository - should be called only once for database cache miss
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Create Redis mock that throws exception
        $redisMock = Mockery::mock('Illuminate\Cache\Repository');
        $redisMock->shouldReceive('remember')
            ->twice()
            ->andThrow(new \Exception('Redis connection failed'));

        // Create Database mock
        $databaseMock = Mockery::mock('Illuminate\Cache\Repository');
        $databaseMock->shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $callback() instanceof Order;
            })
            ->andReturn($order)
            ->ordered();
        $databaseMock->shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return true; // Don't execute callback on cache hit
            })
            ->andReturn($order)
            ->ordered();

        // Setup Cache facade
        Cache::shouldReceive('store')
            ->with('redis')
            ->twice()
            ->andReturn($redisMock);
        Cache::shouldReceive('store')
            ->with('database')
            ->twice()
            ->andReturn($databaseMock);

        // Act
        $result1 = $this->service->getOrder($order); // Redis fails -> Database miss -> Repository
        $result2 = $this->service->getOrder($order); // Redis fails -> Database hit

        // Assert
        $this->assertInstanceOf(Order::class, $result1);
        $this->assertInstanceOf(Order::class, $result2);

        // Log warning should have been called for Redis failures
        Log::shouldHaveReceived('warning')
            ->twice()
            ->with('Redis cache failed, falling back to database cache', [
                'error' => 'Redis connection failed',
                'order_id' => 1
            ]);
    }

    public function test_create_order_handles_redis_cache_failure(): void
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

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        
        // Mock repository methods
        $this->orderRepository->shouldReceive('create')->once()->andReturn($order);
        $this->orderRepository->shouldReceive('createOrderItems')->once();
        $this->orderRepository->shouldReceive('updateTotal')->once();
        $this->orderRepository->shouldReceive('findWithRelations')->once()->andReturn($order);

        // Mock product service methods
        $this->productService->shouldReceive('getProduct')->once()->andReturn($product);
        $this->productService->shouldReceive('validateStock')->once();
        $this->productService->shouldReceive('decreaseStock')->once();

        // Mock Redis cache to throw exception
        $redisMock = Mockery::mock('Illuminate\Cache\Repository');
        $redisMock->shouldReceive('tags')->once()->with(['orders'])->andReturnSelf();
        $redisMock->shouldReceive('flush')->once()->andThrow(new \Exception('Redis connection failed'));

        // Mock Database cache
        $databaseMock = Mockery::mock('Illuminate\Cache\Repository');
        $databaseMock->shouldReceive('tags')->once()->with(['orders'])->andReturnSelf();
        $databaseMock->shouldReceive('flush')->once();

        // Setup Cache facade
        Cache::shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisMock);
        Cache::shouldReceive('store')
            ->with('database')
            ->once()
            ->andReturn($databaseMock);

        // Act
        $result = $this->service->createOrder($data);

        // Assert
        $this->assertInstanceOf(Order::class, $result);

        // Log warning should have been called for Redis failure
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Redis cache clear failed, falling back to database cache', [
                'error' => 'Redis connection failed'
            ]);
    }

    public function test_update_order_handles_redis_cache_failure(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
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

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        
        // Mock repository methods
        $this->orderRepository->shouldReceive('deleteOrderItems')->once();
        $this->orderRepository->shouldReceive('createOrderItems')->once();
        $this->orderRepository->shouldReceive('updateTotal')->once();
        $this->orderRepository->shouldReceive('findWithRelations')->once()->andReturn($order);

        // Mock product service methods
        $this->productService->shouldReceive('getProduct')->once()->andReturn($product);
        $this->productService->shouldReceive('validateStock')->once();
        $this->productService->shouldReceive('decreaseStock')->once();

        // Mock Redis cache to throw exception for forget operation
        $redisTagsMock = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTagsMock->shouldReceive('flush')->never(); // Should not be called due to earlier exception

        $redisMock = Mockery::mock('Illuminate\Cache\Repository');
        $redisMock->shouldReceive('forget')
            ->once()
            ->with('orders.1')
            ->andThrow(new \Exception('Redis connection failed'));
        $redisMock->shouldReceive('tags')
            ->never(); // Should not be called due to earlier exception

        // Mock Database cache for both operations
        $databaseTagsMock = Mockery::mock('Illuminate\Cache\TaggedCache');
        $databaseTagsMock->shouldReceive('flush')->once();

        $databaseMock = Mockery::mock('Illuminate\Cache\Repository');
        $databaseMock->shouldReceive('forget')
            ->once()
            ->with('orders.1');
        $databaseMock->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($databaseTagsMock);

        // Setup Cache facade
        Cache::shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisMock);
        Cache::shouldReceive('store')
            ->with('database')
            ->twice()
            ->andReturn($databaseMock);

        // Act
        $result = $this->service->updateOrder($order, $data);

        // Assert
        $this->assertInstanceOf(Order::class, $result);

        // Log warning should have been called once for Redis failure
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Redis cache clear failed, falling back to database cache', [
                'error' => 'Redis connection failed'
            ]);
    }

    public function test_delete_order_handles_redis_cache_failure(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
        $product = new Product();
        $orderItem = new OrderItem();
        $orderItem->quantity = 2;
        $orderItem->product = $product;
        
        $order->items = new Collection([$orderItem]);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        
        // Mock repository and product service
        $this->productService->shouldReceive('increaseStock')->once();
        $this->orderRepository->shouldReceive('delete')->once()->andReturn(true);

        // Mock Redis cache to throw exception for forget operation
        $redisTagsMock = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTagsMock->shouldReceive('flush')->never(); // Should not be called due to earlier exception

        $redisMock = Mockery::mock('Illuminate\Cache\Repository');
        $redisMock->shouldReceive('forget')
            ->once()
            ->with('orders.1')
            ->andThrow(new \Exception('Redis connection failed'));
        $redisMock->shouldReceive('tags')
            ->never(); // Should not be called due to earlier exception

        // Mock Database cache for both operations
        $databaseTagsMock = Mockery::mock('Illuminate\Cache\TaggedCache');
        $databaseTagsMock->shouldReceive('flush')->once();

        $databaseMock = Mockery::mock('Illuminate\Cache\Repository');
        $databaseMock->shouldReceive('forget')
            ->once()
            ->with('orders.1');
        $databaseMock->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($databaseTagsMock);

        // Setup Cache facade
        Cache::shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisMock);
        Cache::shouldReceive('store')
            ->with('database')
            ->twice()
            ->andReturn($databaseMock);

        // Act
        $result = $this->service->deleteOrder($order);

        // Assert
        $this->assertTrue($result);

        // Log warning should have been called once for Redis failure
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Redis cache clear failed, falling back to database cache', [
                'error' => 'Redis connection failed'
            ]);
    }

    public function test_update_order_throws_validation_exception_when_stock_is_invalid(): void
    {
        // Arrange
        $order = new Order();
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';

        $data = [
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

        $this->orderRepository->shouldReceive('deleteOrderItems')
            ->once()
            ->with($order);

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
        $this->service->updateOrder($order, $data);
    }
}
