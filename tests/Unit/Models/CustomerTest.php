<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $customer = new Customer();
        
        $this->assertEquals([
            'name',
            'since',
        ], $customer->getFillable());
    }

    public function test_casts_attributes(): void
    {
        $customer = new Customer();
        
        $this->assertEquals([
            'id' => 'int',
            'since' => 'date',
            'deleted_at' => 'datetime'
        ], $customer->getCasts());
    }

    public function test_customer_has_many_orders(): void
    {
        $customer = Customer::factory()->create();
        $orders = Order::factory()->count(3)->create([
            'customer_id' => $customer->id
        ]);

        $this->assertCount(3, $customer->orders);
        $this->assertTrue($customer->orders->contains($orders->first()));
        $this->assertInstanceOf(Order::class, $customer->orders->first());
    }

    public function test_customer_uses_soft_deletes(): void
    {
        $customer = Customer::factory()->create();
        
        $customer->delete();
        
        $this->assertSoftDeleted($customer);
    }

    public function test_customer_can_be_created(): void
    {
        $data = [
            'name' => 'John Doe',
            'since' => '2024-01-01',
        ];

        $customer = Customer::create($data);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals($data['name'], $customer->name);
        $this->assertEquals($data['since'], $customer->since->format('Y-m-d'));
    }
}
