<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $orderItem = new OrderItem();
        
        $this->assertEquals([
            'order_id',
            'product_id',
            'quantity',
            'unit_price',
            'total'
        ], $orderItem->getFillable());
    }

    public function test_casts_attributes(): void
    {
        $orderItem = new OrderItem();
        
        $this->assertEquals([
            'id' => 'integer',
            'order_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'deleted_at' => 'datetime'
        ], $orderItem->getCasts());
    }

    public function test_order_item_belongs_to_order(): void
    {
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id
        ]);

        $this->assertInstanceOf(Order::class, $orderItem->order);
        $this->assertEquals($order->id, $orderItem->order->id);
    }

    public function test_order_item_belongs_to_product(): void
    {
        $product = Product::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'product_id' => $product->id
        ]);

        $this->assertInstanceOf(Product::class, $orderItem->product);
        $this->assertEquals($product->id, $orderItem->product->id);
    }

    public function test_calculate_total(): void
    {
        $orderItem = OrderItem::factory()->create([
            'quantity' => 3,
            'unit_price' => 100
        ]);

        $orderItem->calculateTotal();

        $this->assertEquals(300, $orderItem->total);
    }

    public function test_set_quantity(): void
    {
        $orderItem = OrderItem::factory()->create([
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100
        ]);

        $orderItem->setQuantity(3);

        $this->assertEquals(3, $orderItem->quantity);
        $this->assertEquals(300, $orderItem->total);
    }

    public function test_set_unit_price(): void
    {
        $orderItem = OrderItem::factory()->create([
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200
        ]);

        $orderItem->setUnitPrice(150);

        $this->assertEquals(150, $orderItem->unit_price);
        $this->assertEquals(300, $orderItem->total);
    }

    public function test_order_item_uses_soft_deletes(): void
    {
        $orderItem = OrderItem::factory()->create();
        
        $orderItem->delete();
        
        $this->assertSoftDeleted($orderItem);
    }

    public function test_order_item_can_be_created(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->create();
        
        $data = [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.50,
            'total' => 201.00
        ];

        $orderItem = OrderItem::create($data);

        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertEquals($data['order_id'], $orderItem->order_id);
        $this->assertEquals($data['product_id'], $orderItem->product_id);
        $this->assertEquals($data['quantity'], $orderItem->quantity);
        $this->assertEquals($data['unit_price'], $orderItem->unit_price);
        $this->assertEquals($data['total'], $orderItem->total);
    }
}
