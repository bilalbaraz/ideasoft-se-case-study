<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\StoreOrderRequest;
use App\Http\Requests\Api\V1\Order\UpdateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {
    }

    /**
     * Display a listing of the orders.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->orderService->getAllOrders()
        ]);
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder($request->validated());

            return response()->json([
                'data' => $order,
                'message' => 'Order created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $this->orderService->getOrder($order)
        ]);
    }

    /**
     * Update the specified order in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        try {
            $order = $this->orderService->updateOrder($order, $request->validated());

            return response()->json([
                'data' => $order,
                'message' => 'Order updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            $this->orderService->deleteOrder($order);

            return response()->json([
                'message' => 'Order deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting order',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
