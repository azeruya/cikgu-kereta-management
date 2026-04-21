<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // GET CUSTOMER LIST (fast, for table page)
public function index()
{
    $user = auth()->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $customers = Customer::query()
        ->where('branch_id', $user->branch_id)
        ->with([
            'vehicles:id,customer_id,license_plate'
        ])
        ->orderByDesc('id')
        ->paginate(10);

    return response()->json($customers);
}

    // CREATE CUSTOMER
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string'
        ]);

        $customer = Customer::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'branch_id' => $user->branch_id
        ]);

        return response()->json($customer, 201);
    }

    // SHOW ONE CUSTOMER (heavier, for detail/modal page)
    public function show($id)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $customer = Customer::query()
        ->where('branch_id', $user->branch_id)
        ->with([
            'vehicles:id,customer_id,license_plate,make,model,year',
            'latestTransaction:transactions.id,transactions.customer_id,transactions.status,transactions.created_at',
            'transactions' => function ($query) {
                $query->select(
                        'id',
                        'customer_id',
                        'vehicle_id',
                        'document_number',
                        'status',
                        'total_amount',
                        'created_at'
                    )
                    ->with([
                        'vehicle:id,license_plate,make,model,year'
                    ])
                    ->orderByDesc('id')
                    ->limit(5);
            }
        ])
        ->withCount('transactions')
        ->withSum('transactions', 'total_amount')
        ->findOrFail($id);

    return response()->json($customer);
}

    // UPDATE CUSTOMER
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string'
        ]);

        $customer = Customer::where('branch_id', $user->branch_id)
            ->findOrFail($id);

        $customer->update($request->only([
            'name', 'phone', 'email', 'address'
        ]));

        return response()->json($customer);
    }

    // DELETE CUSTOMER
    public function destroy($id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $customer = Customer::where('branch_id', $user->branch_id)
            ->findOrFail($id);

        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}