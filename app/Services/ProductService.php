<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Get product by ID
     */
    public function getProduct(int $id): Product
    {
        return $this->productRepository->findById($id);
    }

    /**
     * Decrease product stock
     */
    public function decreaseStock(Product $product, int $quantity): void
    {
        $this->productRepository->updateStock($product, -$quantity);
    }

    /**
     * Increase product stock
     */
    public function increaseStock(Product $product, int $quantity): void
    {
        $this->productRepository->updateStock($product, $quantity);
    }

    /**
     * Check if product has enough stock
     * 
     * @throws ValidationException
     */
    public function validateStock(Product $product, int $quantity): void
    {
        if (!$this->productRepository->hasStock($product, $quantity)) {
            throw ValidationException::withMessages([
                'items' => ["Product {$product->name} does not have enough stock"]
            ]);
        }
    }
}
