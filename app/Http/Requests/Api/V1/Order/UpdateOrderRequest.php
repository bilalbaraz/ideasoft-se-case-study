<?php

namespace App\Http\Requests\Api\V1\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'En az bir ürün eklenmelidir.',
            'items.array' => 'Ürünler liste formatında olmalıdır.',
            'items.min' => 'En az bir ürün eklenmelidir.',
            'items.*.product_id.required' => 'Ürün ID\'si zorunludur.',
            'items.*.product_id.exists' => 'Geçersiz ürün ID\'si.',
            'items.*.quantity.required' => 'Ürün miktarı zorunludur.',
            'items.*.quantity.integer' => 'Ürün miktarı tam sayı olmalıdır.',
            'items.*.quantity.min' => 'Ürün miktarı en az 1 olmalıdır.'
        ];
    }
}
