<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly Product $product
    ) {
    }

    public function findById(int $id): Product
    {
        return $this->product->findOrFail($id);
    }

    public function updateStock(Product $product, int $quantity): void
    {
        $product->stock += $quantity;
        $product->save();
    }

    public function hasStock(Product $product, int $quantity): bool
    {
        return $product->stock >= $quantity;
    }
}
