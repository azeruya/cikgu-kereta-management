<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    // GET ALL VEHICLES (by branch)
    public function index()
    {
        $vehicles = Vehicle::where('branch_id', auth()->user()->branch_id)
            ->with('customer')
            ->get();

        return response()->json($vehicles);
    }

    // CREATE VEHICLE
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'license_plate' => 'required|string|unique:vehicles',
            'make' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|integer'
        ]);

        // 🔥 ensure customer belongs to same branch
        $customer = Customer::where('branch_id', auth()->user()->branch_id)
            ->findOrFail($request->customer_id);

        $vehicle = Vehicle::create([
            'customer_id' => $customer->id,
            'license_plate' => $request->license_plate,
            'make' => $request->make,
            'model' => $request->model,
            'year' => $request->year,
            'branch_id' => auth()->user()->branch_id
        ]);

        return response()->json($vehicle);
    }

    // SHOW VEHICLE
    public function show($id)
    {
        $vehicle = Vehicle::where('branch_id', auth()->user()->branch_id)
            ->with('customer')
            ->findOrFail($id);

        return response()->json($vehicle);
    }

    // UPDATE VEHICLE
    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::where('branch_id', auth()->user()->branch_id)
            ->findOrFail($id);

        $vehicle->update($request->only([
            'license_plate', 'make', 'model', 'year'
        ]));

        return response()->json($vehicle);
    }

    // DELETE VEHICLE
    public function destroy($id)
    {
        $vehicle = Vehicle::where('branch_id', auth()->user()->branch_id)
            ->findOrFail($id);

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted']);
    }
}