<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->default(0)->after('status');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');
            $table->string('phone', 30)->nullable()->after('coupon_id');
            $table->string('shipping_address')->nullable()->after('phone');
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['subtotal', 'discount_amount', 'phone', 'shipping_address']);
        });
    }
};
