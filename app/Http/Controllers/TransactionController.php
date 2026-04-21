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

        'items.*.item_type' => 'required|in:part,service',
        'items.*.part_id' => 'nullable|integer',
        'items.*.service_name' => 'nullable|string|max:255',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.selling_price' => 'required|numeric|min:0',
        'items.*.note' => 'nullable|string',
    ]);

    return DB::transaction(function () use ($validated, $user) {

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $validated['customer_id'])
            ->firstOrFail();

        $vehicle = Vehicle::query()
            ->where('branch_id', $user->branch_id)
            ->where('customer_id', $customer->id)
            ->where('id', $validated['vehicle_id'])
            ->firstOrFail();

        $transaction = Transaction::create([
            'branch_id' => $user->branch_id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'quotation',
            'document_number' => 'TRX-' . now()->format('YmdHis'),
            'total_amount' => 0,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'quoted_at' => now(),
        ]);

        $total = 0;

        $partIds = collect($validated['items'])
            ->where('item_type', 'part')
            ->pluck('part_id')
            ->filter()
            ->values();

        $parts = Part::query()
            ->where('branch_id', $user->branch_id)
            ->whereIn('id', $partIds)
            ->get()
            ->keyBy('id');

        foreach ($validated['items'] as $item) {
            $sellingPrice = (float) $item['selling_price'];
            $quantity = (int) $item['quantity'];
            $lineTotal = $sellingPrice * $quantity;

            $itemData = [
                'item_type' => $item['item_type'],
                'part_id' => null,
                'service_name' => null,
                'quantity' => $quantity,
                'selling_price' => $sellingPrice,
                'total_price' => $lineTotal,
                'note' => $item['note'] ?? null,
            ];

            if ($item['item_type'] === 'part') {
                $part = $parts[$item['part_id']] ?? null;

                if (!$part) {
                    abort(422, 'Selected part is invalid for this branch.');
                }

                $itemData['part_id'] = $part->id;
                $itemData['cost_price'] = $part->cost_price;
            }

            if ($item['item_type'] === 'service') {
                $itemData['service_name'] = $item['service_name'] ?? null;
            }

            $transaction->items()->create($itemData);

            $total += $lineTotal;
        }

        $transaction->update([
            'total_amount' => $total
        ]);

        return response()->json(
            $transaction->load([
                'customer:id,name,phone',
                'vehicle:id,license_plate,make,model,year',
                'items.part:id,name,variant,sku'
            ]),
            201
        );
    });
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

    public function confirmInvoice(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->status !== 'quotation') {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $transaction->update([
            'status' => 'invoice',
            'invoiced_at' => now()
        ]);

        return response()->json($transaction);
    }

    public function markPaid(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->status !== 'invoice') {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $transaction->update([
            'status' => 'paid',
            'paid_at' => now()
        ]);

        return response()->json($transaction);
    }
}