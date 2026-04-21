<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $vehicles = Vehicle::query()
            ->where('branch_id', $user->branch_id)
            ->with('customer:id,name,phone,email')
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json($vehicles);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'license_plate' => 'required|string|max:50|unique:vehicles,license_plate',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1950|max:2100',
        ]);

        $customer = Customer::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $validated['customer_id'])
            ->firstOrFail();

        $vehicle = Vehicle::create([
            'customer_id' => $customer->id,
            'license_plate' => strtoupper($validated['license_plate']),
            'make' => $validated['make'],
            'model' => $validated['model'],
            'year' => $validated['year'],
            'branch_id' => $user->branch_id,
        ]);

        return response()->json($vehicle, 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $vehicle = Vehicle::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->with('customer:id,name,phone,email,address')
            ->firstOrFail();

        return response()->json($vehicle);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $vehicle = Vehicle::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'license_plate' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'license_plate')->ignore($vehicle->id),
            ],
            'make' => 'sometimes|required|string|max:100',
            'model' => 'sometimes|required|string|max:100',
            'year' => 'sometimes|required|integer|min:1950|max:2100',
        ]);

        if (isset($validated['license_plate'])) {
            $validated['license_plate'] = strtoupper($validated['license_plate']);
        }

        $vehicle->update($validated);

        return response()->json($vehicle);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $vehicle = Vehicle::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted']);
    }
}