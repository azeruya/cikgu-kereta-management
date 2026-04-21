<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $customers = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->with([
                'vehicles:id,customer_id,license_plate'
            ])
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->where(function ($query) use ($user) {
                    return $query->where('branch_id', $user->branch_id);
                }),
            ],
            'address' => 'nullable|string',
        ]);

        $customer = Customer::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'branch_id' => $user->branch_id,
        ]);

        return response()->json($customer, 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with([
                'vehicles:id,customer_id,license_plate,make,model,year',
                'transactions' => function ($query) {
                    $query->select(
                            'id',
                            'customer_id',
                            'vehicle_id',
                            'document_number',
                            'status',
                            'total_amount',
                            'discount_amount',
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
            ->firstOrFail();

        return response()->json($customer);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->ignore($customer->id)
                    ->where(function ($query) use ($user) {
                        return $query->where('branch_id', $user->branch_id);
                    }),
            ],
            'address' => 'nullable|string',
        ]);

        $customer->update($validated);

        return response()->json($customer);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}