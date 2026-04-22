<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Expense::query()
            ->where('branch_id', $user->branch_id);

        // 🔍 filters
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('category', 'like', "%$q%")
                    ->orWhere('description', 'like', "%$q%");
            });
        }

        $expenses = $query
            ->orderByDesc('expense_date')
            ->paginate(10);

        // 📊 summary (IMPORTANT)
        $total = (clone $query)->sum('amount');

        $monthly = (clone $query)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        return response()->json([
            ...$expenses->toArray(),
            'summary' => [
                'total' => $total,
                'monthly' => $monthly,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'receipt_file' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
            'expense_date' => 'required|date',
        ]);

        $path = null;

        if ($request->hasFile('receipt_file')) {
            $path = $request->file('receipt_file')->store('receipts', 'public');
        }

        $expense = Expense::create([
            'branch_id' => $user->branch_id,
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'receipt_file' => $path,
            'expense_date' => $validated['expense_date'],
        ]);

        return response()->json($expense, 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $expense = Expense::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($expense);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $expense = Expense::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'category' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'receipt_file' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
            'expense_date' => 'sometimes|required|date',
        ]);

        $path = $expense->receipt_file;

        if ($request->hasFile('receipt_file')) {
            $path = $request->file('receipt_file')->store('receipts', 'public');
        }

        $expense->update([
            'category' => $validated['category'] ?? $expense->category,
            'description' => $validated['description'] ?? $expense->description,
            'amount' => $validated['amount'] ?? $expense->amount,
            'receipt_file' => $path,
            'expense_date' => $validated['expense_date'] ?? $expense->expense_date,
        ]);

        return response()->json($expense);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $expense = Expense::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $expense->delete();

        return response()->json(['message' => 'Expense deleted']);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();

        $query = Expense::query()
            ->where('branch_id', $user->branch_id);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $q = trim($request->search);

            $query->where(function ($sub) use ($q) {
                $sub->where('category', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $filename = 'expenses-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Category', 'Description', 'Amount', 'Receipt File']);

            $query->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->chunk(200, function ($expenses) use ($handle) {
                    foreach ($expenses as $expense) {
                        fputcsv($handle, [
                            optional($expense->expense_date)->format('Y-m-d'),
                            $expense->category,
                            $expense->description,
                            $expense->amount,
                            $expense->receipt_file,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}