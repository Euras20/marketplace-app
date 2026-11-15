<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_code')->unique();
            $table->string('tracking_token_hash');
            $table->string('status')->default('approved'); // mirrors order fulfilment status
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'order_id']);
            $table->index(['store_id', 'tracking_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking');
    }
};
