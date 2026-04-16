<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;

class PartController extends Controller
{
    // GET ALL PARTS (branch-based)
    public function index()
    {
        $parts = Part::where('branch_id', auth()->user()->branch_id)->get();

        return response()->json($parts);
    }

    // CREATE PART
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'variant' => 'nullable|string',
            'vehicle_make' => 'nullable|string',
            'vehicle_model' => 'nullable|string',
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'stock' => 'required|integer',
            'min_stock_threshold' => 'required|integer',
            'image' => 'nullable|string'
        ]);

        $part = Part::create([
            'name' => $request->name,
            'variant' => $request->variant,
            'vehicle_make' => $request->vehicle_make,
            'vehicle_model' => $request->vehicle_model,
            'description' => $request->description,
            'cost_price' => $request->cost_price,
            'selling_price' => $request->selling_price,
            'stock' => $request->stock,
            'min_stock_threshold' => $request->min_stock_threshold,
            'image' => $request->image,
            'branch_id' => auth()->user()->branch_id
        ]);

        return response()->json($part);
    }

    // SHOW SINGLE PART
    public function show($id)
    {
        $part = Part::where('branch_id', auth()->user()->branch_id)
            ->findOrFail($id);

        return response()->json($part);
    }

    // UPDATE PART
    public function update(Request $request, $id)
    {
        $part = Part::where('branch_id', auth()->user()->branch_id)
            ->findOrFail($id);

        $part->update($request->only([
            'name',
            'variant',
            'vehicle_make',
            'vehicle_model',
            'description',
            'cost_price',
            'selling_price',
            'stock',
            'min_stock_threshold',
            'image'
        ]));

        return response()->json($part);
    }

    // DELETE PART
    public function destroy($id)
    {
        $part = Part::where('branch_id', auth()->user()->branch_id)
            ->findOrFail($id);

        $part->delete();

        return response()->json(['message' => 'Part deleted']);
    }

    // LOW STOCK ALERT 
    public function lowStock()
    {
        $parts = Part::where('branch_id', auth()->user()->branch_id)
            ->whereColumn('stock', '<=', 'min_stock_threshold')
            ->get();

        return response()->json($parts);
    }
}