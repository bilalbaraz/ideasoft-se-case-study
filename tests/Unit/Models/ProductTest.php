<?php

namespace Tests\Unit\Models;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $product = new Product();
        
        $this->assertEquals([
            'name',
            'category_id',
            'price',
            'stock'
        ], $product->getFillable());
    }

    public function test_casts_attributes(): void
    {
        $product = new Product();
        
        $this->assertEquals([
            'price' => 'decimal:2',
            'stock' => 'integer',
            'category_id' => 'integer',
            'deleted_at' => 'datetime'
        ], $product->getCasts());
    }

    public function test_product_has_many_order_items(): void
    {
        $product = Product::factory()->create();
        $orderItems = OrderItem::factory()->count(3)->create([
            'product_id' => $product->id
        ]);

        $this->assertCount(3, $product->orderItems);
        $this->assertTrue($product->orderItems->contains($orderItems->first()));
        $this->assertInstanceOf(OrderItem::class, $product->orderItems->first());
    }

    public function test_has_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->assertTrue($product->hasStock(3));
        $this->assertTrue($product->hasStock(5));
        $this->assertFalse($product->hasStock(6));
    }

    public function test_decrease_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $product->decreaseStock(3);

        $this->assertEquals(2, $product->stock);
    }

    public function test_decrease_stock_throws_exception_when_insufficient(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not enough stock');

        $product->decreaseStock(6);
    }

    public function test_product_uses_soft_deletes(): void
    {
        $product = Product::factory()->create();
        
        $product->delete();
        
        $this->assertSoftDeleted($product);
    }

    public function test_product_can_be_created(): void
    {
        $data = [
            'name' => 'Test Product',
            'category_id' => 1,
            'price' => 99.99,
            'stock' => 100
        ];

        $product = Product::create($data);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals($data['name'], $product->name);
        $this->assertEquals($data['category_id'], $product->category_id);
        $this->assertEquals($data['price'], $product->price);
        $this->assertEquals($data['stock'], $product->stock);
    }
}
