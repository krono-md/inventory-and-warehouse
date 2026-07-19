<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->text('address')->nullable()->after('name');
        });

        DB::statement('UPDATE warehouses SET address = CONCAT_WS(\', \', COALESCE(address_description, \'\'), COALESCE(barangay, \'\'), COALESCE(city, \'\'), COALESCE(province, \'\'), COALESCE(country, \'Philippines\'))');

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['province', 'city', 'barangay', 'address_description', 'country']);
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->text('address_description')->nullable();
            $table->string('country', 100)->default('Philippines');
        });

        DB::statement("UPDATE warehouses SET province = '', city = '', barangay = '', address_description = address, country = 'Philippines'");

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
