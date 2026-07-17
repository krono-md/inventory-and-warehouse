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
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->renameColumn('quantity_on_hand', 'stock');
            $table->dropColumn('quantity_reserved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->renameColumn('stock', 'quantity_on_hand');
            $table->integer('quantity_reserved')->default(0);
        });
    }
};
