<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->integer('reorder_threshold')->default(10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->integer('reorder_threshold')->default(0)->change();
        });
    }
};
