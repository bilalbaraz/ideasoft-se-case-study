<?php

namespace Tests\Unit\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\Order\StoreOrderRequest;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreOrderRequestTest extends TestCase
{
    use RefreshDatabase;

    private StoreOrderRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new StoreOrderRequest();
    }

    public function test_authorize(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules(): void
    {
        $rules = $this->request->rules();

        $this->assertEquals([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1'
        ], $rules);
    }

    public function test_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertEquals([
            'customer_id.required' => 'Müşteri ID\'si zorunludur.',
            'customer_id.exists' => 'Geçersiz müşteri ID\'si.',
            'items.required' => 'En az bir ürün eklenmelidir.',
            'items.array' => 'Ürünler liste formatında olmalıdır.',
            'items.min' => 'En az bir ürün eklenmelidir.',
            'items.*.product_id.required' => 'Ürün ID\'si zorunludur.',
            'items.*.product_id.exists' => 'Geçersiz ürün ID\'si.',
            'items.*.quantity.required' => 'Ürün miktarı zorunludur.',
            'items.*.quantity.integer' => 'Ürün miktarı tam sayı olmalıdır.',
            'items.*.quantity.min' => 'Ürün miktarı en az 1 olmalıdır.'
        ], $messages);
    }

    public function test_validation_passes_with_valid_data(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2
                ]
            ]
        ];

        $validator = app()->make('validator')->make($data, $this->request->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_with_invalid_customer(): void
    {
        $product = Product::factory()->create();

        $data = [
            'customer_id' => 999, // Non-existent customer
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2
                ]
            ]
        ];

        $validator = app()->make('validator')->make($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('customer_id', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_product(): void
    {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => 999, // Non-existent product
                    'quantity' => 2
                ]
            ]
        ];

        $validator = app()->make('validator')->make($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.0.product_id', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_quantity(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 0 // Invalid quantity
                ]
            ]
        ];

        $validator = app()->make('validator')->make($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.0.quantity', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_items(): void
    {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'items' => [] // Empty items array
        ];

        $validator = app()->make('validator')->make($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items', $validator->errors()->toArray());
    }
}
