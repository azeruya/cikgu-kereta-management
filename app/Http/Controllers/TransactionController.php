<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array'
        ]);

        DB::beginTransaction();

        try {

            // 1. Create transaction (quotation first)
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

            // 2. Create items
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

            // 3. Update transaction total
            $transaction->update([
                'total_amount' => $total
            ]);

            DB::commit();

            return response()->json($transaction->load('items'));

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'message' => 'Transaction failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}