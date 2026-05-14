<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('online_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            $table->string('source')->default('google_form');
            $table->string('external_row_hash')->unique();

            $table->timestamp('submitted_at')->nullable();
            $table->text('problem_description')->nullable();
            $table->boolean('terms_accepted')->default(false);

            $table->string('status')->default('new'); // new, reviewed, converted, dismissed
            $table->json('raw_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_requests');
    }
};
