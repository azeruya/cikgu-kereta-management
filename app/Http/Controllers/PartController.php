<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $parts = Part::query()
            ->forBranch($user->branch_id)
            ->with('compatibilities:id,part_id,make,model,year_from,year_to')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('variant', 'ilike', "%{$search}%")
                      ->orWhere('sku', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20);

        return response()->json($parts);
    }

    public function lowStock(Request $request)
    {
        $user = $request->user();

        $parts = Part::query()
            ->forBranch($user->branch_id)
            ->lowStock()
            ->orderBy('stock')
            ->get([
                'id',
                'name',
                'variant',
                'sku',
                'stock',
                'min_stock_threshold',
                'selling_price',
                'is_generic'
            ]);

        return response()->json($parts);
    }

    public function compatibleParts(Request $request, $vehicleId)
    {
        $user = $request->user();

        $vehicle = Vehicle::query()
            ->where('branch_id', $user->branch_id)
            ->where('id', $vehicleId)
            ->firstOrFail();

        $parts = Part::query()
            ->forBranch($user->branch_id)
            ->compatibleWithVehicle($vehicle)
            ->with('compatibilities:id,part_id,make,model,year_from,year_to')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'variant',
                'sku',
                'description',
                'cost_price',
                'selling_price',
                'stock',
                'min_stock_threshold',
                'image',
                'is_generic'
            ]);

        return response()->json($parts);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'variant' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock_threshold' => 'required|integer|min:0',
            'image' => 'nullable|string|max:255',
            'is_generic' => 'nullable|boolean',
            'compatibilities' => 'nullable|array',
            'compatibilities.*.make' => 'nullable|string|max:100',
            'compatibilities.*.model' => 'nullable|string|max:100',
            'compatibilities.*.year_from' => 'nullable|integer|min:1950|max:2100',
            'compatibilities.*.year_to' => 'nullable|integer|min:1950|max:2100',
        ]);

        $part = DB::transaction(function () use ($validated, $user) {
            $part = Part::create([
                'branch_id' => $user->branch_id,
                'name' => $validated['name'],
                'variant' => $validated['variant'] ?? null,
                'sku' => $validated['sku'] ?? null,
                'description' => $validated['description'] ?? null,
                'cost_price' => $validated['cost_price'],
                'selling_price' => $validated['selling_price'],
                'stock' => $validated['stock'],
                'min_stock_threshold' => $validated['min_stock_threshold'],
                'image' => $validated['image'] ?? null,
                'is_generic' => $validated['is_generic'] ?? false,
            ]);

            if (!empty($validated['compatibilities'])) {
                foreach ($validated['compatibilities'] as $compatibility) {
                    $part->compatibilities()->create([
                        'make' => $compatibility['make'] ?? null,
                        'model' => $compatibility['model'] ?? null,
                        'year_from' => $compatibility['year_from'] ?? null,
                        'year_to' => $compatibility['year_to'] ?? null,
                    ]);
                }
            }

            return $part->load('compatibilities:id,part_id,make,model,year_from,year_to');
        });

        return response()->json($part, 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $part = Part::query()
            ->forBranch($user->branch_id)
            ->where('id', $id)
            ->with('compatibilities:id,part_id,make,model,year_from,year_to')
            ->firstOrFail();

        return response()->json($part);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $part = Part::query()
            ->forBranch($user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'variant' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'cost_price' => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'min_stock_threshold' => 'sometimes|required|integer|min:0',
            'image' => 'nullable|string|max:255',
            'is_generic' => 'nullable|boolean',
            'compatibilities' => 'nullable|array',
            'compatibilities.*.make' => 'nullable|string|max:100',
            'compatibilities.*.model' => 'nullable|string|max:100',
            'compatibilities.*.year_from' => 'nullable|integer|min:1950|max:2100',
            'compatibilities.*.year_to' => 'nullable|integer|min:1950|max:2100',
        ]);

        $updatedPart = DB::transaction(function () use ($part, $validated) {
            $part->update([
                'name' => $validated['name'] ?? $part->name,
                'variant' => $validated['variant'] ?? $part->variant,
                'sku' => $validated['sku'] ?? $part->sku,
                'description' => $validated['description'] ?? $part->description,
                'cost_price' => $validated['cost_price'] ?? $part->cost_price,
                'selling_price' => $validated['selling_price'] ?? $part->selling_price,
                'stock' => $validated['stock'] ?? $part->stock,
                'min_stock_threshold' => $validated['min_stock_threshold'] ?? $part->min_stock_threshold,
                'image' => $validated['image'] ?? $part->image,
                'is_generic' => $validated['is_generic'] ?? $part->is_generic,
            ]);

            if (array_key_exists('compatibilities', $validated)) {
                $part->compatibilities()->delete();

                foreach ($validated['compatibilities'] as $compatibility) {
                    $part->compatibilities()->create([
                        'make' => $compatibility['make'] ?? null,
                        'model' => $compatibility['model'] ?? null,
                        'year_from' => $compatibility['year_from'] ?? null,
                        'year_to' => $compatibility['year_to'] ?? null,
                    ]);
                }
            }

            return $part->load('compatibilities:id,part_id,make,model,year_from,year_to');
        });

        return response()->json($updatedPart);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $part = Part::query()
            ->forBranch($user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $part->delete();

        return response()->json(['message' => 'Part deleted']);
    }

    public function restock(Request $request, $id)
    {
        $user = $request->user();

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $part = Part::query()
            ->forBranch($user->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $updatedPart = DB::transaction(function () use ($part, $validated) {
            $part->increment('stock', (int) $validated['quantity']);
            return $part->fresh()->load('compatibilities:id,part_id,make,model,year_from,year_to');
        });

        return response()->json([
            'message' => 'Part restocked successfully.',
            'part' => $updatedPart,
        ]);
    }
}