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
    private $cache;
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->productService = Mockery::mock(ProductService::class);
        $this->cache = Mockery::mock('Illuminate\Contracts\Cache\Factory');
        $this->logger = Mockery::mock('Psr\Log\LoggerInterface');
        
        $this->service = new OrderService(
            $this->orderRepository,
            $this->productService,
            $this->cache,
            $this->logger
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
        $orders = new Collection([new Order()]);

        // Mock Redis cache to throw exception
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.all')
            ->andThrow(new \Exception('Redis connection failed'));

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Mock Database cache to throw exception
        $databaseTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $databaseTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.all')
            ->andThrow(new \Exception('Database connection failed'));

        $databaseStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $databaseStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($databaseTaggedCache);

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisStore);
        $this->cache->shouldReceive('store')
            ->with('database')
            ->once()
            ->andReturn($databaseStore);

        // Mock logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Redis cache failed, falling back to database cache', [
                'exception' => 'Redis connection failed'
            ]);
        $this->logger->shouldReceive('error')
            ->once()
            ->with('Failed to get result from both stores', [
                'exception' => 'Database connection failed'
            ]);

        // Assert
        $this->expectException(\App\Exceptions\CacheException::class);
        $this->expectExceptionMessage('Both cache stores failed');

        // Act
        $this->service->getAllOrders();
    }

    /**
     * @test
     * @covers \App\Services\OrderService::getOrder
     * @covers \App\Services\OrderService::cacheResult
     */
    public function test_get_order_returns_from_redis_cache(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;

        // Mock Redis cache for success
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.1')
            ->andReturn($order);

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisStore);

        // Repository should not be called since cache hit
        $this->orderRepository->shouldNotReceive('findWithRelations');

        // Act
        $result = $this->service->getOrder($order);

        // Assert
        $this->assertSame($order, $result);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::getOrder
     * @covers \App\Services\OrderService::cacheResult
     */
    public function test_get_order_returns_from_database_cache_when_redis_fails(): void
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

    /**
     * @test
     * @covers \App\Services\OrderService::getOrder
     * @covers \App\Services\OrderService::cacheResult
     */
    public function test_get_order_returns_from_repository_when_both_caches_fail(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
        
        // Mock repository - should be called only once when both caches fail
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Create Redis mock that throws exception
        $redisMock = Mockery::mock('Illuminate\Cache\Repository');
        $redisMock->shouldReceive('remember')
            ->twice()
            ->andThrow(new \Exception('Redis connection failed'));

        // Create Database mock that throws exception
        $databaseMock = Mockery::mock('Illuminate\Cache\Repository');
        $databaseMock->shouldReceive('remember')
            ->twice()
            ->andThrow(new \Exception('Database connection failed'));

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
        $result1 = $this->service->getOrder($order); // Redis fails -> Database fails -> Repository
        $result2 = $this->service->getOrder($order); // Redis fails -> Database fails -> Repository

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

        // Log warning should have been called for Database failures
        Log::shouldHaveReceived('warning')
            ->twice()
            ->with('Database cache failed, falling back to repository', [
                'error' => 'Database connection failed',
                'order_id' => 1
            ]);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::getOrder
     * @covers \App\Services\OrderService::cacheResult
     */
    public function test_get_order_throws_exception_when_all_sources_fail(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;

        // Mock Redis cache to throw exception
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.1')
            ->andThrow(new \Exception('Redis connection failed'));

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Mock Database cache to throw exception
        $databaseTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $databaseTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.1')
            ->andThrow(new \Exception('Database connection failed'));

        $databaseStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $databaseStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($databaseTaggedCache);

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisStore);
        $this->cache->shouldReceive('store')
            ->with('database')
            ->once()
            ->andReturn($databaseStore);

        // Mock logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Redis cache failed, falling back to database cache', [
                'exception' => 'Redis connection failed'
            ]);
        $this->logger->shouldReceive('error')
            ->once()
            ->with('Failed to get result from both stores', [
                'exception' => 'Database connection failed'
            ]);

        // Repository should not be called since we throw CacheException
        $this->orderRepository->shouldNotReceive('findWithRelations');

        // Assert
        $this->expectException(\App\Exceptions\CacheException::class);
        $this->expectExceptionMessage('Both cache stores failed');

        // Act
        $this->service->getOrder($order);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::createOrder
     * @covers \App\Services\OrderService::prepareAndValidateItems
     */
    public function test_create_order(): void
    {
        // Arrange
        $customer = new Customer();
        $customer->id = 1;

        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->price = 100;
        $product->stock = 5; // Enough stock for the order

        $order = new Order();
        $order->id = 1;
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

        $this->productService->shouldReceive('decreaseStock')
            ->once()
            ->with($product, 2);

        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->with($order, [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'total' => 200
            ]]);

        $this->orderRepository->shouldReceive('updateTotal')
            ->once()
            ->with($order);

        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Mock Redis cache for clearing
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('flush')
            ->once();

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Mock Database cache for clearing
        $databaseTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $databaseTaggedCache->shouldReceive('flush')
            ->never();

        $databaseStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $databaseStore->shouldReceive('tags')
            ->never();

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisStore);

        // Act
        $result = $this->service->createOrder($data);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals($customer->id, $result->customer_id);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::createOrder
     * @covers \App\Services\OrderService::prepareAndValidateItems
     */
    public function test_create_order_throws_validation_exception_when_stock_is_invalid(): void
    {
        // Arrange
        $customer = new Customer();
        $customer->id = 1;

        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->stock = 1;

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

        // Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Insufficient stock for product Test Product");

        // Act
        $this->service->createOrder($data);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::getAllOrders
     */
    public function test_get_all_orders_uses_redis_cache(): void
    {
        // Arrange
        $orders = new Collection([new Order()]);

        // Mock Redis cache for success
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.all')
            ->andReturn($orders);

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisStore);

        // Repository should not be called since cache hit
        $this->orderRepository->shouldNotReceive('findAllWithRelations');

        // Act
        $result = $this->service->getAllOrders();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame($orders, $result);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::getAllOrders
     */
    public function test_get_all_orders_falls_back_to_database_cache(): void
    {
        // Arrange
        $orders = new Collection([new Order()]);

        // Mock Redis cache to throw exception
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.all')
            ->andThrow(new \Exception('Redis connection failed'));

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Mock Database cache for success
        $databaseTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $databaseTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.all')
            ->andReturn($orders);

        $databaseStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $databaseStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($databaseTaggedCache);

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->once()
            ->andReturn($redisStore);
        $this->cache->shouldReceive('store')
            ->with('database')
            ->once()
            ->andReturn($databaseStore);

        // Mock logger for Redis failure
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Redis cache failed, falling back to database cache', [
                'exception' => 'Redis connection failed'
            ]);

        // Repository should not be called since database cache hit
        $this->orderRepository->shouldNotReceive('findAllWithRelations');

        // Act
        $result = $this->service->getAllOrders();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame($orders, $result);
    }
}
