<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 64)->nullable()->after('name');
            $table->string('barcode', 32)->nullable()->after('sku');
            $table->decimal('cost_price', 10, 2)->nullable()->after('price');
            $table->unsignedInteger('reorder_level')->default(10)->after('cost_price');
            $table->boolean('is_active')->default(true)->after('reorder_level');
        });

        // Give every existing product (including soft-deleted ones) a SKU and an
        // internal EAN-13 barcode before the unique indexes are added.
        DB::table('products')->orderBy('id')->select('id')->chunkById(500, function ($products) {
            foreach ($products as $product) {
                $payload = '20' . str_pad((string) $product->id, 10, '0', STR_PAD_LEFT);
                DB::table('products')->where('id', $product->id)->update([
                    'sku' => 'SKU-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                    'barcode' => $payload . $this->checkDigit($payload),
                ]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique('sku');
            $table->unique('barcode');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropUnique(['barcode']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sku', 'barcode', 'cost_price', 'reorder_level', 'is_active']);
        });
    }

    /** GS1 check digit (kept local so this migration never depends on app code). */
    private function checkDigit(string $digits): int
    {
        $sum = 0;
        $weight = 3;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $sum += (int) $digits[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10;
    }
};
