<?php

namespace Tests\Unit\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Eloquent\DiscountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DiscountRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DiscountRepository();
    }

    public function test_get_order_items_by_category(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        
        // Create products with different category_ids
        $product1 = Product::factory()->create(['category_id' => 1]);
        $product2 = Product::factory()->create(['category_id' => 1]);
        $product3 = Product::factory()->create(['category_id' => 2]);
        
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product3->id
        ]);

        // Get items by category
        $itemsByCategory = $this->repository->getOrderItemsByCategory($order);

        // Assert
        $this->assertCount(2, $itemsByCategory); // 2 categories
        $this->assertCount(2, $itemsByCategory[1]); // 2 items in category 1
        $this->assertCount(1, $itemsByCategory[2]); // 1 item in category 2
    }

    public function test_get_order_items_by_category_returns_empty_collection_for_order_without_items(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        // Get items by category
        $itemsByCategory = $this->repository->getOrderItemsByCategory($order);

        // Assert
        $this->assertCount(0, $itemsByCategory);
    }

    public function test_get_order_total(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => 100,
            'quantity' => 2,
            'total' => 200
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => 50,
            'quantity' => 1,
            'total' => 50
        ]);

        // Get order total
        $total = $this->repository->getOrderTotal($order);

        // Assert
        $this->assertEquals(250, $total);
    }

    public function test_get_order_total_returns_zero_for_order_without_items(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        // Get order total
        $total = $this->repository->getOrderTotal($order);

        // Assert
        $this->assertEquals(0, $total);
    }

    public function test_get_category_total(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        
        $items = collect([
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'unit_price' => 100,
                'quantity' => 2,
                'total' => 200
            ]),
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'unit_price' => 50,
                'quantity' => 1,
                'total' => 50
            ])
        ]);

        // Get category total
        $total = $this->repository->getCategoryTotal($items);

        // Assert
        $this->assertEquals(250, $total);
    }

    public function test_get_category_total_returns_zero_for_empty_collection(): void
    {
        // Get category total with empty collection
        $total = $this->repository->getCategoryTotal(collect());

        // Assert
        $this->assertEquals(0, $total);
    }
}
