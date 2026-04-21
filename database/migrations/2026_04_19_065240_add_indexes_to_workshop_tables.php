<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('branch_id', 'idx_customers_branch_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->index('customer_id', 'idx_vehicles_customer_id');
            $table->index('branch_id', 'idx_vehicles_branch_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('branch_id', 'idx_transactions_branch_id');
            $table->index(['branch_id', 'status'], 'idx_transactions_branch_status');
            $table->index('customer_id', 'idx_transactions_customer_id');
            $table->index(['customer_id', 'created_at'], 'idx_transactions_customer_created_at');
            $table->index(['branch_id', 'created_at'], 'idx_transactions_branch_created_at');
            $table->index('vehicle_id', 'idx_transactions_vehicle_id');
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->index('branch_id', 'idx_parts_branch_id');
            $table->index(['branch_id', 'stock'], 'idx_parts_branch_stock');
            $table->index(['branch_id', 'min_stock_threshold'], 'idx_parts_branch_min_stock');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->index('transaction_id', 'idx_transaction_items_transaction_id');
            $table->index('part_id', 'idx_transaction_items_part_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('transaction_id', 'idx_payments_transaction_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index('branch_id', 'idx_expenses_branch_id');
            $table->index(['branch_id', 'expense_date'], 'idx_expenses_branch_expense_date');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('branch_id', 'idx_purchase_orders_branch_id');
            $table->index(['branch_id', 'status'], 'idx_purchase_orders_branch_status');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->index('purchase_order_id', 'idx_purchase_order_items_purchase_order_id');
            $table->index('part_id', 'idx_purchase_order_items_part_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_branch_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('idx_vehicles_customer_id');
            $table->dropIndex('idx_vehicles_branch_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_branch_id');
            $table->dropIndex('idx_transactions_branch_status');
            $table->dropIndex('idx_transactions_customer_id');
            $table->dropIndex('idx_transactions_customer_created_at');
            $table->dropIndex('idx_transactions_branch_created_at');
            $table->dropIndex('idx_transactions_vehicle_id');
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->dropIndex('idx_parts_branch_id');
            $table->dropIndex('idx_parts_branch_stock');
            $table->dropIndex('idx_parts_branch_min_stock');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropIndex('idx_transaction_items_transaction_id');
            $table->dropIndex('idx_transaction_items_part_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_transaction_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_expenses_branch_id');
            $table->dropIndex('idx_expenses_branch_expense_date');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_purchase_orders_branch_id');
            $table->dropIndex('idx_purchase_orders_branch_status');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex('idx_purchase_order_items_purchase_order_id');
            $table->dropIndex('idx_purchase_order_items_part_id');
        });
    }
};