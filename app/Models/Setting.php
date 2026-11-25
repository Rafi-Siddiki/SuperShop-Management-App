<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Store settings stored as key/value rows, cached as one array.
 * Read with setting('delivery_fee'); defaults apply until an admin saves a value.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'store-settings';

    /** key => [default, validation rules, cast] */
    public const SCHEMA = [
        'store_name' => ['Supershop', 'required|string|max:60', 'string'],
        'store_tagline' => ['Groceries, gadgets, fashion and home essentials, delivered across Bangladesh.', 'nullable|string|max:160', 'string'],
        'store_address' => ['House 12, Road 4, Dhanmondi, Dhaka', 'required|string|max:160', 'string'],
        'store_phone' => ['09612-345678', 'required|string|max:30', 'string'],
        'store_email' => ['support@supershop.test', 'required|email|max:120', 'string'],
        'store_hours' => ['9am–10pm, every day', 'nullable|string|max:60', 'string'],
        'bin_number' => ['', 'nullable|string|max:30', 'string'],
        'delivery_fee' => [60, 'required|numeric|min:0|max:100000', 'money'],
        // 0 = never free.
        'free_delivery_threshold' => [2000, 'required|numeric|min:0|max:10000000', 'money'],
        'vat_rate' => [0, 'required|numeric|min:0|max:50', 'float'],
        'default_reorder_level' => [10, 'required|integer|min:0|max:100000', 'int'],
        'return_window_days' => [7, 'required|integer|min:0|max:365', 'int'],
        'receipt_footer' => ['Thank you for shopping with us! Exchange within 7 days with this receipt.', 'nullable|string|max:200', 'string'],
    ];

    /** All settings with defaults applied, cast to their types. */
    public static function values(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        $values = [];
        foreach (self::SCHEMA as $key => [$default, , $cast]) {
            $raw = array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '' ? $stored[$key] : $default;
            $values[$key] = match ($cast) {
                'int' => (int) $raw,
                'float', 'money' => round((float) $raw, 2),
                default => (string) $raw,
            };
        }

        return $values;
    }

    public static function value(string $key): mixed
    {
        if (! array_key_exists($key, self::SCHEMA)) {
            throw new \InvalidArgumentException("Unknown setting [$key].");
        }

        return static::values()[$key];
    }

    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::SCHEMA)) {
                static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
            }
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function rules(): array
    {
        return array_map(fn ($definition) => $definition[1], self::SCHEMA);
    }
}
