<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        $quantity = $this->faker->numberBetween(1, 5);
        
        return [
            'order_id' => Order::factory(),
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'total' => $product->price * $quantity,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => function (array $attributes) {
                return $attributes['created_at'];
            }
        ];
    }

    /**
     * Indicate that the order item belongs to a specific order
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    /**
     * Indicate that the order item is for a specific product
     */
    public function forProduct(Product $product): static
    {
        $quantity = $this->faker->numberBetween(1, 5);
        
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'unit_price' => $product->price,
            'total' => $product->price * $quantity,
            'quantity' => $quantity
        ]);
    }

    /**
     * Set specific quantity for the order item
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $unitPrice = $attributes['unit_price'] ?? Product::find($attributes['product_id'])->price;
            
            return [
                'quantity' => $quantity,
                'total' => $unitPrice * $quantity
            ];
        });
    }
}
