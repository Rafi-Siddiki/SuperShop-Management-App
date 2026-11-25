<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    /** `cost` is the unit cost at the time of sale, so profit reports stay correct when costs change. */
    protected $fillable = ['order_id', 'product_id', 'quantity', 'price', 'cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** Include soft-deleted products so order history always renders. */
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->price * $this->quantity;
    }
}
