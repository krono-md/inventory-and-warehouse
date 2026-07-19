<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receivings', function (Blueprint $table) {
            $table->dropUnique(['shipment_number']);
            $table->unique(['shipment_number', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_receivings', function (Blueprint $table) {
            $table->dropUnique(['shipment_number', 'item_id']);
            $table->string('shipment_number')->unique()->change();
        });
    }
};
