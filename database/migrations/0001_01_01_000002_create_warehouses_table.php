<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('province', 100);
            $table->string('city', 100);
            $table->string('barangay', 100)->nullable();
            $table->text('address_description')->nullable();
            $table->string('country', 100)->default('Philippines');
            $table->integer('capacity_units');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
