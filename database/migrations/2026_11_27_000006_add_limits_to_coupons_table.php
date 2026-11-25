<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->decimal('min_order_amount', 10, 2)->nullable()->after('discount');
            $table->unsignedInteger('max_uses')->nullable()->after('min_order_amount');
            $table->unsignedInteger('max_uses_per_customer')->nullable()->after('max_uses');
            $table->boolean('is_active')->default(true)->after('max_uses_per_customer');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['min_order_amount', 'max_uses', 'max_uses_per_customer', 'is_active']);
        });
    }
};
