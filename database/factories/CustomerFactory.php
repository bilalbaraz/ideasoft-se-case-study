<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'since' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'revenue' => $this->faker->randomFloat(2, 0, 10000),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => function (array $attributes) {
                return $attributes['created_at'];
            }
        ];
    }

    /**
     * Indicate that the customer has specific revenue
     */
    public function withRevenue(float $revenue): static
    {
        return $this->state(fn (array $attributes) => [
            'revenue' => $revenue,
        ]);
    }
}
