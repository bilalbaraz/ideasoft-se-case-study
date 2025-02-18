<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Order;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'since',
    ];

    protected $casts = [
        'since' => 'date',
    ];

    /**
     * Get all orders for the customer.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
