<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Part;
use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
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

        return response()->json([
            ...$transaction->toArray(),
            'quotation_number' => $this->quotationNumber($transaction),
            'invoice_number' => $this->invoiceNumber($transaction),
            'receipt_number' => $this->receiptNumber($transaction),
        ]);
    }

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
                'document_number' => 'TRX-' . str_pad((string) time(), 10, '0', STR_PAD_LEFT),
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
                ->unique()
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
                    'service_hours' => null,
                    'cost_price' => 0,
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

    public function update(Request $request, $id)
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

        $transaction = Transaction::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with('items')
            ->firstOrFail();

        if ($transaction->status !== 'quotation') {
            return response()->json([
                'message' => 'Only quotations can be edited.'
            ], 422);
        }

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $validated['customer_id'])
            ->firstOrFail();

        $vehicle = Vehicle::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $validated['vehicle_id'])
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        return DB::transaction(function () use ($transaction, $validated, $customer, $vehicle, $user) {
            $transaction->items()->delete();

            $total = 0;

            $partIds = collect($validated['items'])
                ->where('item_type', 'part')
                ->pluck('part_id')
                ->filter()
                ->unique()
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
                    'service_hours' => null,
                    'cost_price' => 0,
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
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'total_amount' => $total,
            ]);

            return response()->json(
                $transaction->fresh([
                    'customer:id,name,phone',
                    'vehicle:id,license_plate,make,model,year',
                    'items.part:id,name,variant,sku',
                    'payments',
                ])
            );
        });
    }

    public function confirmInvoice(Request $request, $id)
    {
        $user = $request->user();

        $transaction = Transaction::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with('items.part')
            ->firstOrFail();

        if ($transaction->status !== 'quotation') {
            return response()->json([
                'message' => 'Only quotation transactions can be confirmed to invoice.'
            ], 422);
        }

        try {
            DB::transaction(function () use ($transaction, $user) {
                foreach ($transaction->items as $item) {
                    if ($item->item_type !== 'part' || !$item->part_id) {
                        continue;
                    }

                    $part = Part::query()
                        ->where('branch_id', $user->branch_id)
                        ->where('id', $item->part_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $qty = (int) $item->quantity;

                    if ($qty < 1) {
                        abort(422, "Invalid quantity for part {$part->name}.");
                    }

                    if ((int) $part->stock < $qty) {
                        abort(422, "Insufficient stock for part {$part->name}. Available: {$part->stock}, needed: {$qty}.");
                    }

                    $part->decrement('stock', $qty);
                }

                $transaction->update([
                    'status' => 'invoice',
                    'invoiced_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Failed to confirm quotation.'
            ], 422);
        }

        return response()->json([
            'message' => 'Transaction confirmed as invoice.',
            'transaction' => $transaction->fresh([
                'customer:id,name,phone,email,address',
                'vehicle:id,license_plate,make,model,year',
                'items.part:id,name,variant,sku',
                'payments',
            ]),
            'invoice_number' => $this->invoiceNumber($transaction),
        ]);
    }

    public function markPaid(Request $request, $id)
    {
        $user = $request->user();

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
        ]);

        $transaction = Transaction::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with('payments')
            ->firstOrFail();

        if ($transaction->status !== 'invoice') {
            return response()->json([
                'message' => 'Only invoice transactions can be marked as paid.'
            ], 422);
        }

        DB::transaction(function () use ($transaction, $validated) {
            $transaction->payments()->create([
                'amount_paid' => $validated['amount_paid'],
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'payment_date' => $validated['payment_date'] ?? now(),
            ]);

            $transaction->update([
                'status' => 'receipt',
                'paid_at' => $validated['payment_date'] ?? now(),
            ]);
        });

        return response()->json([
            'message' => 'Transaction marked as paid.',
            'transaction' => $transaction->fresh([
                'customer:id,name,phone,email,address',
                'vehicle:id,license_plate,make,model,year',
                'items.part:id,name,variant,sku',
                'payments',
            ]),
        ]);
    }

    protected function quotationNumber(Transaction $transaction): string
    {
        return 'QUO-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
    }

    protected function invoiceNumber(Transaction $transaction): string
    {
        return 'INV-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
    }

    protected function receiptNumber(Transaction $transaction): string
    {
        return 'REC-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
    }
}