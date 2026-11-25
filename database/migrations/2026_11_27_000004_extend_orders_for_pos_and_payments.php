<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders can now come from the online shop or the in-store till (POS), carry
 * a payment method/status and a delivery fee, and record which staff member
 * served them. Deleting a customer no longer deletes their sales history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('channel', 16)->default('online')->after('status');
            $table->string('payment_method', 16)->default('cod')->after('channel');
            $table->string('payment_status', 16)->default('unpaid')->after('payment_method');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('amount_tendered', 10, 2)->nullable()->after('total_amount');
            $table->decimal('change_due', 10, 2)->nullable()->after('amount_tendered');
            $table->foreignId('served_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable()->after('served_by');
            $table->string('notes', 500)->nullable()->after('shipping_address');
            // One key per till sale: a double-clicked "Complete sale" cannot record the sale twice.
            $table->string('idempotency_key', 64)->nullable()->unique()->after('notes');

            $table->index(['channel', 'created_at']);
        });

        // Cash on delivery is collected on delivery.
        DB::table('orders')->where('status', 'delivered')->update(['payment_status' => 'paid']);

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
            $table->decimal('cost', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('cost');
            $table->decimal('price', 8, 2)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['channel', 'created_at']);
            $table->dropUnique(['idempotency_key']);
            $table->dropForeign(['served_by']);
            $table->dropColumn(['channel', 'payment_method', 'payment_status', 'delivery_fee', 'amount_tendered',
                'change_due', 'served_by', 'customer_name', 'notes', 'idempotency_key']);
            $table->dropForeign(['user_id']);
        });

        // Orders without a customer (walk-in sales) cannot satisfy the old constraint.
        DB::table('orders')->whereNull('user_id')->delete();

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
