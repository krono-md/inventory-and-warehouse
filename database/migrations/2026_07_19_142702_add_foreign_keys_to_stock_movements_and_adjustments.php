<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove orphan rows before adding FK constraints
        DB::statement("DELETE FROM stock_movements WHERE performed_by IS NOT NULL AND performed_by NOT IN (SELECT id FROM users)");
        DB::statement("DELETE FROM stock_adjustments WHERE requested_by IS NOT NULL AND requested_by NOT IN (SELECT id FROM users)");
        DB::statement("DELETE FROM stock_adjustments WHERE approved_by IS NOT NULL AND approved_by NOT IN (SELECT id FROM users)");

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('performed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['performed_by']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['approved_by']);
        });
    }
};
