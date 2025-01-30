<?php

namespace Tests\Unit\Repositories\Contracts;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Eloquent\OrderRepository;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class OrderRepositoryInterfaceTest extends TestCase
{
    private OrderRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(OrderRepositoryInterface::class);
    }

    public function test_get_all_with_relations_returns_collection(): void
    {
        $collection = new Collection();
        $this->repository->expects($this->once())
            ->method('getAllWithRelations')
            ->willReturn($collection);

        $result = $this->repository->getAllWithRelations();
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_find_with_relations_returns_order(): void
    {
        $order = $this->createMock(Order::class);
        $this->repository->expects($this->once())
            ->method('findWithRelations')
            ->with($order)
            ->willReturn($order);

        $result = $this->repository->findWithRelations($order);
        $this->assertInstanceOf(Order::class, $result);
    }

    public function test_create_returns_order(): void
    {
        $data = ['customer_id' => 1];
        $order = $this->createMock(Order::class);
        $this->repository->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($order);

        $result = $this->repository->create($data);
        $this->assertInstanceOf(Order::class, $result);
    }

    public function test_create_order_items_accepts_array(): void
    {
        $order = $this->createMock(Order::class);
        $items = [
            [
                'product_id' => 1,
                'quantity' => 2,
                'unit_price' => 100
            ]
        ];

        $this->repository->expects($this->once())
            ->method('createOrderItems')
            ->with($order, $items);

        $this->repository->createOrderItems($order, $items);
    }

    public function test_delete_order_items_accepts_order(): void
    {
        $order = $this->createMock(Order::class);
        $this->repository->expects($this->once())
            ->method('deleteOrderItems')
            ->with($order);

        $this->repository->deleteOrderItems($order);
    }

    public function test_delete_returns_boolean(): void
    {
        $order = $this->createMock(Order::class);
        $this->repository->expects($this->once())
            ->method('delete')
            ->with($order)
            ->willReturn(true);

        $result = $this->repository->delete($order);
        $this->assertIsBool($result);
    }

    public function test_update_total_accepts_order(): void
    {
        $order = $this->createMock(Order::class);
        $this->repository->expects($this->once())
            ->method('updateTotal')
            ->with($order);

        $this->repository->updateTotal($order);
    }

    public function test_implementation_matches_interface(): void
    {
        $concreteClass = new \ReflectionClass(OrderRepository::class);
        $interface = new \ReflectionClass(OrderRepositoryInterface::class);

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
