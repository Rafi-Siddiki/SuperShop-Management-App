<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Status changes and stock effects are handled by App\Services\OrderService;
 * never update `status` directly, or the stock ledger and audit trail drift.
 */
class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURNED = 'returned';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
        self::STATUS_RETURNED,
    ];

    /** Orders that count as sales in reports (cancelled and returned ones do not). */
    public const REVENUE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
    ];

    public const STATUS_STYLES = [
        self::STATUS_PENDING => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        self::STATUS_PROCESSING => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        self::STATUS_SHIPPED => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        self::STATUS_DELIVERED => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        self::STATUS_CANCELLED => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        self::STATUS_RETURNED => 'bg-orange-50 text-orange-700 ring-orange-600/20',
    ];

    public const STATUS_ICONS = [
        self::STATUS_PENDING => 'fa-hourglass-half',
        self::STATUS_PROCESSING => 'fa-box-open',
        self::STATUS_SHIPPED => 'fa-truck-fast',
        self::STATUS_DELIVERED => 'fa-circle-check',
        self::STATUS_CANCELLED => 'fa-ban',
        self::STATUS_RETURNED => 'fa-arrow-rotate-left',
    ];

    public const CHANNEL_ONLINE = 'online';
    public const CHANNEL_POS = 'pos';

    public const CHANNELS = [
        self::CHANNEL_ONLINE => ['Online', 'fa-globe'],
        self::CHANNEL_POS => ['In-store', 'fa-cash-register'],
    ];

    public const PAYMENT_METHODS = [
        'cod' => 'Cash on delivery',
        'cash' => 'Cash',
        'card' => 'Card',
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
    ];

    /** Methods a cashier can take at the till. */
    public const POS_PAYMENT_METHODS = ['cash', 'card', 'bkash', 'nagad'];

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_REFUNDED = 'refunded';
    public const PAYMENT_VOID = 'void';

    public const PAYMENT_STYLES = [
        self::PAYMENT_UNPAID => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        self::PAYMENT_PAID => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        self::PAYMENT_REFUNDED => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        self::PAYMENT_VOID => 'bg-slate-50 text-slate-600 ring-slate-500/20',
    ];

    protected $fillable = [
        'user_id', 'served_by', 'customer_name', 'status', 'channel', 'payment_method', 'payment_status',
        'subtotal', 'discount_amount', 'delivery_fee', 'total_amount', 'amount_tendered', 'change_due',
        'coupon_id', 'phone', 'shipping_address', 'notes', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
        ];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servedBy()
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function histories()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeRevenue(Builder $query): Builder
    {
        return $query->whereIn('status', self::REVENUE_STATUSES);
    }

    public function getNumberAttribute(): string
    {
        return 'SS-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getIsPosAttribute(): bool
    {
        return $this->channel === self::CHANNEL_POS;
    }

    public function getCustomerLabelAttribute(): string
    {
        return $this->user?->name ?? ($this->customer_name ?: ($this->is_pos ? 'Walk-in customer' : 'Deleted customer'));
    }

    public function getStatusStyleAttribute(): string
    {
        return self::STATUS_STYLES[$this->status] ?? 'bg-gray-50 text-gray-700 ring-gray-500/20';
    }

    public function getStatusIconAttribute(): string
    {
        return self::STATUS_ICONS[$this->status] ?? 'fa-circle';
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }

    /** Subtotal, falling back to the item sum for orders created before the column existed. */
    public function getItemsSubtotalAttribute(): float
    {
        if ((float) $this->subtotal > 0) {
            return (float) $this->subtotal;
        }

        return (float) $this->items->sum(fn ($item) => $item->price * $item->quantity);
    }

    public function getDiscountAttribute(): float
    {
        if ((float) $this->discount_amount > 0) {
            return (float) $this->discount_amount;
        }

        return max(0, round($this->items_subtotal + (float) $this->delivery_fee - (float) $this->total_amount, 2));
    }

    public function canBeCancelledByCustomer(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->channel === self::CHANNEL_ONLINE;
    }

    /**
     * Statuses a staff member may move this order to next.
     * Shipped orders can come back as returned (failed delivery / refused).
     */
    public function nextStatuses(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED => [self::STATUS_DELIVERED, self::STATUS_RETURNED],
            self::STATUS_DELIVERED => [self::STATUS_RETURNED],
            default => [],
        };
    }

    /** When the order was delivered / handed over, from the audit trail. */
    public function deliveredAt(): ?\Carbon\CarbonInterface
    {
        return $this->histories->where('to_status', self::STATUS_DELIVERED)->last()?->created_at;
    }

    /** Whether a delivered order is still inside the store's return window. */
    public function isWithinReturnWindow(): bool
    {
        $deliveredAt = $this->deliveredAt();

        return $deliveredAt === null || $deliveredAt->gte(now()->subDays((int) setting('return_window_days')));
    }
}
