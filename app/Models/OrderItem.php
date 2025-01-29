<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Order;
use App\Models\Product;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'total'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    /**
     * Get the order that owns the item.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product for this item.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate total price for this item
     */
    public function calculateTotal(): void
    {
        $this->total = $this->quantity * $this->unit_price;
        $this->save();
    }

    /**
     * Set the quantity and recalculate total
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
        $this->calculateTotal();
    }

    /**
     * Set the unit price and recalculate total
     */
    public function setUnitPrice(float $unitPrice): void
    {
        $this->unit_price = $unitPrice;
        $this->calculateTotal();
    }
}
