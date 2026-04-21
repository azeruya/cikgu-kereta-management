<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('license_plate')->unique();
            $table->string('make');
            $table->string('model');
            $table->integer('year');
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE vehicles
            ADD CONSTRAINT vehicles_year_reasonable
            CHECK (year >= 1950 AND year <= 2100)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};