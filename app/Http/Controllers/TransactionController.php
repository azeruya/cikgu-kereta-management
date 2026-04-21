<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Part;
use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    // 🔹 LIST (fast, paginated)
    public function index(Request $request)
    {
        $user = $request->user();

        $transactions = Transaction::query()
            ->forBranch($user->branch_id)
            ->status($request->status)
            ->with([
                'customer:id,name',
                'vehicle:id,license_plate'
            ])
            ->select([
                'id',
                'customer_id',
                'vehicle_id',
                'status',
                'total_amount',
                'discount_amount',
                'created_at'
            ])
            ->latest()
            ->paginate(15);

        return response()->json($transactions);
    }

    // 🔹 DETAIL
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $transaction = Transaction::query()
            ->forBranch($user->branch_id)
            ->where('id', $id)
            ->with([
                'customer:id,name,phone',
                'vehicle:id,license_plate,make,model,year',
                'items.part:id,name,variant',
                'payments'
            ])
            ->firstOrFail();

        return response()->json($transaction);
    }

    // 🔥 CREATE TRANSACTION (CRITICAL)
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'vehicle_id' => 'required|integer',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',

            'items.*.part_id' => 'nullable|integer',
            'items.*.service_name' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selling_price' => 'required|numeric|min:0',
        ]);

        $transaction = DB::transaction(function () use ($validated, $user) {

            // 🔹 validate ownership
            Customer::where('branch_id', $user->branch_id)
                ->findOrFail($validated['customer_id']);

            Vehicle::where('branch_id', $user->branch_id)
                ->findOrFail($validated['vehicle_id']);

            $totalAmount = 0;

            $transaction = Transaction::create([
                'branch_id' => $user->branch_id,
                'customer_id' => $validated['customer_id'],
                'vehicle_id' => $validated['vehicle_id'],
                'status' => 'quoted',
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'quoted_at' => now(),
            ]);

            foreach ($validated['items'] as $item) {

                $lineTotal = $item['quantity'] * $item['selling_price'];
                $totalAmount += $lineTotal;

                // 🔥 if it's a part → reduce stock
                if (!empty($item['part_id'])) {
                    $part = Part::where('branch_id', $user->branch_id)
                        ->findOrFail($item['part_id']);

                    $part->decrement('stock', $item['quantity']);
                }

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'part_id' => $item['part_id'] ?? null,
                    'service_name' => $item['service_name'] ?? null,
                    'quantity' => $item['quantity'],
                    'selling_price' => $item['selling_price'],
                    'total_price' => $lineTotal,
                ]);
            }

            $transaction->update([
                'total_amount' => $totalAmount
            ]);

            return $transaction->load([
                'items.part:id,name',
                'customer:id,name',
                'vehicle:id,license_plate'
            ]);
        });

        return response()->json($transaction, 201);
    }

    // 🔹 UPDATE STATUS (important for workflow)
    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => 'required|in:quoted,invoiced,paid'
        ]);

        $transaction = Transaction::query()
            ->forBranch($user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'invoiced') {
            $updateData['invoiced_at'] = now();
        }

        if ($validated['status'] === 'paid') {
            $updateData['paid_at'] = now();
        }

        $transaction->update($updateData);

        return response()->json($transaction);
    }

    // 🔹 DELETE (optional, but careful)
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $transaction = Transaction::query()
            ->forBranch($user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted']);
    }
}