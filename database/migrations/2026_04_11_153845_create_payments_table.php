<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->decimal('amount_paid', 10, 2);
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->timestamp('payment_date')->useCurrent();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_payment_method_valid
            CHECK (payment_method IN ('cash', 'card', 'online'))
        ");

        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_amount_paid_positive
            CHECK (amount_paid > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};