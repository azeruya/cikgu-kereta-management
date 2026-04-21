<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE purchase_order_items
            ADD CONSTRAINT purchase_order_items_quantity_positive
            CHECK (quantity > 0)
        ");

        DB::statement("
            ALTER TABLE purchase_order_items
            ADD CONSTRAINT purchase_order_items_cost_price_nonnegative
            CHECK (cost_price IS NULL OR cost_price >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};