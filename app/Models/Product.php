<?php

namespace App\Models;

use App\Support\Barcode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'Food', 'Electronics', 'Clothing', 'Home & Kitchen', 'Books',
        'Toys', 'Beauty & Health', 'Sports', 'Automotive', 'Others',
    ];

    public const CATEGORY_ICONS = [
        'Food' => 'fa-basket-shopping',
        'Electronics' => 'fa-mobile-screen-button',
        'Clothing' => 'fa-shirt',
        'Home & Kitchen' => 'fa-kitchen-set',
        'Books' => 'fa-book-open',
        'Toys' => 'fa-puzzle-piece',
        'Beauty & Health' => 'fa-spa',
        'Sports' => 'fa-futbol',
        'Automotive' => 'fa-car',
        'Others' => 'fa-gem',
    ];

    /** Fallback reorder level used where a product-specific one is not loaded. */
    public const LOW_STOCK_THRESHOLD = 10;

    protected $fillable = [
        'name', 'sku', 'barcode', 'description', 'date', 'image', 'category',
        'price', 'cost_price', 'reorder_level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Products created without codes get a SKU and an internal EAN-13 barcode.
        // Both are derived from the id, so they are unique without any extra lookup.
        static::created(function (Product $product) {
            $codes = [];
            if (! filled($product->sku)) {
                $codes['sku'] = 'SKU-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT);
            }
            if (! filled($product->barcode)) {
                $codes['barcode'] = Barcode::internalFor($product->id);
            }
            if ($codes) {
                $product->forceFill($codes)->saveQuietly();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Products whose stock is at or below their own reorder level. */
    public function scopeNeedsReorder(Builder $query): Builder
    {
        return $query->whereHas('stock', fn ($s) => $s->whereColumn('stocks.quantity', '<=', 'products.reorder_level'));
    }

    /** Find a product by an exact barcode or SKU (what a scanner or cashier types). */
    public static function findByCode(?string $code, bool $withTrashed = false): ?self
    {
        // Barcodes drop spaces/dashes; SKUs keep their dashes and are stored upper-case.
        $barcode = Barcode::normalize($code);
        $sku = self::normalizeSku($code);
        if ($barcode === '' && $sku === '') {
            return null;
        }

        $query = $withTrashed ? static::withTrashed() : static::query();

        return $query->with('stock')
            ->where(fn ($q) => $q->where('barcode', $barcode)->orWhere('sku', $sku))
            ->orderByRaw('CASE WHEN barcode = ? THEN 0 ELSE 1 END', [$barcode])
            ->first();
    }

    public static function normalizeSku(?string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $sku));
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('sku', self::normalizeSku($term))
              ->orWhere('barcode', Barcode::normalize($term));
        });
    }

    public function scopeInCategory(Builder $query, ?string $category): Builder
    {
        return filled($category) ? $query->where('category', $category) : $query;
    }

    /** URL of the product image, or a neutral placeholder when none is stored. */
    public function getImageUrlAttribute(): string
    {
        return self::imageUrlFor($this->image);
    }

    public static function imageUrlFor(?string $image): string
    {
        if ($image && str_starts_with($image, 'http')) {
            return $image;
        }

        return $image
            ? asset('storage/' . $image)
            : asset('images/placeholder-product.svg');
    }

    public function getStockQuantityAttribute(): int
    {
        return (int) ($this->stock?->quantity ?? 0);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->reorder_threshold;
    }

    public function getReorderThresholdAttribute(): int
    {
        return (int) ($this->reorder_level ?? self::LOW_STOCK_THRESHOLD);
    }

    /** True when the product can be sold: listed, active and not deleted. */
    public function getIsSellableAttribute(): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    /** Gross margin in percent of the selling price, if a cost is known. */
    public function getMarginPercentAttribute(): ?float
    {
        if ($this->cost_price === null || (float) $this->price <= 0) {
            return null;
        }

        return round(((float) $this->price - (float) $this->cost_price) / (float) $this->price * 100, 1);
    }

    public function getBarcodeSvgAttribute(): ?string
    {
        return $this->barcode ? Barcode::svg($this->barcode) : null;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->stock_quantity <= 0;
    }

    public function getCategoryIconAttribute(): string
    {
        return self::CATEGORY_ICONS[$this->category] ?? 'fa-tag';
    }

    /**
     * Best sellers ranked by units sold (cancelled orders excluded).
     */
    public static function getTopProducts(?int $limit = null)
    {
        $query = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', Order::REVENUE_STATUSES)
            ->select(
                'products.id',
                'products.name',
                'products.category',
                'products.image',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.category', 'products.image')
            ->orderByDesc('total_quantity');

        return $limit ? $query->limit($limit)->get() : $query->get();
    }
}
