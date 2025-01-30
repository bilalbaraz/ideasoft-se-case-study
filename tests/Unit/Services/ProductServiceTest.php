<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    private ProductService $service;
    private ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->service = new ProductService($this->productRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_product(): void
    {
        // Arrange
        $product = new Product();
        $product->id = 1;

        $this->productRepository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($product);

        // Act
        $result = $this->service->getProduct(1);

        // Assert
        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals(1, $result->id);
    }

    public function test_decrease_stock(): void
    {
        // Arrange
        $product = new Product();
        $quantity = 5;

        $this->productRepository->shouldReceive('updateStock')
            ->once()
            ->with($product, -$quantity);

        // Act
        $this->service->decreaseStock($product, $quantity);
    }

    public function test_increase_stock(): void
    {
        // Arrange
        $product = new Product();
        $quantity = 5;

        $this->productRepository->shouldReceive('updateStock')
            ->once()
            ->with($product, $quantity);

        // Act
        $this->service->increaseStock($product, $quantity);
    }

    public function test_validate_stock_passes_when_enough_stock(): void
    {
        // Arrange
        $product = new Product();
        $quantity = 5;

        $this->productRepository->shouldReceive('hasStock')
            ->once()
            ->with($product, $quantity)
            ->andReturn(true);

        // Act
        $this->service->validateStock($product, $quantity);

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    public function test_validate_stock_throws_exception_when_not_enough_stock(): void
    {
        // Arrange
        $product = new Product();
        $product->name = 'Test Product';
        $quantity = 5;

        $this->productRepository->shouldReceive('hasStock')
            ->once()
            ->with($product, $quantity)
            ->andReturn(false);

        // Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Product Test Product does not have enough stock');

        // Act
        $this->service->validateStock($product, $quantity);
    }
}
