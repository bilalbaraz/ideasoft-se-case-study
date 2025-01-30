<?php

namespace Tests\Unit\Repositories\Contracts;

use App\Models\Order;
use App\Repositories\Contracts\DiscountRepositoryInterface;
use App\Repositories\Eloquent\DiscountRepository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class DiscountRepositoryInterfaceTest extends TestCase
{
    private DiscountRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(DiscountRepositoryInterface::class);
    }

    public function test_get_order_items_by_category_returns_collection(): void
    {
        $order = $this->createMock(Order::class);
        $collection = new Collection();
        
        $this->repository->expects($this->once())
            ->method('getOrderItemsByCategory')
            ->with($order)
            ->willReturn($collection);

        $result = $this->repository->getOrderItemsByCategory($order);
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_order_total_returns_float(): void
    {
        $order = $this->createMock(Order::class);
        $this->repository->expects($this->once())
            ->method('getOrderTotal')
            ->with($order)
            ->willReturn(100.00);

        $result = $this->repository->getOrderTotal($order);
        $this->assertIsFloat($result);
    }

    public function test_get_category_total_returns_float(): void
    {
        $items = new Collection();
        $this->repository->expects($this->once())
            ->method('getCategoryTotal')
            ->with($items)
            ->willReturn(100.00);

        $result = $this->repository->getCategoryTotal($items);
        $this->assertIsFloat($result);
    }

    public function test_implementation_matches_interface(): void
    {
        $concreteClass = new \ReflectionClass(DiscountRepository::class);
        $interface = new \ReflectionClass(DiscountRepositoryInterface::class);

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
