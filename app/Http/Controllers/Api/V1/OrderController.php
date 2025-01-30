<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\StoreOrderRequest;
use App\Http\Requests\Api\V1\Order\UpdateOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders.
     */
    public function index(): JsonResponse
    {
        $orders = Order::with(['customer', 'items.product'])->get();

        return response()->json([
            'data' => $orders
        ]);
    }

    /**
     * Store a newly created order in storage.
     * 
     * @throws ValidationException
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                // Create order
                $order = Order::create([
                    'customer_id' => $request->customer_id,
                    'total' => 0 // Will be calculated after adding items
                ]);

                // Add items to order
                foreach ($request->items as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    // Check stock
                    if (!$product->hasStock($item['quantity'])) {
                        throw ValidationException::withMessages([
                            'items' => ["Product {$product->name} does not have enough stock"]
                        ]);
                    }

                    // Create order item
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'total' => $product->price * $item['quantity']
                    ]);

                    // Decrease product stock
                    $product->decreaseStock($item['quantity']);
                }

                // Calculate order total
                $order->calculateTotal();

                // Load relationships
                $order->load(['customer', 'items.product']);

                return response()->json([
                    'data' => $order,
                    'message' => 'Order created successfully'
                ], 201);
            });
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
        $order->load(['customer', 'items.product']);

        return response()->json([
            'data' => $order
        ]);
    }

    /**
     * Update the specified order in storage.
     * 
     * @throws ValidationException
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        try {
            return DB::transaction(function () use ($order, $request) {
                // Restore previous product stocks
                foreach ($order->items as $item) {
                    $product = $item->product;
                    $product->stock += $item->quantity;
                    $product->save();
                }

                // Delete old items
                $order->items()->delete();

                // Add new items
                foreach ($request->items as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    // Check stock
                    if (!$product->hasStock($item['quantity'])) {
                        throw ValidationException::withMessages([
                            'items' => ["Product {$product->name} does not have enough stock"]
                        ]);
                    }

                    // Create order item
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'total' => $product->price * $item['quantity']
                    ]);

                    // Decrease product stock
                    $product->decreaseStock($item['quantity']);
                }

                // Calculate order total
                $order->calculateTotal();

                // Load relationships
                $order->load(['customer', 'items.product']);

                return response()->json([
                    'data' => $order,
                    'message' => 'Order updated successfully'
                ]);
            });
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
            return DB::transaction(function () use ($order) {
                // Restore product stocks
                foreach ($order->items as $item) {
                    $product = $item->product;
                    $product->stock += $item->quantity;
                    $product->save();
                }

                // Delete order (soft delete)
                $order->delete();

                return response()->json([
                    'message' => 'Order deleted successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting order',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
