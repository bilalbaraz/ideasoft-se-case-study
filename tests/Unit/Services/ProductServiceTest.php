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

    /** @test */
    public function it_should_decrease_stock_correctly(): void
    {
        // Arrange
        $product = new Product();
        $product->id = 1;
        $product->stock = 10;
        $quantity = 5;

        $this->productRepository
            ->shouldReceive('updateStock')
            ->once()
            ->with(Mockery::on(function ($arg) use ($product) {
                return $arg->id === $product->id;
            }), -5)
            ->andReturnNull();

        // Act
        $this->service->decreaseStock($product, $quantity);

        // Assert - verify mock expectations
        $this->assertTrue(true); // Mock verification will fail if expectations aren't met
    }

    /** @test */
    public function it_should_increase_stock_correctly(): void
    {
        // Arrange
        $product = new Product();
        $product->id = 1;
        $product->stock = 5;
        $quantity = 3;

        $this->productRepository
            ->shouldReceive('updateStock')
            ->once()
            ->with(Mockery::on(function ($arg) use ($product) {
                return $arg->id === $product->id;
            }), 3)
            ->andReturnNull();

        // Act
        $this->service->increaseStock($product, $quantity);

        // Assert - verify mock expectations
        $this->assertTrue(true); // Mock verification will fail if expectations aren't met
    }

    public function test_validate_stock_with_sufficient_stock(): void
    {
        // Arrange
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';
        $quantity = 5;

        $this->productRepository->shouldReceive('hasStock')
            ->once()
            ->with($product, $quantity)
            ->andReturnTrue();

        // Act
        $this->service->validateStock($product, $quantity);
        
        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    public function test_validate_stock_with_insufficient_stock(): void
    {
        // Arrange
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';
        $quantity = 5;

        $this->productRepository->shouldReceive('hasStock')
            ->once()
            ->with($product, $quantity)
            ->andReturnFalse();

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Product Test Product does not have enough stock');
        
        $this->service->validateStock($product, $quantity);
    }
}
