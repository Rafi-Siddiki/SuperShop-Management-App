<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level guarantees behind the stock and cart logic:
 *  - exactly one stock row per product, and stock can never be negative;
 *  - one active cart per user, and one line per product in a cart.
 * Existing duplicates are cleaned up first so the indexes can be created.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Duplicate stock rows: keep the most recently updated one.
            $duplicates = DB::table('stocks')->select('product_id')->groupBy('product_id')->havingRaw('COUNT(*) > 1')->pluck('product_id');
            foreach ($duplicates as $productId) {
                $keep = DB::table('stocks')->where('product_id', $productId)->orderByDesc('updated_at')->orderByDesc('id')->value('id');
                DB::table('stocks')->where('product_id', $productId)->where('id', '!=', $keep)->delete();
            }

            DB::table('stocks')->where('quantity', '<', 0)->update(['quantity' => 0]);

            // Every product needs a stock row.
            $missing = DB::table('products')->leftJoin('stocks', 'stocks.product_id', '=', 'products.id')
                ->whereNull('stocks.id')->pluck('products.id');
            foreach ($missing as $productId) {
                DB::table('stocks')->insert(['product_id' => $productId, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }

            // Duplicate active carts: merge lines into the oldest cart.
            $users = DB::table('carts')->select('user_id', 'status')->groupBy('user_id', 'status')->havingRaw('COUNT(*) > 1')->get();
            foreach ($users as $row) {
                $ids = DB::table('carts')->where('user_id', $row->user_id)->where('status', $row->status)->orderBy('id')->pluck('id');
                DB::table('cart_items')->whereIn('cart_id', $ids->slice(1))->update(['cart_id' => $ids->first()]);
                DB::table('carts')->whereIn('id', $ids->slice(1))->delete();
            }

            // Duplicate lines for the same product in one cart: sum them into one line.
            $lines = DB::table('cart_items')->select('cart_id', 'product_id')->groupBy('cart_id', 'product_id')->havingRaw('COUNT(*) > 1')->get();
            foreach ($lines as $line) {
                $items = DB::table('cart_items')->where('cart_id', $line->cart_id)->where('product_id', $line->product_id)->orderBy('id')->get();
                DB::table('cart_items')->where('id', $items->first()->id)->update(['quantity' => $items->sum('quantity')]);
                DB::table('cart_items')->whereIn('id', $items->slice(1)->pluck('id'))->delete();
            }
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0)->change();
            $table->unique('product_id');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->unique(['user_id', 'status']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id']);
        });
    }

    /**
     * MySQL drops a foreign key's implicit index once a unique index covers the
     * same leading column, so each foreign key is dropped and re-created around
     * removing the unique index.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['cart_id']);
            $table->dropUnique(['cart_id', 'product_id']);
            $table->foreign('cart_id')->references('id')->on('carts')->cascadeOnDelete();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropUnique(['product_id']);
            $table->integer('quantity')->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
