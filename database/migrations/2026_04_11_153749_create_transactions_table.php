<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('status')->default('quotation');
            $table->string('document_number')->unique();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE transactions
            ADD CONSTRAINT transactions_status_valid
            CHECK (status IN ('quotation', 'invoice', 'receipt'))
        ");

        DB::statement("
            ALTER TABLE transactions
            ADD CONSTRAINT transactions_amounts_nonnegative
            CHECK (total_amount >= 0 AND discount_amount >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};