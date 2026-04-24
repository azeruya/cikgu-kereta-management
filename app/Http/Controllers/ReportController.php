<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:quotation,invoice,receipt'],
            'payment_status' => ['nullable', 'in:paid,unpaid,partial'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $branchId = $user->branch_id;
        $perPage = (int) ($validated['per_page'] ?? 10);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $paymentSub = DB::table('payments')
            ->select(
                'transaction_id',
                DB::raw('SUM(amount_paid) as paid_amount')
            )
            ->groupBy('transaction_id');

        /*
        |--------------------------------------------------------------------------
        | Base transactions query
        |--------------------------------------------------------------------------
        */
        $baseQuery = Transaction::query()
            ->from('transactions')
            ->leftJoinSub($paymentSub, 'payment_summary', function ($join) {
                $join->on('transactions.id', '=', 'payment_summary.transaction_id');
            })
            ->where('transactions.branch_id', $branchId);

        $this->applyTransactionFilters($baseQuery, $request);

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */
        $summaryRow = (clone $baseQuery)
            ->selectRaw('COUNT(transactions.id) as total_transactions')
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(transactions.discount_amount), 0) as total_discount')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.discount_amount), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(COALESCE(payment_summary.paid_amount, 0)), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(GREATEST((transactions.total_amount - transactions.discount_amount) - COALESCE(payment_summary.paid_amount, 0), 0)), 0) as total_unpaid')
            ->first();

        $expenseQuery = Expense::query()
            ->where('branch_id', $branchId);

        $this->applyExpenseDateFilters($expenseQuery, $startDate, $endDate);

        $totalExpenses = (float) $expenseQuery->sum('amount');
        $totalRevenue = (float) ($summaryRow->total_revenue ?? 0);
        $estimatedProfit = $totalRevenue - $totalExpenses;

        /*
        |--------------------------------------------------------------------------
        | Paid vs unpaid
        |--------------------------------------------------------------------------
        */
        $paidVsUnpaid = [
            'paid' => (float) ($summaryRow->total_paid ?? 0),
            'unpaid' => (float) ($summaryRow->total_unpaid ?? 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | Revenue vs expense by period
        |--------------------------------------------------------------------------
        */
        $periodRevenue = Transaction::query()
            ->from('transactions')
            ->where('transactions.branch_id', $branchId);

        $this->applyTransactionFilters($periodRevenue, $request, false);

        $periodRevenue = $periodRevenue
            ->selectRaw("TO_CHAR(transactions.created_at, 'YYYY-MM') as label")
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.discount_amount), 0) as revenue')
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->keyBy('label');

        $periodExpenses = Expense::query()
            ->where('branch_id', $branchId);

        $this->applyExpenseDateFilters($periodExpenses, $startDate, $endDate);

        $periodExpenses = $periodExpenses
            ->selectRaw("TO_CHAR(expense_date, 'YYYY-MM') as label")
            ->selectRaw('COALESCE(SUM(amount), 0) as expense')
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->keyBy('label');

        $periodLabels = collect($periodRevenue->keys())
            ->merge($periodExpenses->keys())
            ->unique()
            ->sort()
            ->values();

        $periodChart = $periodLabels->map(function ($label) use ($periodRevenue, $periodExpenses) {
            $revenue = (float) optional($periodRevenue->get($label))->revenue;
            $expense = (float) optional($periodExpenses->get($label))->expense;

            return [
                'label' => $label,
                'revenue' => $revenue,
                'expense' => $expense,
                'profit' => $revenue - $expense,
            ];
        })->values();

        /*
        |--------------------------------------------------------------------------
        | Revenue by make
        |--------------------------------------------------------------------------
        */
        $revenueByMake = Transaction::query()
            ->from('transactions')
            ->join('vehicles', 'transactions.vehicle_id', '=', 'vehicles.id')
            ->where('transactions.branch_id', $branchId);

        $this->applyTransactionFilters($revenueByMake, $request, false);

        $revenueByMake = $revenueByMake
            ->selectRaw("COALESCE(vehicles.make, 'Unknown') as make")
            ->selectRaw('COUNT(transactions.id) as transactions_count')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.discount_amount), 0) as revenue')
            ->groupBy('vehicles.make')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'make' => $row->make ?: 'Unknown',
                    'transactions_count' => (int) $row->transactions_count,
                    'revenue' => (float) $row->revenue,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Revenue by model
        |--------------------------------------------------------------------------
        */
        $revenueByModel = Transaction::query()
            ->from('transactions')
            ->join('vehicles', 'transactions.vehicle_id', '=', 'vehicles.id')
            ->where('transactions.branch_id', $branchId);

        $this->applyTransactionFilters($revenueByModel, $request, false);

        $revenueByModel = $revenueByModel
            ->selectRaw("COALESCE(vehicles.model, 'Unknown') as model")
            ->selectRaw('COUNT(transactions.id) as transactions_count')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.discount_amount), 0) as revenue')
            ->groupBy('vehicles.model')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'model' => $row->model ?: 'Unknown',
                    'transactions_count' => (int) $row->transactions_count,
                    'revenue' => (float) $row->revenue,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Customer analytics
        |--------------------------------------------------------------------------
        */
        $customerAnalyticsBase = Transaction::query()
            ->from('transactions')
            ->join('customers', 'transactions.customer_id', '=', 'customers.id')
            ->where('transactions.branch_id', $branchId);

        $this->applyTransactionFilters($customerAnalyticsBase, $request, false);

        $uniqueCustomers = (clone $customerAnalyticsBase)
            ->distinct('transactions.customer_id')
            ->count('transactions.customer_id');

        $topCustomers = (clone $customerAnalyticsBase)
            ->selectRaw('customers.id, customers.name')
            ->selectRaw('COUNT(transactions.id) as transactions_count')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.discount_amount), 0) as revenue')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'transactions_count' => (int) $row->transactions_count,
                    'revenue' => (float) $row->revenue,
                ];
            });

        $repeatCustomers = (clone $customerAnalyticsBase)
            ->selectRaw('transactions.customer_id, COUNT(transactions.id) as trx_count')
            ->groupBy('transactions.customer_id')
            ->havingRaw('COUNT(transactions.id) > 1')
            ->get()
            ->count();

        $customerAnalytics = [
            'unique_customers' => $uniqueCustomers,
            'repeat_customers' => $repeatCustomers,
            'top_customers' => $topCustomers,
        ];

        /*
        |--------------------------------------------------------------------------
        | Vehicle analytics
        |--------------------------------------------------------------------------
        */
        $vehicleAnalyticsBase = Transaction::query()
            ->from('transactions')
            ->join('vehicles', 'transactions.vehicle_id', '=', 'vehicles.id')
            ->where('transactions.branch_id', $branchId);

        $this->applyTransactionFilters($vehicleAnalyticsBase, $request, false);

        $uniqueVehicles = (clone $vehicleAnalyticsBase)
            ->distinct('transactions.vehicle_id')
            ->count('transactions.vehicle_id');

        $topVehicles = (clone $vehicleAnalyticsBase)
            ->selectRaw('vehicles.id, vehicles.license_plate, vehicles.make, vehicles.model, vehicles.year')
            ->selectRaw('COUNT(transactions.id) as transactions_count')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.discount_amount), 0) as revenue')
            ->groupBy('vehicles.id', 'vehicles.license_plate', 'vehicles.make', 'vehicles.model', 'vehicles.year')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'license_plate' => $row->license_plate,
                    'make' => $row->make,
                    'model' => $row->model,
                    'year' => $row->year,
                    'transactions_count' => (int) $row->transactions_count,
                    'revenue' => (float) $row->revenue,
                ];
            });

        $vehicleAnalytics = [
            'unique_vehicles' => $uniqueVehicles,
            'top_vehicles' => $topVehicles,
        ];

        /*
        |--------------------------------------------------------------------------
        | Transactions table
        |--------------------------------------------------------------------------
        */
        $transactions = (clone $baseQuery)
            ->with([
                'customer:id,name',
                'vehicle:id,license_plate,make,model,year',
            ])
            ->select('transactions.*')
            ->selectRaw('COALESCE(payment_summary.paid_amount, 0) as paid_amount')
            ->orderByDesc('transactions.created_at')
            ->paginate($perPage);

        $transactions->getCollection()->transform(function ($trx) {
            $amountPayable = (float) $trx->total_amount - (float) $trx->discount_amount;
            $paidAmount = (float) ($trx->paid_amount ?? 0);
            $balance = max($amountPayable - $paidAmount, 0);

            return [
                'id' => $trx->id,
                'document_number' => $trx->document_number,
                'status' => $trx->status,
                'created_at' => optional($trx->created_at)?->toDateTimeString(),
                'quoted_at' => optional($trx->quoted_at)?->toDateTimeString(),
                'invoiced_at' => optional($trx->invoiced_at)?->toDateTimeString(),
                'paid_at' => optional($trx->paid_at)?->toDateTimeString(),

                'customer' => $trx->customer ? [
                    'id' => $trx->customer->id,
                    'name' => $trx->customer->name,
                ] : null,

                'vehicle' => $trx->vehicle ? [
                    'id' => $trx->vehicle->id,
                    'license_plate' => $trx->vehicle->license_plate,
                    'make' => $trx->vehicle->make,
                    'model' => $trx->vehicle->model,
                    'year' => $trx->vehicle->year,
                ] : null,

                'total_amount' => (float) $trx->total_amount,
                'discount_amount' => (float) $trx->discount_amount,
                'amount_payable' => $amountPayable,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'payment_status' => $this->resolvePaymentStatus($amountPayable, $paidAmount),
                'notes' => $trx->notes,
            ];
        });

        return response()->json([
            'summary' => [
                'total_transactions' => (int) ($summaryRow->total_transactions ?? 0),
                'gross_sales' => (float) ($summaryRow->gross_sales ?? 0),
                'total_discount' => (float) ($summaryRow->total_discount ?? 0),
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'estimated_profit' => $estimatedProfit,
                'total_paid' => (float) ($summaryRow->total_paid ?? 0),
                'total_unpaid' => (float) ($summaryRow->total_unpaid ?? 0),
            ],
            'paid_vs_unpaid' => $paidVsUnpaid,
            'period_chart' => $periodChart,
            'revenue_by_make' => $revenueByMake,
            'revenue_by_model' => $revenueByModel,
            'customer_analytics' => $customerAnalytics,
            'vehicle_analytics' => $vehicleAnalytics,
            'transactions' => $transactions,
        ]);
    }

    private function applyTransactionFilters($query, Request $request, bool $includePaymentStatus = true): void
    {
        if ($request->filled('start_date')) {
            $query->whereDate('transactions.created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('transactions.created_at', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('transactions.status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('transactions.document_number', 'ilike', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('license_plate', 'ilike', "%{$search}%")
                            ->orWhere('make', 'ilike', "%{$search}%")
                            ->orWhere('model', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($includePaymentStatus && $request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->whereRaw('COALESCE(payment_summary.paid_amount, 0) >= (transactions.total_amount - transactions.discount_amount)')
                    ->whereRaw('(transactions.total_amount - transactions.discount_amount) > 0');
            }

            if ($request->payment_status === 'unpaid') {
                $query->whereRaw('COALESCE(payment_summary.paid_amount, 0) = 0');
            }

            if ($request->payment_status === 'partial') {
                $query->whereRaw('COALESCE(payment_summary.paid_amount, 0) > 0')
                    ->whereRaw('COALESCE(payment_summary.paid_amount, 0) < (transactions.total_amount - transactions.discount_amount)');
            }
        }
    }

    private function applyExpenseDateFilters($query, ?string $startDate, ?string $endDate): void
    {
        if ($startDate) {
            $query->whereDate('expense_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('expense_date', '<=', $endDate);
        }
    }

    private function resolvePaymentStatus(float $amountPayable, float $paidAmount): string
    {
        if ($amountPayable <= 0) {
            return 'paid';
        }

        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($paidAmount < $amountPayable) {
            return 'partial';
        }

        return 'paid';
    }
}