<?php

namespace Tests\Unit\Api\V1\Controllers;

use App\Http\Controllers\Api\V1\DiscountController;
use App\Models\Order;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class DiscountControllerTest extends TestCase
{
    use RefreshDatabase;

    private DiscountController $discountController;
    private DiscountService $discountService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock DiscountService
        $this->discountService = Mockery::mock(DiscountService::class);
        
        // Create controller with mock service
        $this->discountController = new DiscountController($this->discountService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calculate_returns_discount_data(): void
    {
        // Arrange
        $order = Order::factory()->create();
        
        $expectedResponse = [
            'order_id' => $order->id,
            'subtotal' => 1500.00,
            'discounts' => [
                [
                    'type' => 'category',
                    'category_id' => 1,
                    'item_count' => 6,
                    'discount_rate' => '10%',
                    'amount' => 60.00
                ],
                [
                    'type' => 'total_amount',
                    'min_amount' => 1000,
                    'order_total' => 1500.00,
                    'discount_rate' => '10%',
                    'amount' => 150.00
                ]
            ],
            'total_discount' => 210.00,
            'total' => 1290.00
        ];

        // Mock service response
        $this->discountService->shouldReceive('calculateDiscounts')
            ->once()
            ->with($order)
            ->andReturn($expectedResponse);

        // Act
        $response = $this->discountController->calculate($order);

        // Assert
        $this->assertEquals(200, $response->status());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    public function test_calculate_with_no_discounts(): void
    {
        // Arrange
        $order = Order::factory()->create();
        
        $expectedResponse = [
            'order_id' => $order->id,
            'subtotal' => 500.00,
            'discounts' => [],
            'total_discount' => 0.00,
            'total' => 500.00
        ];

        // Mock service response
        $this->discountService->shouldReceive('calculateDiscounts')
            ->once()
            ->with($order)
            ->andReturn($expectedResponse);

        // Act
        $response = $this->discountController->calculate($order);

        // Assert
        $this->assertEquals(200, $response->status());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    public function test_calculate_with_only_category_discount(): void
    {
        // Arrange
        $order = Order::factory()->create();
        
        $expectedResponse = [
            'order_id' => $order->id,
            'subtotal' => 800.00,
            'discounts' => [
                [
                    'type' => 'category',
                    'category_id' => 1,
                    'item_count' => 6,
                    'discount_rate' => '10%',
                    'amount' => 80.00
                ]
            ],
            'total_discount' => 80.00,
            'total' => 720.00
        ];

        // Mock service response
        $this->discountService->shouldReceive('calculateDiscounts')
            ->once()
            ->with($order)
            ->andReturn($expectedResponse);

        // Act
        $response = $this->discountController->calculate($order);

        // Assert
        $this->assertEquals(200, $response->status());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    public function test_calculate_with_only_total_amount_discount(): void
    {
        // Arrange
        $order = Order::factory()->create();
        
        $expectedResponse = [
            'order_id' => $order->id,
            'subtotal' => 1200.00,
            'discounts' => [
                [
                    'type' => 'total_amount',
                    'min_amount' => 1000,
                    'order_total' => 1200.00,
                    'discount_rate' => '10%',
                    'amount' => 120.00
                ]
            ],
            'total_discount' => 120.00,
            'total' => 1080.00
        ];

        // Mock service response
        $this->discountService->shouldReceive('calculateDiscounts')
            ->once()
            ->with($order)
            ->andReturn($expectedResponse);

        // Act
        $response = $this->discountController->calculate($order);

        // Assert
        $this->assertEquals(200, $response->status());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }
}
