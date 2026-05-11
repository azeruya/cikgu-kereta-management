<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Part;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $user->branch_id;
        $isAdmin = $user->role === 'admin';

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();

        $todayTransactions = Transaction::with([
                'customer:id,name',
                'vehicle:id,license_plate',
                'items.part:id,name',
            ])
            ->where('branch_id', $branchId)
            ->whereDate('created_at', $today)
            ->latest()
            ->limit(5)
            ->get();

        $activeInvoices = Transaction::where('branch_id', $branchId)
            ->where('status', 'invoice')
            ->count();

        $pendingReceiptsCount = Transaction::where('branch_id', $branchId)
            ->where('status', 'invoice')
            ->count();

        $lowStockQuery = Part::where('branch_id', $branchId)
            ->whereColumn('stock', '<=', 'min_stock_threshold');

        $lowStockItems = (clone $lowStockQuery)
            ->orderBy('stock')
            ->limit(5)
            ->get();

        $lowStockCount = (clone $lowStockQuery)->count();

        $criticalStockCount = (clone $lowStockQuery)
            ->where('stock', '<=', 3)
            ->count();

        $recentTransactions = Transaction::with([
                'customer:id,name',
                'vehicle:id,license_plate',
                'user:id,name',
            ])
            ->where('branch_id', $branchId)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function ($trx) {
                $user = $trx->user?->name ?? 'Staff';
                $customer = $trx->customer?->name ?? 'Customer';
                $plate = $trx->vehicle?->license_plate ?? 'vehicle';

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
                    'text' => "<span class='act-bold'>{$user}</span> {$actionText} for {$plate} <span class='act-muted'>({$customer})</span>",
                    'time' => $activityTime?->diffForHumans(),
                    'dotClass' => match ($trx->status) {
                        'receipt' => 'dot-green',
                        'invoice' => 'dot-blue',
                        'quotation' => 'dot-amber',
                        default => 'dot-purple',
                    },
                ];
            });

        $summary = [
            'active_invoices' => $activeInvoices,
            'pending_receipts_count' => $pendingReceiptsCount,
            'low_stock_count' => $lowStockCount,
            'critical_stock_count' => $criticalStockCount,
        ];

        $weeklyRevenue = [];

        if ($isAdmin) {
            $todayRevenue = Transaction::where('branch_id', $branchId)
                ->where('status', 'receipt')
                ->whereDate('created_at', $today)
                ->sum('total_amount');

            $pendingReceiptsAmount = Transaction::where('branch_id', $branchId)
                ->where('status', 'invoice')
                ->sum('total_amount');

            $summary['today_revenue'] = $todayRevenue;
            $summary['pending_receipts_amount'] = $pendingReceiptsAmount;

            $weeklyRevenue = collect(range(0, 6))->map(function ($i) use ($startOfWeek, $branchId) {
                $date = $startOfWeek->copy()->addDays($i);

                return [
                    'label' => $date->format('D'),
                    'date' => $date->toDateString(),
                    'total' => Transaction::where('branch_id', $branchId)
                        ->where('status', 'receipt')
                        ->whereDate('created_at', $date)
                        ->sum('total_amount'),
                    'is_today' => $date->isToday(),
                ];
            });
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