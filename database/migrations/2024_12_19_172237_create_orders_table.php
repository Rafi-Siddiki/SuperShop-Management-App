<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Links to users table
            $table->string('status')->default('pending'); // Order status
            $table->timestamps(); // Automatically adds created_at and updated_at
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
