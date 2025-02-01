<?php

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Requests\Api\V1\Order\StoreOrderRequest;
use App\Http\Requests\Api\V1\Order\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private OrderController $orderController;
    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock services
        $this->orderService = Mockery::mock(OrderService::class);
        
        // Create controller with mock service
        $this->orderController = new OrderController(
            $this->orderService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_all_orders(): void
    {
        // Arrange
        $orders = Order::factory()->count(3)->create();
        
        $this->orderService->shouldReceive('getAllOrders')
            ->once()
            ->andReturn($orders);

        // Act
        $response = $this->orderController->index();

        // Assert
        $this->assertEquals(200, $response->status());
        $responseData = $response->getData(true);
        $this->assertCount(3, $responseData['data']);
    }

    public function test_show_returns_specific_order(): void
    {
        // Arrange
        $order = Order::factory()->create();
        
        $this->orderService->shouldReceive('getOrder')
            ->once()
            ->with($order)
            ->andReturn($order);

        // Act
        $response = $this->orderController->show($order);

        // Assert
        $this->assertEquals(200, $response->status());
        $responseData = $response->getData(true);
        $this->assertEquals($order->id, $responseData['data']['id']);
    }

    public function test_store_creates_new_order(): void
    {
        // Arrange
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        
        $requestData = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2
                ]
            ]
        ];

        // Mock StoreOrderRequest
        $request = Mockery::mock(StoreOrderRequest::class);
        $request->shouldReceive('validated')
            ->once()
            ->andReturn($requestData);
        
        $createdOrder = Order::factory()
            ->create([
                'customer_id' => $customer->id,
                'total' => $product->price * 2
            ]);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with($requestData)
            ->andReturn($createdOrder);

        // Act
        $response = $this->orderController->store($request);

        // Assert
        $this->assertEquals(201, $response->status());
        $responseData = $response->getData(true);
        $this->assertEquals($createdOrder->id, $responseData['data']['id']);
        $this->assertEquals('Order created successfully', $responseData['message']);
    }

    public function test_update_modifies_existing_order(): void
    {
        // Arrange
        $order = Order::factory()->create();
        $product = Product::factory()->create();
        
        $requestData = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3
                ]
            ]
        ];

        // Mock UpdateOrderRequest
        $request = Mockery::mock(UpdateOrderRequest::class);
        $request->shouldReceive('validated')
            ->once()
            ->andReturn($requestData);
        
        $updatedOrder = Order::factory()
            ->create([
                'customer_id' => $order->customer_id,
                'total' => $product->price * 3
            ]);

        $this->orderService->shouldReceive('updateOrder')
            ->once()
            ->with($order, $requestData)
            ->andReturn($updatedOrder);

        // Act
        $response = $this->orderController->update($request, $order);

        // Assert
        $this->assertEquals(200, $response->status());
        $responseData = $response->getData(true);
        $this->assertEquals($updatedOrder->id, $responseData['data']['id']);
        $this->assertEquals('Order updated successfully', $responseData['message']);
    }

    public function test_destroy_deletes_order(): void
    {
        // Arrange
        $order = Order::factory()->create();
        
        $this->orderService->shouldReceive('deleteOrder')
            ->once()
            ->with($order)
            ->andReturn(true);

        // Act
        $response = $this->orderController->destroy($order);

        // Assert
        $this->assertEquals(200, $response->status());
        $responseData = $response->getData(true);
        $this->assertEquals('Order deleted successfully', $responseData['message']);
    }

    public function test_store_handles_exception(): void
    {
        // Arrange
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        
        $requestData = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2
                ]
            ]
        ];

        // Mock StoreOrderRequest
        $request = Mockery::mock(StoreOrderRequest::class);
        $request->shouldReceive('validated')
            ->twice() // Called in both try and catch blocks
            ->andReturn($requestData);
        
        // Mock OrderService to throw exception
        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with($requestData)
            ->andThrow(new \Exception('Failed to create order'));

        // Act
        $response = $this->orderController->store($request);

        // Assert
        $this->assertEquals(422, $response->status());
        $responseData = $response->getData(true);
        $this->assertEquals('Error creating order', $responseData['message']);
        $this->assertEquals('Failed to create order', $responseData['error']);
    }

    public function test_update_handles_exception(): void
    {
        // Arrange
        $order = Order::factory()->create();
        $product = Product::factory()->create();
        
        $requestData = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3
                ]
            ]
        ];

        // Mock UpdateOrderRequest
        $request = Mockery::mock(UpdateOrderRequest::class);
        $request->shouldReceive('validated')
            ->twice() // Called in both try and catch blocks
            ->andReturn($requestData);
        
        // Mock OrderService to throw exception
        $this->orderService->shouldReceive('updateOrder')
            ->once()
            ->with($order, $requestData)
            ->andThrow(new \Exception('Failed to update order'));

        // Act
        $response = $this->orderController->update($request, $order);

        // Assert
        $this->assertEquals(422, $response->status());
        $responseData = $response->getData(true);
        $this->assertEquals('Error updating order', $responseData['message']);
        $this->assertEquals('Failed to update order', $responseData['error']);
    }

    public function test_destroy_handles_exception(): void
    {
        // Arrange
        $order = Order::factory()->create();
        
        $errorMessage = 'Failed to delete order';
        
        $this->orderService->shouldReceive('deleteOrder')
            ->once()
            ->with($order)
            ->andThrow(new \Exception($errorMessage));

        // Act
        $response = $this->orderController->destroy($order);

        // Assert
        $this->assertEquals(422, $response->status());
        $responseData = $response->getData(true);
        $this->assertEquals('Error deleting order', $responseData['message']);
        $this->assertEquals($errorMessage, $responseData['error']);
    }
}
