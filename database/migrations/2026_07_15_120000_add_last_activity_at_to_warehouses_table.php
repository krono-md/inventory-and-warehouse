<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('status');
            $table->timestamp('deactivated_at')->nullable()->after('last_activity_at');
            $table->index('last_activity_at');
            $table->index('status');
        });

        // Backfill: set last_activity_at to the most recent stock movement per warehouse
        DB::statement("
            UPDATE warehouses w
            SET last_activity_at = sub.latest
            FROM (
                SELECT warehouse_id, MAX(created_at) AS latest
                FROM stock_movements
                GROUP BY warehouse_id
            ) sub
            WHERE sub.warehouse_id = w.id
        ");
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropIndex(['status']);
            $table->dropColumn(['last_activity_at', 'deactivated_at']);
        });
    }
};
