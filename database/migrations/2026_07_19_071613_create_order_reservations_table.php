<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('order_reference', 100);
            $table->string('source', 50)->default('api');
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('status', 20)->default('reserved');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['order_reference', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reservations');
    }
};
