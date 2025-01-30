<?php

namespace Tests\Unit\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Eloquent\ProductRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductRepository(new Product());
    }

    public function test_find_by_id(): void
    {
        // Create test data
        $product = Product::factory()->create();

        // Find product
        $foundProduct = $this->repository->findById($product->id);

        // Assert
        $this->assertInstanceOf(Product::class, $foundProduct);
        $this->assertEquals($product->id, $foundProduct->id);
    }

    public function test_find_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->repository->findById(999);
    }

    public function test_update_stock(): void
    {
        // Create test data
        $product = Product::factory()->create(['stock' => 10]);

        // Update stock
        $this->repository->updateStock($product, -2);

        // Assert
        $this->assertEquals(8, $product->fresh()->stock);

        // Update stock again
        $this->repository->updateStock($product, 5);

        // Assert
        $this->assertEquals(13, $product->fresh()->stock);
    }

    public function test_has_stock_returns_true_when_enough_stock(): void
    {
        // Create test data
        $product = Product::factory()->create(['stock' => 10]);

        // Check stock
        $result = $this->repository->hasStock($product, 5);

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_stock_returns_false_when_not_enough_stock(): void
    {
        // Create test data
        $product = Product::factory()->create(['stock' => 10]);

        // Check stock
        $result = $this->repository->hasStock($product, 15);

        // Assert
        $this->assertFalse($result);
    }

    public function test_has_stock_returns_true_when_exact_stock(): void
    {
        // Create test data
        $product = Product::factory()->create(['stock' => 10]);

        // Check stock
        $result = $this->repository->hasStock($product, 10);

        // Assert
        $this->assertTrue($result);
    }
}
