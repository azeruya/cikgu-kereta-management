<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('supplier_name');
            $table->string('supplier_contact')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('pending');
            $table->date('order_date');
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE purchase_orders
            ADD CONSTRAINT purchase_orders_status_valid
            CHECK (status IN ('pending', 'received', 'cancelled'))
        ");

        DB::statement("
            ALTER TABLE purchase_orders
            ADD CONSTRAINT purchase_orders_total_amount_nonnegative
            CHECK (total_amount >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};