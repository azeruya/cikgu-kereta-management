<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $perPage = (int) $request->get('per_page', 8);
        $perPage = min(max($perPage, 5), 50);

        $query = Expense::query()
            ->where('branch_id', $user->branch_id)
            ->select([
                'id',
                'branch_id',
                'category',
                'description',
                'amount',
                'expense_date',
                'receipt_file',
                'created_at',
            ]);

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('category', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        return response()->json(
            $query
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate($perPage)
        );
    }

    public function summary(Request $request)
    {
        $user = $request->user();

        $query = Expense::query()
            ->where('branch_id', $user->branch_id);

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('category', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $summary = [
            'total_records' => (clone $query)->count(),
            'total_expenses' => (clone $query)->sum('amount'),
            'this_month' => (clone $query)
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
        ];

        $monthlyTrend = (clone $query)
            ->selectRaw("TO_CHAR(expense_date, 'Mon YYYY') as label")
            ->selectRaw("DATE_TRUNC('month', expense_date) as month_key")
            ->selectRaw("SUM(amount) as total")
            ->groupByRaw("DATE_TRUNC('month', expense_date), TO_CHAR(expense_date, 'Mon YYYY')")
            ->orderByRaw("DATE_TRUNC('month', expense_date)")
            ->limit(6)
            ->get();

        $categoryTotals = (clone $query)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'summary' => $summary,
            'monthly_trend' => $monthlyTrend,
            'category_totals' => $categoryTotals,
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
            $path = $this->uploadReceiptToSupabase($request->file('receipt_file'));
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
            // Delete old Supabase receipt first
            $this->deleteReceiptFromSupabase($expense->receipt_file);

            // Upload new receipt to Supabase
            $path = $this->uploadReceiptToSupabase($request->file('receipt_file'));
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

        $this->deleteReceiptFromSupabase($expense->receipt_file);

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

            // Helps Excel recognize comma-separated CSV
            fwrite($handle, "sep=,\n");

            fputcsv($handle, ['Date', 'Category', 'Description', 'Amount', 'Receipt File']);

            $query->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->chunk(200, function ($expenses) use ($handle) {
                    foreach ($expenses as $expense) {
                        fputcsv($handle, [
                            optional($expense->expense_date)->format('Y-m-d'),
                            $expense->category,
                            $expense->description ?? '',
                            number_format((float) $expense->amount, 2, '.', ''),
                            $expense->receipt_file ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function uploadReceiptToSupabase($file): string
    {
        $extension = $file->getClientOriginalExtension();

        $fileName = 'receipts/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;

        $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
        $bucket = env('SUPABASE_STORAGE_BUCKET', 'receipts');
        $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY');

        if (!$supabaseUrl || !$serviceKey) {
            throw new \Exception('Supabase Storage environment variables are missing.');
        }

        $uploadUrl = "{$supabaseUrl}/storage/v1/object/{$bucket}/{$fileName}";
        
            $response = Http::withToken($serviceKey)
                ->withHeaders([
                    'apikey' => $serviceKey,
                    'Content-Type' => $file->getMimeType(),
                    'x-upsert' => 'false',
                ])
                ->withBody(
                    file_get_contents($file->getRealPath()),
                    $file->getMimeType()
                )
                ->post($uploadUrl);

        if (!$response->successful()) {
            throw new \Exception('Failed to upload receipt to Supabase Storage: ' . $response->body());
        }

        return "{$supabaseUrl}/storage/v1/object/public/{$bucket}/{$fileName}";
    }

    private function deleteReceiptFromSupabase(?string $fileUrl): void
    {
        if (!$fileUrl) {
            return;
        }

        $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
        $bucket = env('SUPABASE_STORAGE_BUCKET', 'receipts');
        $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY');

        $prefix = "{$supabaseUrl}/storage/v1/object/public/{$bucket}/";

        // Only delete Supabase public storage URLs
        if (!str_starts_with($fileUrl, $prefix)) {
            return;
        }

        $filePath = str_replace($prefix, '', $fileUrl);

        if (!$filePath) {
            return;
        }

        Http::withToken($serviceKey)
            ->withHeaders([
                'apikey' => $serviceKey,
            ])
            ->delete("{$supabaseUrl}/storage/v1/object/{$bucket}/{$filePath}");
}