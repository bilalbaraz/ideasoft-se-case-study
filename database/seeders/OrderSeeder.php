<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = [
            [
                'id' => 1,
                'customer_id' => 1,
                'items' => [
                    [
                        'product_id' => 102,
                        'quantity' => 10,
                        'unit_price' => '11.28',
                        'total' => '112.80'
                    ]
                ],
                'total' => '112.80'
            ],
            [
                'id' => 2,
                'customer_id' => 2,
                'items' => [
                    [
                        'product_id' => 101,
                        'quantity' => 2,
                        'unit_price' => '49.50',
                        'total' => '99.00'
                    ],
                    [
                        'product_id' => 100,
                        'quantity' => 1,
                        'unit_price' => '120.75',
                        'total' => '120.75'
                    ]
                ],
                'total' => '219.75'
            ],
            [
                'id' => 3,
                'customer_id' => 3,
                'items' => [
                    [
                        'product_id' => 102,
                        'quantity' => 6,
                        'unit_price' => '11.28',
                        'total' => '67.68'
                    ],
                    [
                        'product_id' => 100,
                        'quantity' => 10,
                        'unit_price' => '120.75',
                        'total' => '1207.50'
                    ]
                ],
                'total' => '1275.18'
            ]
        ];

        foreach ($orders as $orderData) {
            $items = $orderData['items'];
            unset($orderData['items']);
            
            $order = Order::create($orderData);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total']
                ]);
            }
        }
    }
}
