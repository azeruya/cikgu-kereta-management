<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_vehicle_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->onDelete('cascade');
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->integer('year_from')->nullable();
            $table->integer('year_to')->nullable();
            $table->timestamps();

            $table->index('part_id', 'idx_part_vehicle_compat_part_id');
            $table->index(['make', 'model'], 'idx_part_vehicle_compat_make_model');
            $table->index(['make', 'model', 'year_from', 'year_to'], 'idx_part_vehicle_compat_make_model_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_vehicle_compatibilities');
    }
};