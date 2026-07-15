<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill: convert existing string values to user IDs where possible
        DB::table('stock_transfers')->orderBy('id')->chunk(100, function ($transfers) {
            foreach ($transfers as $transfer) {
                $updates = [];

                // approved_by
                if (!empty($transfer->approved_by)) {
                    $user = User::where('username', $transfer->approved_by)
                        ->orWhere('name', $transfer->approved_by)
                        ->first();
                    $updates['approved_by'] = $user?->id;
                } else {
                    $updates['approved_by'] = null;
                }

                // requested_by
                if (!empty($transfer->requested_by)) {
                    $user = User::where('username', $transfer->requested_by)
                        ->orWhere('name', $transfer->requested_by)
                        ->first();
                    $updates['requested_by'] = $user?->id;
                } else {
                    $updates['requested_by'] = null;
                }

                DB::table('stock_transfers')
                    ->where('id', $transfer->id)
                    ->update($updates);
            }
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'requested_by']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['requested_by']);
            $table->dropColumn(['approved_by', 'requested_by']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('approved_by')->nullable()->after('status');
            $table->string('requested_by')->nullable()->after('status');
        });
    }
};
