<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop orphan requested_by_user_id column from stock_transfers
        if (Schema::hasColumn('stock_transfers', 'requested_by_user_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->dropForeign(['requested_by_user_id']);
                $table->dropColumn('requested_by_user_id');
            });
        }

        // 2. Add unique constraint to order_reservations
        // First clean up any duplicate reserved rows keeping only the latest
        DB::statement('DELETE FROM order_reservations WHERE id IN (
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY order_reference, item_id, warehouse_id, status
                    ORDER BY created_at DESC
                ) AS rn
                FROM order_reservations
            ) t WHERE t.rn > 1
        )');

        try {
            Schema::table('order_reservations', function (Blueprint $table) {
                $table->unique(['order_reference', 'item_id', 'warehouse_id', 'status'], 'uq_order_reservations_combo');
            });
        } catch (\Exception $e) {
            // Index may already exist
        }

        // 3. Fix stock_movements.created_at to use current timestamp as default
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable(false)->default(DB::raw('CURRENT_TIMESTAMP'))->change();
        });

        // 4. Change category cascadeOnDelete to restrictOnDelete
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('order_reservations', function (Blueprint $table) {
            $table->dropIndex('uq_order_reservations_combo');
            $table->dropUnique(['order_reference', 'item_id', 'warehouse_id', 'status']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable(false)->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }
};
