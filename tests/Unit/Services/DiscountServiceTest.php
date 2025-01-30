<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\DiscountRepositoryInterface;
use App\Services\DiscountService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class DiscountServiceTest extends TestCase
{
    private DiscountService $service;
    private DiscountRepositoryInterface $discountRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->discountRepository = Mockery::mock(DiscountRepositoryInterface::class);
        $this->service = new DiscountService($this->discountRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calculate_discounts_with_no_discounts(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
        $subtotal = 500.0;

        $this->discountRepository->shouldReceive('getOrderTotal')
            ->once()
            ->with($order)
            ->andReturn($subtotal);

        $this->discountRepository->shouldReceive('getOrderItemsByCategory')
            ->once()
            ->with($order)
            ->andReturn(new Collection());

        // Act
        $result = $this->service->calculateDiscounts($order);

        // Assert
        $this->assertEquals([
            'order_id' => 1,
            'subtotal' => 500.0,
            'discounts' => [],
            'total_discount' => 0,
            'total' => 500.0
        ], $result);
    }

    public function test_calculate_discounts_with_category_discount(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
        $subtotal = 800.0;

        $categoryItems = new Collection([
            new OrderItem(['quantity' => 3]),
            new OrderItem(['quantity' => 4])
        ]);

        $this->discountRepository->shouldReceive('getOrderTotal')
            ->once()
            ->with($order)
            ->andReturn($subtotal);

        $this->discountRepository->shouldReceive('getOrderItemsByCategory')
            ->once()
            ->with($order)
            ->andReturn(new Collection([1 => $categoryItems]));

        $this->discountRepository->shouldReceive('getCategoryTotal')
            ->once()
            ->with($categoryItems)
            ->andReturn(500.0);

        // Act
        $result = $this->service->calculateDiscounts($order);

        // Assert
        $this->assertEquals([
            'order_id' => 1,
            'subtotal' => 800.0,
            'discounts' => [
                [
                    'type' => 'category',
                    'category_id' => 1,
                    'item_count' => 7,
                    'discount_rate' => '10%',
                    'amount' => 50.0
                ]
            ],
            'total_discount' => 50.0,
            'total' => 750.0
        ], $result);
    }

    public function test_calculate_discounts_with_total_amount_discount(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
        $subtotal = 1200.0;

        $this->discountRepository->shouldReceive('getOrderTotal')
            ->once()
            ->with($order)
            ->andReturn($subtotal);

        $this->discountRepository->shouldReceive('getOrderItemsByCategory')
            ->once()
            ->with($order)
            ->andReturn(new Collection());

        // Act
        $result = $this->service->calculateDiscounts($order);

        // Assert
        $this->assertEquals([
            'order_id' => 1,
            'subtotal' => 1200.0,
            'discounts' => [
                [
                    'type' => 'total_amount',
                    'min_amount' => 1000,
                    'order_total' => 1200.0,
                    'discount_rate' => '10%',
                    'amount' => 120.0
                ]
            ],
            'total_discount' => 120.0,
            'total' => 1080.0
        ], $result);
    }

    public function test_calculate_discounts_with_both_discounts(): void
    {
        // Arrange
        $order = new Order();
        $order->id = 1;
        $subtotal = 1500.0;

        $categoryItems = new Collection([
            new OrderItem(['quantity' => 3]),
            new OrderItem(['quantity' => 4])
        ]);

        $this->discountRepository->shouldReceive('getOrderTotal')
            ->once()
            ->with($order)
            ->andReturn($subtotal);

        $this->discountRepository->shouldReceive('getOrderItemsByCategory')
            ->once()
            ->with($order)
            ->andReturn(new Collection([1 => $categoryItems]));

        $this->discountRepository->shouldReceive('getCategoryTotal')
            ->once()
            ->with($categoryItems)
            ->andReturn(1000.0);

        // Act
        $result = $this->service->calculateDiscounts($order);

        // Assert
        $this->assertEquals([
            'order_id' => 1,
            'subtotal' => 1500.0,
            'discounts' => [
                [
                    'type' => 'category',
                    'category_id' => 1,
                    'item_count' => 7,
                    'discount_rate' => '10%',
                    'amount' => 100.0
                ],
                [
                    'type' => 'total_amount',
                    'min_amount' => 1000,
                    'order_total' => 1500.0,
                    'discount_rate' => '10%',
                    'amount' => 150.0
                ]
            ],
            'total_discount' => 250.0,
            'total' => 1250.0
        ], $result);
    }
}
