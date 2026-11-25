<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Audit trail: every status change of an order, who made it and when. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'id']);
        });

        // Existing orders: record their creation so every order has a timeline.
        DB::table('orders')->orderBy('id')->select('id', 'user_id', 'status', 'created_at', 'updated_at')
            ->chunkById(500, function ($orders) {
                $rows = [];
                foreach ($orders as $order) {
                    $rows[] = ['order_id' => $order->id, 'from_status' => null, 'to_status' => 'pending',
                        'user_id' => $order->user_id, 'note' => 'Order placed', 'created_at' => $order->created_at];
                    if ($order->status !== 'pending') {
                        $rows[] = ['order_id' => $order->id, 'from_status' => 'pending', 'to_status' => $order->status,
                            'user_id' => null, 'note' => 'Recorded before history tracking', 'created_at' => $order->updated_at];
                    }
                }
                DB::table('order_status_histories')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
