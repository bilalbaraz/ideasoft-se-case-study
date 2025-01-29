<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Customer;
use App\Models\OrderItem;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'total'
    ];

    protected $casts = [
        'total' => 'decimal:2'
    ];

    /**
     * Get the customer that owns the order.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the items for the order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Calculate total price of the order
     */
    public function calculateTotal(): void
    {
        $this->total = $this->items->sum('total');
        $this->save();
    }

    /**
     * Check if order is eligible for total amount discount
     */
    public function isEligibleForTotalDiscount(float $minimumAmount = 1000): bool
    {
        return $this->total >= $minimumAmount;
    }

    /**
     * Get products grouped by category
     */
    public function getProductsByCategory(): array
    {
        return $this->items()
            ->with('product')
            ->get()
            ->groupBy(function ($item) {
                return $item->product->category_id;
            })
            ->toArray();
    }
}
