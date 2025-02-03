<?php

namespace Tests\Unit\Services;

use App\Exceptions\OrderCreationException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\OrderService;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\MessageBag;
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

        // Mock Database cache for success
        $databaseTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $databaseTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.1')
            ->andReturn($order);

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
        $this->orderRepository->shouldNotReceive('findWithRelations');

        // Act
        $result = $this->service->getOrder($order);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertSame($order, $result);
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

        // Assert
        $this->expectException(\App\Exceptions\CacheException::class);
        $this->expectExceptionMessage('Both cache stores failed');

        // Act
        $this->service->getOrder($order);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::getOrder
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
     * @covers \App\Services\OrderService::getOrder
     */
    public function test_get_order_continues_when_cache_write_fails(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;

        // Mock Redis cache
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.1')
            ->andReturnNull();
        $redisTaggedCache->shouldReceive('put')
            ->once()
            ->with('orders.1', $order, 60 * 60)
            ->andThrow(new \Exception('Cache write failed'));

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->twice()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->twice()
            ->andReturn($redisStore);

        // Mock logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Failed to write to cache', [
                'store' => 'redis',
                'exception' => 'Cache write failed'
            ]);

        // Mock repository
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Act
        $result = $this->service->getOrder($order);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertSame($order, $result);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::getOrder
     */
    public function test_get_order_continues_when_cache_store_not_found(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;

        // Setup Cache facade to throw exception for store not found
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->once()
            ->andThrow(new \Exception('Cache store not found: redis'));

        // Mock database cache
        $databaseTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $databaseTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.1')
            ->andReturn(null);

        $databaseStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $databaseStore->shouldReceive('tags')
            ->once()
            ->with(['orders'])
            ->andReturn($databaseTaggedCache);

        $this->cache->shouldReceive('store')
            ->with('database')
            ->once()
            ->andReturn($databaseStore);

        // Mock logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Failed to write to cache', [
                'store' => 'redis',
                'exception' => 'Cache store not found: redis'
            ]);

        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Redis cache failed, falling back to database cache', [
                'exception' => 'Cache store not found: redis'
            ]);

        // Mock repository
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Act
        $result = $this->service->getOrder($order);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertSame($order, $result);
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

    /**
     * @test
     * @covers \App\Services\OrderService::getAllOrders
     */
    public function test_get_all_orders_continues_when_cache_write_fails(): void
    {
        // Arrange
        $orders = collect([
            new Order(),
            new Order()
        ]);

        // Mock Redis cache
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.all')
            ->andReturnNull();
        $redisTaggedCache->shouldReceive('put')
            ->once()
            ->with('orders.all', $orders, 60 * 60)
            ->andThrow(new \Exception('Cache write failed'));

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->twice()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->twice()
            ->andReturn($redisStore);

        // Mock logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Failed to write to cache', [
                'store' => 'redis',
                'exception' => 'Cache write failed'
            ]);

        // Mock repository
        $this->orderRepository->shouldReceive('getAllWithRelations')
            ->once()
            ->andReturn($orders);

        // Act
        $result = $this->service->getAllOrders();

        // Assert
        $this->assertCount(2, $result);
        $this->assertSame($orders->all(), $result->all());
    }

    /**
     * @test
     * @covers \App\Services\OrderService::getOrder
     */
    public function test_get_order_writes_to_cache_successfully(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;

        // Mock Redis cache
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('get')
            ->once()
            ->with('orders.1')
            ->andReturnNull();
        $redisTaggedCache->shouldReceive('put')
            ->once()
            ->with('orders.1', $order, 60 * 60)
            ->andReturn(true);

        $redisStore = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $redisStore->shouldReceive('tags')
            ->twice()
            ->with(['orders'])
            ->andReturn($redisTaggedCache);

        // Setup Cache facade
        $this->cache->shouldReceive('store')
            ->with('redis')
            ->twice()
            ->andReturn($redisStore);

        // Mock repository
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Act
        $result = $this->service->getOrder($order);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertSame($order, $result);
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
     * @covers \App\Services\OrderService::createOrder
     * @covers \App\Services\OrderService::prepareAndValidateItems
     */
    public function test_create_order_throws_validation_exception_when_product_validation_fails(): void
    {
        // Arrange
        $data = [
            'customer_id' => 1,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2
                ]
            ]
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock repository for initial order creation
        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(['customer_id' => 1, 'total' => 0])
            ->andReturn(new Order());

        // Mock product service to throw validation exception
        $errors = new MessageBag(['product_id' => ['Invalid product']]);
        $this->productService->shouldReceive('validateProduct')
            ->once()
            ->andThrow(ValidationException::withMessages(['product_id' => ['Invalid product']]));

        // Assert
        $this->expectException(ValidationException::class);

        // Act
        $this->service->createOrder($data);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::createOrder
     */
    public function test_create_order_throws_exception_when_create_items_fails(): void
    {
        // Arrange
        $order = new Order();
        $data = [
            'customer_id' => 1,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2
                ]
            ]
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock repository for initial order creation
        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(['customer_id' => 1, 'total' => 0])
            ->andReturn($order);

        // Mock product service for validation
        $this->productService->shouldReceive('validateProduct')
            ->once()
            ->andReturn(true);

        // Mock repository to throw exception when creating items
        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->andThrow(new \Exception('Failed to create order items'));

        // Assert
        $this->expectException(OrderCreationException::class);
        $this->expectExceptionMessage('Failed to create order: Failed to create order items');

        // Act
        $this->service->createOrder($data);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::createOrder
     */
    public function test_create_order_throws_exception_when_update_total_fails(): void
    {
        // Arrange
        $order = new Order();
        $data = [
            'customer_id' => 1,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2
                ]
            ]
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock repository for initial order creation
        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(['customer_id' => 1, 'total' => 0])
            ->andReturn($order);

        // Mock product service for validation
        $this->productService->shouldReceive('validateProduct')
            ->once()
            ->andReturn(true);

        // Mock repository for creating items
        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->andReturn(true);

        // Mock repository to throw exception when updating total
        $this->orderRepository->shouldReceive('updateTotal')
            ->once()
            ->andThrow(new \Exception('Failed to update total'));

        // Assert
        $this->expectException(OrderCreationException::class);
        $this->expectExceptionMessage('Failed to create order: Failed to update total');

        // Act
        $this->service->createOrder($data);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::createOrder
     */
    public function test_create_order_throws_exception_when_find_with_relations_fails(): void
    {
        // Arrange
        $order = new Order();
        $data = [
            'customer_id' => 1,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2
                ]
            ]
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock repository for initial order creation
        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(['customer_id' => 1, 'total' => 0])
            ->andReturn($order);

        // Mock product service for validation
        $this->productService->shouldReceive('validateProduct')
            ->once()
            ->andReturn(true);

        // Mock repository for creating items
        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->andReturn(true);

        // Mock repository for updating total
        $this->orderRepository->shouldReceive('updateTotal')
            ->once()
            ->andReturn(true);

        // Mock repository to throw exception when finding with relations
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->andThrow(new \Exception('Failed to find order'));

        // Assert
        $this->expectException(OrderCreationException::class);
        $this->expectExceptionMessage('Failed to create order: Failed to find order');

        // Act
        $this->service->createOrder($data);
    }

    /**
     * @test
     * @covers \App\Services\OrderService::createOrder
     */
    public function test_create_order_continues_when_clear_cache_fails(): void
    {
        // Arrange
        $order = new Order();
        $data = [
            'customer_id' => 1,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2
                ]
            ]
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock repository for initial order creation
        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(['customer_id' => 1, 'total' => 0])
            ->andReturn($order);

        // Mock product service
        $product = new Product();
        $product->price = 100;
        
        $this->productService->shouldReceive('validateProduct')
            ->once()
            ->andReturn(true);
        $this->productService->shouldReceive('getProduct')
            ->once()
            ->andReturn($product);

        // Mock repository for creating items
        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->andReturn(true);

        // Mock repository for updating total
        $this->orderRepository->shouldReceive('updateTotal')
            ->once()
            ->andReturn(true);

        // Mock repository for finding with relations
        $this->orderRepository->shouldReceive('findWithRelations')
            ->once()
            ->andReturn($order);

        // Mock Redis cache to throw exception
        $redisTaggedCache = Mockery::mock('Illuminate\Cache\TaggedCache');
        $redisTaggedCache->shouldReceive('flush')
            ->once()
            ->andThrow(new \Exception('Failed to clear cache'));

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

        // Mock logger for cache clear failure
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Failed to clear Redis cache', [
                'exception' => 'Failed to clear cache'
            ]);

        // Act
        $result = $this->service->createOrder($data);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertSame($order, $result);
    }
}
