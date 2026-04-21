<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('category');
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('receipt_file')->nullable();
            $table->date('expense_date');
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT expenses_amount_positive
            CHECK (amount > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};