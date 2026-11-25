<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the inventory ledger. Rows are only ever created by
 * App\Services\InventoryService, never edited or deleted.
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const OPENING = 'opening';
    public const RECEIVED = 'received';
    public const SALE = 'sale';
    public const POS_SALE = 'pos_sale';
    public const CANCELLED = 'cancelled';
    public const RETURNED = 'returned';
    public const COUNT = 'count';
    public const DAMAGED = 'damaged';
    public const EXPIRED = 'expired';
    public const LOST = 'lost';

    /** type => [label, icon, tailwind classes] */
    public const TYPES = [
        self::OPENING => ['Opening stock', 'fa-flag', 'bg-slate-50 text-slate-700 ring-slate-500/20'],
        self::RECEIVED => ['Received', 'fa-truck-ramp-box', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        self::SALE => ['Online sale', 'fa-globe', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
        self::POS_SALE => ['In-store sale', 'fa-cash-register', 'bg-indigo-50 text-indigo-700 ring-indigo-600/20'],
        self::CANCELLED => ['Order cancelled', 'fa-rotate-left', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        self::RETURNED => ['Customer return', 'fa-arrow-rotate-left', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        self::COUNT => ['Stock count', 'fa-clipboard-check', 'bg-violet-50 text-violet-700 ring-violet-600/20'],
        self::DAMAGED => ['Damaged', 'fa-heart-crack', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
        self::EXPIRED => ['Expired', 'fa-hourglass-end', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
        self::LOST => ['Lost / theft', 'fa-person-running', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
    ];

    /** Reasons an admin can pick when writing stock off. */
    public const WRITE_OFF_REASONS = [self::DAMAGED, self::EXPIRED, self::LOST];

    protected $fillable = ['product_id', 'change', 'balance_after', 'type', 'order_id', 'user_id', 'unit_cost', 'note'];

    protected function casts(): array
    {
        return [
            'change' => 'integer',
            'balance_after' => 'integer',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst($this->type);
    }

    public function getIconAttribute(): string
    {
        return self::TYPES[$this->type][1] ?? 'fa-circle';
    }

    public function getStyleAttribute(): string
    {
        return self::TYPES[$this->type][2] ?? 'bg-slate-50 text-slate-700 ring-slate-500/20';
    }
}
