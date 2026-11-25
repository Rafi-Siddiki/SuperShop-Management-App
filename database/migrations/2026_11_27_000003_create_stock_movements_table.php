<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory ledger: one row for every change to a product's stock.
 * For each product, the sum of `change` equals the current stock and
 * `balance_after` is the stock level right after that movement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('change');
            $table->unsignedInteger('balance_after');
            $table->string('type', 32);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'id']);
            $table->index(['type', 'created_at']);
            $table->index('created_at');
        });

        // Existing installs: record current stock as the opening balance.
        $now = now();
        DB::table('stocks')->where('quantity', '>', 0)->orderBy('id')->chunkById(500, function ($stocks) use ($now) {
            DB::table('stock_movements')->insert($stocks->map(fn ($s) => [
                'product_id' => $s->product_id,
                'change' => $s->quantity,
                'balance_after' => $s->quantity,
                'type' => 'opening',
                'note' => 'Opening balance when stock tracking was enabled',
                'created_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
