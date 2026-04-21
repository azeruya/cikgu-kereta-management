<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('item_type');
            $table->string('service_name')->nullable();
            $table->decimal('service_hours', 5, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE transaction_items
            ADD CONSTRAINT transaction_items_item_type_valid
            CHECK (item_type IN ('part', 'service'))
        ");

        DB::statement("
            ALTER TABLE transaction_items
            ADD CONSTRAINT transaction_items_quantity_positive
            CHECK (quantity > 0)
        ");

        DB::statement("
            ALTER TABLE transaction_items
            ADD CONSTRAINT transaction_items_prices_nonnegative
            CHECK (
                selling_price >= 0
                AND total_price >= 0
                AND (cost_price IS NULL OR cost_price >= 0)
            )
        ");

        DB::statement("
            ALTER TABLE transaction_items
            ADD CONSTRAINT transaction_items_service_hours_nonnegative
            CHECK (service_hours IS NULL OR service_hours >= 0)
        ");

        DB::statement("
            ALTER TABLE transaction_items
            ADD CONSTRAINT transaction_items_valid_item_shape
            CHECK (
                (item_type = 'part' AND part_id IS NOT NULL AND service_name IS NULL)
                OR
                (item_type = 'service' AND part_id IS NULL AND service_name IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};