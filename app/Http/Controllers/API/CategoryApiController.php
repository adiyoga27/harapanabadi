<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Category;

class CategoryApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;

        $categories = Category::where('business_id', $business_id)
            ->where('category_type', 'product')
            ->withCount('products')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $category = Category::with('products')
            ->where('business_id', $business_id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $category]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'name' => 'required|string',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'business_id' => $business_id,
            'short_code' => $request->short_code,
            'category_type' => 'product',
            'description' => $request->description,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $category = Category::where('business_id', $business_id)->findOrFail($id);
        $category->update($request->only(['name', 'short_code', 'description']));

        return response()->json(['success' => true, 'data' => $category]);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $category = Category::where('business_id', $business_id)->findOrFail($id);

        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Category has products and cannot be deleted.',
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }

    public function subCategories(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $subCategories = Category::where('business_id', $business_id)
            ->where('parent_id', $id)
            ->get();

        return response()->json(['success' => true, 'data' => $subCategories]);
    }
}
