<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $status = $request->query('status');

        $query = Transaction::query()
            ->where('branch_id', $user->branch_id)
            ->with([
                'customer:id,name,phone',
                'vehicle:id,license_plate,make,model,year',
            ])
            ->orderByDesc('id');

        if ($status && in_array($status, ['quotation', 'invoice', 'receipt'])) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(10));
    }

    public function show($id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $transaction = Transaction::query()
            ->where('branch_id', $user->branch_id)
            ->with([
                'customer:id,name,phone,email,address',
                'vehicle:id,customer_id,license_plate,make,model,year',
                'items',
                'payments',
            ])
            ->findOrFail($id);

        return response()->json($transaction);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array'
        ]);

        DB::beginTransaction();

        try {
            $transaction = Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'vehicle_id' => $request->vehicle_id,
                'customer_id' => $request->customer_id,
                'status' => 'quotation',
                'document_number' => 'TRX-' . time(),
                'total_amount' => 0,
                'discount_amount' => 0,
                'notes' => $request->notes
            ]);

            $total = 0;

            foreach ($request->items as $item) {
                $transactionItem = TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'part_id' => $item['part_id'] ?? null,
                    'item_type' => $item['item_type'],
                    'service_name' => $item['service_name'] ?? null,
                    'service_hours' => $item['service_hours'] ?? null,
                    'cost_price' => $item['cost_price'] ?? 0,
                    'selling_price' => $item['selling_price'],
                    'quantity' => $item['quantity'] ?? 1,
                    'total_price' => $item['selling_price'] * ($item['quantity'] ?? 1),
                    'note' => $item['note'] ?? null
                ]);

                $total += $transactionItem->total_price;
            }

            $transaction->update([
                'total_amount' => $total
            ]);

            DB::commit();

            return response()->json($transaction->load('items'), 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'message' => 'Transaction failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmInvoice($id)
    {
        DB::beginTransaction();

        try {
            $transaction = Transaction::where('branch_id', auth()->user()->branch_id)
                ->with('items')
                ->findOrFail($id);

            foreach ($transaction->items as $item) {
                if ($item->item_type === 'part') {
                    $part = Part::find($item->part_id);

                    if (!$part) {
                        throw new \Exception("Part not found");
                    }

                    if ($part->stock < $item->quantity) {
                        throw new \Exception("Not enough stock for {$part->name}");
                    }

                    $part->decrement('stock', $item->quantity);
                }
            }

            $transaction->update([
                'status' => 'invoice'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Invoice confirmed and stock deducted'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function markPaid($id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $transaction = Transaction::query()
            ->where('branch_id', $user->branch_id)
            ->findOrFail($id);

        $transaction->update([
            'status' => 'receipt'
        ]);

        return response()->json([
            'message' => 'Transaction marked as paid'
        ]);
    }
}