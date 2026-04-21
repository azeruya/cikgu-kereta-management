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

        $query = Vehicle::query()
            ->where('branch_id', $user->branch_id)
            ->with('customer:id,name,phone,email');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        $query->orderByDesc('id');

        if ($request->boolean('all')) {
            return response()->json(
                $query->get([
                    'id',
                    'customer_id',
                    'license_plate',
                    'make',
                    'model',
                    'year',
                    'branch_id',
                ])
            );
        }

        return response()->json(
            $query->paginate(10)
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $request->merge([
            'license_plate' => strtoupper($request->license_plate),
        ]);

        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'license_plate' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles')->where(function ($q) use ($user) {
                    return $q->where('branch_id', $user->branch_id);
                }),
            ],
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
        $request->merge([
            'license_plate' => strtoupper($request->license_plate),
        ]);

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
                Rule::unique('vehicles')
                ->where(function ($q) use ($user) {
                    return $q->where('branch_id', $user->branch_id);
                })
                ->ignore($vehicle->id),
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