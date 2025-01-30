<?php

namespace Tests\Unit\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Eloquent\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OrderRepository(new Order());
    }

    public function test_get_all_with_relations(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $product = Product::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id
        ]);

        // Get all orders with relations
        $orders = $this->repository->getAllWithRelations();

        // Assert
        $this->assertCount(1, $orders);
        $this->assertTrue($orders->first()->relationLoaded('customer'));
        $this->assertTrue($orders->first()->relationLoaded('items'));
        $this->assertTrue($orders->first()->items->first()->relationLoaded('product'));
    }

    public function test_find_with_relations(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $product = Product::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id
        ]);

        // Find order with relations
        $foundOrder = $this->repository->findWithRelations($order);

        // Assert
        $this->assertTrue($foundOrder->relationLoaded('customer'));
        $this->assertTrue($foundOrder->relationLoaded('items'));
        $this->assertTrue($foundOrder->items->first()->relationLoaded('product'));
    }

    public function test_create(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $data = [
            'customer_id' => $customer->id,
            'total' => 100.00
        ];

        // Create order
        $order = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals(100.00, $order->total);
    }

    public function test_create_order_items(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $product = Product::factory()->create(['price' => 50.00]);

        $items = [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => $product->price
            ]
        ];

        // Create order items
        $this->repository->createOrderItems($order, $items);

        // Assert
        $this->assertCount(1, $order->items);
        $this->assertEquals($product->id, $order->items->first()->product_id);
        $this->assertEquals(2, $order->items->first()->quantity);
        $this->assertEquals(50.00, $order->items->first()->unit_price);
        $this->assertEquals(100.00, $order->items->first()->total);
    }

    public function test_delete_order_items(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $product = Product::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id
        ]);

        // Delete order items
        $this->repository->deleteOrderItems($order);

        // Assert
        $this->assertCount(0, $order->items()->get());
    }

    public function test_delete(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        // Delete order
        $result = $this->repository->delete($order);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(Order::find($order->id));
    }

    public function test_update_total(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $product = Product::factory()->create(['price' => 50.00]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => $product->price,
            'total' => $product->price * 2
        ]);

        // Update total
        $this->repository->updateTotal($order);

        // Assert
        $this->assertEquals(100.00, $order->total);
    }
}
