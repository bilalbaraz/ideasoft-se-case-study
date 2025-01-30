<?php

namespace App\Repositories\Contracts;

use App\Models\Product;

interface ProductRepositoryInterface
{
    /**
     * Find product by ID
     */
    public function findById(int $id): Product;

    /**
     * Update product stock
     */
    public function updateStock(Product $product, int $quantity): void;

    /**
     * Check if product has enough stock
     */
    public function hasStock(Product $product, int $quantity): bool;
}
