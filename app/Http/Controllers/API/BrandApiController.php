<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Brands;

class BrandApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;

        $brands = Brands::where('business_id', $business_id)
            ->withCount('products')
            ->get();

        return response()->json(['success' => true, 'data' => $brands]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $brand = Brands::with('products')
            ->where('business_id', $business_id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $brand]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'name' => 'required|string',
        ]);

        $brand = Brands::create([
            'name' => $request->name,
            'business_id' => $business_id,
            'description' => $request->description,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $brand], 201);
    }

    public function update(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $brand = Brands::where('business_id', $business_id)->findOrFail($id);
        $brand->update($request->only(['name', 'description']));

        return response()->json(['success' => true, 'data' => $brand]);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $brand = Brands::where('business_id', $business_id)->findOrFail($id);

        if ($brand->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Brand has products and cannot be deleted.',
            ], 422);
        }

        $brand->delete();

        return response()->json(['success' => true, 'message' => 'Brand deleted.']);
    }
}
