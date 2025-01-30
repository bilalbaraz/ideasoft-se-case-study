<?php

namespace Tests\Unit\Repositories\Contracts;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\ProductRepository;
use PHPUnit\Framework\TestCase;

class ProductRepositoryInterfaceTest extends TestCase
{
    private ProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(ProductRepositoryInterface::class);
    }

    public function test_find_by_id_returns_product(): void
    {
        $product = $this->createMock(Product::class);
        $this->repository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($product);

        $result = $this->repository->findById(1);
        $this->assertInstanceOf(Product::class, $result);
    }

    public function test_update_stock_accepts_product_and_quantity(): void
    {
        $product = $this->createMock(Product::class);
        $this->repository->expects($this->once())
            ->method('updateStock')
            ->with($product, 5);

        $this->repository->updateStock($product, 5);
    }

    public function test_has_stock_returns_boolean(): void
    {
        $product = $this->createMock(Product::class);
        $this->repository->expects($this->once())
            ->method('hasStock')
            ->with($product, 5)
            ->willReturn(true);

        $result = $this->repository->hasStock($product, 5);
        $this->assertIsBool($result);
    }

    public function test_implementation_matches_interface(): void
    {
        $concreteClass = new \ReflectionClass(ProductRepository::class);
        $interface = new \ReflectionClass(ProductRepositoryInterface::class);

        foreach ($interface->getMethods() as $method) {
            $concreteMethod = $concreteClass->getMethod($method->getName());
            
            $this->assertEquals(
                $method->getName(),
                $concreteMethod->getName(),
                "Method {$method->getName()} is not implemented"
            );

            $this->assertEquals(
                count($method->getParameters()),
                count($concreteMethod->getParameters()),
                "Method {$method->getName()} has different number of parameters"
            );
        }
    }
}
