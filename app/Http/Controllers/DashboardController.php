<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Part;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $user->branch_id;
        $isAdmin = $user->role === 'admin';

        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        $startOfWeek = Carbon::now()->startOfWeek()->startOfDay();
        $endOfWeek = Carbon::now()->endOfWeek()->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Today's transactions
        |--------------------------------------------------------------------------
        */
        $todayTransactions = Transaction::select([
            'id',
            'customer_id',
            'vehicle_id',
            'branch_id',
            'status',
            'total_amount',
            'created_at',
            'updated_at',
        ])
        ->with([
            'customer:id,name',
            'vehicle:id,license_plate',
            'items.part:id,name',
        ])
        ->where('branch_id', $branchId)
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->latest()
        ->limit(5)
        ->get();

        /*
        |--------------------------------------------------------------------------
        | Transaction summary
        |--------------------------------------------------------------------------
        | One query for invoice count and pending invoice amount.
        */
        $transactionSummary = Transaction::where('branch_id', $branchId)
            ->selectRaw("
                COUNT(*) FILTER (WHERE status = 'invoice') AS active_invoices,
                COALESCE(SUM(total_amount) FILTER (WHERE status = 'invoice'), 0) AS pending_receipts_amount,
                COALESCE(SUM(total_amount) FILTER (
                    WHERE status = 'receipt'
                    AND created_at BETWEEN ? AND ?
                ), 0) AS today_revenue
            ", [$todayStart, $todayEnd])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Low stock
        |--------------------------------------------------------------------------
        | One query for count values, one query for the displayed list.
        */
        $lowStockStats = Part::where('branch_id', $branchId)
            ->whereColumn('stock', '<=', 'min_stock_threshold')
            ->selectRaw("
                COUNT(*) AS low_stock_count,
                COUNT(*) FILTER (WHERE stock <= 3) AS critical_stock_count
            ")
            ->first();

        $lowStockItems = Part::select([
                'id',
                'name',
                'stock',
                'min_stock_threshold',
                'branch_id',
            ])
            ->where('branch_id', $branchId)
            ->whereColumn('stock', '<=', 'min_stock_threshold')
            ->orderBy('stock')
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Recent activity
        |--------------------------------------------------------------------------
        */
        $recentTransactions = Transaction::select([
                'id',
                'customer_id',
                'vehicle_id',
                'user_id',
                'status',
                'quoted_at',
                'invoiced_at',
                'paid_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'customer:id,name',
                'vehicle:id,license_plate',
                'user:id,name',
            ])
            ->where('branch_id', $branchId)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function ($trx) {
                $staffName = e($trx->user?->name ?? 'Staff');
                $customer = e($trx->customer?->name ?? 'Customer');
                $plate = e($trx->vehicle?->license_plate ?? 'vehicle');

                $activityTime = match ($trx->status) {
                    'quotation' => $trx->quoted_at ?? $trx->created_at,
                    'invoice' => $trx->invoiced_at ?? $trx->updated_at,
                    'receipt' => $trx->paid_at ?? $trx->updated_at,
                    default => $trx->updated_at,
                };

                $actionText = match ($trx->status) {
                    'quotation' => 'created quotation',
                    'invoice' => 'converted quotation to invoice',
                    'receipt' => 'marked invoice as paid',
                    default => 'updated transaction',
                };

                return [
                    'text' => "<span class='act-bold'>{$staffName}</span> {$actionText} for {$plate} <span class='act-muted'>({$customer})</span>",
                    'time' => $activityTime?->diffForHumans(),
                    'dotClass' => match ($trx->status) {
                        'receipt' => 'dot-green',
                        'invoice' => 'dot-blue',
                        'quotation' => 'dot-amber',
                        default => 'dot-purple',
                    },
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Weekly revenue
        |--------------------------------------------------------------------------
        | One query instead of 7 queries.
        */
        $weeklyRevenue = [];

        if ($isAdmin) {
            $weeklyRows = Transaction::where('branch_id', $branchId)
                ->where('status', 'receipt')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->selectRaw('DATE(created_at) as revenue_date, COALESCE(SUM(total_amount), 0) as total')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->pluck('total', 'revenue_date');

            $weeklyRevenue = collect(range(0, 6))->map(function ($i) use ($startOfWeek, $weeklyRows) {
                $date = $startOfWeek->copy()->addDays($i);
                $dateString = $date->toDateString();

                return [
                    'label' => $date->format('D'),
                    'date' => $dateString,
                    'total' => (float) ($weeklyRows[$dateString] ?? 0),
                    'is_today' => $date->isToday(),
                ];
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */
        $summary = [
            'active_invoices' => (int) ($transactionSummary->active_invoices ?? 0),
            'pending_receipts_count' => (int) ($transactionSummary->active_invoices ?? 0),
            'low_stock_count' => (int) ($lowStockStats->low_stock_count ?? 0),
            'critical_stock_count' => (int) ($lowStockStats->critical_stock_count ?? 0),
        ];

        if ($isAdmin) {
            $summary['today_revenue'] = (float) ($transactionSummary->today_revenue ?? 0);
            $summary['pending_receipts_amount'] = (float) ($transactionSummary->pending_receipts_amount ?? 0);
        }

        return response()->json([
            'summary' => $summary,
            'today_transactions' => $todayTransactions,
            'weekly_revenue' => $weeklyRevenue,
            'low_stock_items' => $lowStockItems,
            'recent_activity' => $recentTransactions,
        ]);
    }
}