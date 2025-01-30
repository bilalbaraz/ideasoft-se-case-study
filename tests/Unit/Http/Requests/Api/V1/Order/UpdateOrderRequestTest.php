<?php

namespace Tests\Unit\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\Order\UpdateOrderRequest;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateOrderRequestTest extends TestCase
{
    use RefreshDatabase;

    private UpdateOrderRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new UpdateOrderRequest();
    }

    public function test_authorize(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules(): void
    {
        $rules = $this->request->rules();

        $this->assertEquals([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1'
        ], $rules);
    }

    public function test_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertEquals([
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
        $product = Product::factory()->create();

        $data = [
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

    public function test_validation_fails_with_invalid_product(): void
    {
        $data = [
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
        $product = Product::factory()->create();

        $data = [
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
        $data = [
            'items' => [] // Empty items array
        ];

        $validator = app()->make('validator')->make($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_missing_product_id(): void
    {
        $data = [
            'items' => [
                [
                    'quantity' => 2 // Missing product_id
                ]
            ]
        ];

        $validator = app()->make('validator')->make($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.0.product_id', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_missing_quantity(): void
    {
        $product = Product::factory()->create();

        $data = [
            'items' => [
                [
                    'product_id' => $product->id // Missing quantity
                ]
            ]
        ];

        $validator = app()->make('validator')->make($data, $this->request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.0.quantity', $validator->errors()->toArray());
    }
}
