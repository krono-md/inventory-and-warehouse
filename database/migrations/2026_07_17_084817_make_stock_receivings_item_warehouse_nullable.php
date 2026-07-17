<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receivings', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['warehouse_id']);
        });

        Schema::table('stock_receivings', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->change();
            $table->foreignId('warehouse_id')->nullable()->change();
        });

        Schema::table('stock_receivings', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_receivings', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['warehouse_id']);
        });

        Schema::table('stock_receivings', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable(false)->change();
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });

        Schema::table('stock_receivings', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
        });
    }
};
