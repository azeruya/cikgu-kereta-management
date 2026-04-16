<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // GET ALL CUSTOMERS (by branch)
    public function index()
    {
        $customers = Customer::where('branch_id', auth()->user()->branch_id)
            ->with('vehicles')
            ->get();

        return response()->json($customers);
    }

    // CREATE CUSTOMER
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string'
        ]);

        $customer = Customer::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'branch_id' => auth()->user()->branch_id // 🔥 important
        ]);

        return response()->json($customer);
    }

    // SHOW ONE CUSTOMER
    public function show($id)
    {
        $customer = Customer::where('branch_id', auth()->user()->branch_id)
            ->with('vehicles')
            ->findOrFail($id);

        return response()->json($customer);
    }

    // UPDATE CUSTOMER
    public function update(Request $request, $id)
    {
        $customer = Customer::where('branch_id', auth()->user()->branch_id)
            ->findOrFail($id);

        $customer->update($request->only(['name', 'phone', 'email', 'address']));

        return response()->json($customer);
    }

    // DELETE CUSTOMER
    public function destroy($id)
    {
        $customer = Customer::where('branch_id', auth()->user()->branch_id)
            ->findOrFail($id);

        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}