<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'discount', 'min_order_amount', 'max_uses', 'max_uses_per_customer', 'is_active', 'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'discount' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'max_uses_per_customer' => 'integer',
            'is_active' => 'boolean',
            'expiry_date' => 'datetime',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('expiry_date', '>', now());
    }

    /** Orders that used this coupon and still count (a cancelled order gives the use back). */
    public function usesQuery(): Builder
    {
        return Order::query()->where('coupon_id', $this->id)->where('status', '!=', Order::STATUS_CANCELLED);
    }

    /**
     * Why this coupon cannot be used for an order, or null when it can.
     * At checkout this runs while the coupon row is locked and $lockingReads
     * is true, so concurrent orders cannot push it past its usage limits.
     */
    public function problemFor(?User $user, float $subtotal, bool $lockingReads = false): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is not active.';
        }

        if ($this->isExpired()) {
            return 'This coupon has expired.';
        }

        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return 'This coupon needs a minimum order of ' . money($this->min_order_amount) . '.';
        }

        if ($this->max_uses !== null) {
            $query = $this->usesQuery();
            $used = ($lockingReads ? $query->sharedLock() : $query)->count();
            if ($used >= $this->max_uses) {
                return 'This coupon has reached its usage limit.';
            }
        }

        if ($this->max_uses_per_customer !== null && $user) {
            $query = $this->usesQuery()->where('user_id', $user->id);
            $used = ($lockingReads ? $query->sharedLock() : $query)->count();
            if ($used >= $this->max_uses_per_customer) {
                return 'You have already used this coupon the maximum number of times.';
            }
        }

        return null;
    }

    public function getDiscountLabelAttribute(): string
    {
        return rtrim(rtrim((string) $this->discount, '0'), '.') . '%';
    }
}
