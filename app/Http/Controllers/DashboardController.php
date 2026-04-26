<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Expense;
use App\Models\Part;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $todayTransactions = Transaction::with(['customer', 'vehicle', 'items'])
            ->whereDate('created_at', $today)
            ->latest()
            ->limit(5)
            ->get();

        $todayRevenue = Transaction::where('status', 'receipt')
            ->whereDate('created_at', $today)
            ->sum('total_amount');

        $activeInvoices = Transaction::where('status', 'invoice')->count();

        $pendingReceiptsAmount = Transaction::where('status', 'invoice')
            ->sum('total_amount');

        $pendingReceiptsCount = Transaction::where('status', 'invoice')->count();

        $lowStockItems = Part::whereColumn('stock', '<=', 'min_stock_threshold')
            ->orderBy('stock')
            ->limit(5)
            ->get();

        $criticalStockCount = Part::whereColumn('stock', '<=', 'min_stock_threshold')
            ->where('stock', '<=', 3)
            ->count();

        $weeklyRevenue = collect(range(0, 6))->map(function ($i) use ($startOfWeek) {
            $date = $startOfWeek->copy()->addDays($i);

            return [
                'label' => $date->format('D'),
                'date' => $date->toDateString(),
                'total' => Transaction::where('status', 'receipt')
                    ->whereDate('created_at', $date)
                    ->sum('total_amount'),
                'is_today' => $date->isToday(),
            ];
        });

        return response()->json([
            'summary' => [
                'today_revenue' => $todayRevenue,
                'active_invoices' => $activeInvoices,
                'pending_receipts_amount' => $pendingReceiptsAmount,
                'pending_receipts_count' => $pendingReceiptsCount,
                'low_stock_count' => Part::whereColumn('stock', '<=', 'min_stock_threshold')->count(),
                'critical_stock_count' => $criticalStockCount,
            ],
            'today_transactions' => $todayTransactions,
            'weekly_revenue' => $weeklyRevenue,
            'low_stock_items' => $lowStockItems,
            'recent_activity' => [],
        ]);
    }
}