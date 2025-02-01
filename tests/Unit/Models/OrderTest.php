<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $order = new Order();
        
        $this->assertEquals([
            'customer_id',
            'total'
        ], $order->getFillable());
    }

    public function test_casts_attributes(): void
    {
        $order = new Order();
        
        $this->assertEquals([
            'id' => 'int',
            'total' => 'decimal:2',
            'deleted_at' => 'datetime'
        ], $order->getCasts());
    }

    public function test_order_belongs_to_customer(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id
        ]);

        $this->assertInstanceOf(Customer::class, $order->customer);
        $this->assertEquals($customer->id, $order->customer->id);
    }

    public function test_order_has_many_items(): void
    {
        $order = Order::factory()->create();
        $items = OrderItem::factory()->count(3)->create([
            'order_id' => $order->id
        ]);

        $this->assertCount(3, $order->items);
        $this->assertTrue($order->items->contains($items->first()));
        $this->assertInstanceOf(OrderItem::class, $order->items->first());
    }

    public function test_calculate_total(): void
    {
        $order = Order::factory()->create(['total' => 0]);
        
        // Create two order items with different totals
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200
        ]);
        
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => 300,
            'total' => 300
        ]);

        $order->calculateTotal();

        $this->assertEquals(500, $order->total);
    }

    public function test_is_eligible_for_total_discount(): void
    {
        $order = Order::factory()->create(['total' => 1500]);
        $this->assertTrue($order->isEligibleForTotalDiscount());
        
        $order = Order::factory()->create(['total' => 500]);
        $this->assertFalse($order->isEligibleForTotalDiscount());
        
        // Test with custom minimum amount
        $this->assertTrue($order->isEligibleForTotalDiscount(400));
        $this->assertFalse($order->isEligibleForTotalDiscount(600));
    }

    public function test_get_products_by_category(): void
    {
        $order = Order::factory()->create();
        
        // Create products in different categories
        $product1 = Product::factory()->create(['category_id' => 1]);
        $product2 = Product::factory()->create(['category_id' => 1]);
        $product3 = Product::factory()->create(['category_id' => 2]);
        
        // Create order items
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

        $productsByCategory = $order->getProductsByCategory();

        $this->assertCount(2, $productsByCategory);
        $this->assertCount(2, $productsByCategory[1]); // Category 1 has 2 products
        $this->assertCount(1, $productsByCategory[2]); // Category 2 has 1 product
    }

    public function test_order_uses_soft_deletes(): void
    {
        $order = Order::factory()->create();
        
        $order->delete();
        
        $this->assertSoftDeleted($order);
    }

    public function test_order_can_be_created(): void
    {
        $customer = Customer::factory()->create();
        $data = [
            'customer_id' => $customer->id,
            'total' => 1500.75
        ];

        $order = Order::create($data);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($data['customer_id'], $order->customer_id);
        $this->assertEquals($data['total'], $order->total);
    }
}
