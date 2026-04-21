<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('variant')->nullable();
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->decimal('cost_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->integer('stock')->default(0);
            $table->integer('min_stock_threshold')->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_generic')->default(false);
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE parts
            ADD CONSTRAINT parts_stock_nonnegative
            CHECK (stock >= 0)
        ");

        DB::statement("
            ALTER TABLE parts
            ADD CONSTRAINT parts_min_stock_threshold_nonnegative
            CHECK (min_stock_threshold >= 0)
        ");

        DB::statement("
            ALTER TABLE parts
            ADD CONSTRAINT parts_prices_nonnegative
            CHECK (cost_price >= 0 AND selling_price >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};