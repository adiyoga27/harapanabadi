<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Product;
use App\Variation;
use App\VariationLocationDetails;
use App\Category;
use App\Brands;
use App\Unit;
use App\TaxRate;
use App\Business;
use App\BusinessLocation;
use App\VariationTemplate;
use App\Barcode;
use App\SellingPriceGroup;
use App\Warranty;
use Illuminate\Support\Facades\DB;

class ProductApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;
        $perPage = $request->get('per_page', 20);

        $query = Product::with(['brand', 'unit', 'category', 'subCategory', 'variations', 'tax'])
            ->where('products.business_id', $business_id)
            ->where('products.type', '!=', 'modifier');

        if ($request->category_id) {
            $query->where('products.category_id', $request->category_id);
        }
        if ($request->brand_id) {
            $query->where('products.brand_id', $request->brand_id);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('products.name', 'like', "%{$request->search}%")
                  ->orWhere('products.sku', 'like', "%{$request->search}%");
            });
        }

        $products = $query->orderBy('products.name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function listProducts(Request $request)
    {
        $business_id = $request->user()->business_id;

        $products = Product::with(['variations', 'unit'])
            ->where('business_id', $business_id)
            ->where('type', '!=', 'modifier')
            ->where('status', 'active')
            ->select('id', 'name', 'sku', 'type', 'unit_id', 'category_id')
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $product = Product::with([
            'brand', 'unit', 'category', 'subCategory', 'tax',
            'variations', 'variations.variationLocationDetails', 'media',
        ])->where('business_id', $business_id)->findOrFail($id);

        return response()->json(['success' => true, 'data' => $product]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'name' => 'required|string',
            'sku' => 'required|string',
            'unit_id' => 'required|integer',
            'type' => 'required|string|in:single,variable',
            'tax' => 'nullable|integer|exists:tax_rates,id',
        ]);

        $product = Product::create([
            'name' => $request->name,
            'sku' => $request->sku,
            'business_id' => $business_id,
            'type' => $request->type,
            'unit_id' => $request->unit_id,
            'category_id' => $request->category_id,
            'sub_category_id' => $request->sub_category_id,
            'brand_id' => $request->brand_id,
            'tax' => $request->tax,
            'barcode_type' => $request->barcode_type ?? 'C128',
            'alert_quantity' => $request->alert_quantity,
            'enable_stock' => $request->enable_stock ?? 1,
            'weight' => $request->weight,
            'product_description' => $request->product_description,
            'created_by' => $request->user()->id,
            'status' => 'active',
        ]);

        return response()->json(['success' => true, 'data' => $product], 201);
    }

    public function update(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $product = Product::where('business_id', $business_id)->findOrFail($id);

        $product->update($request->only([
            'name', 'sku', 'unit_id', 'category_id', 'sub_category_id',
            'brand_id', 'tax', 'barcode_type', 'alert_quantity',
            'enable_stock', 'weight', 'product_description', 'type',
        ]));

        return response()->json(['success' => true, 'data' => $product]);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $product = Product::where('business_id', $business_id)->findOrFail($id);

        if ($product->purchaseLines()->exists() || $product->sellLines()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Product has transactions and cannot be deleted.',
            ], 422);
        }

        $product->delete();

        return response()->json(['success' => true, 'message' => 'Product deleted.']);
    }

    public function stockHistory(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $history = DB::table('variations as v')
            ->join('products as p', 'v.product_id', '=', 'p.id')
            ->leftJoin('variation_location_details as vld', 'vld.variation_id', '=', 'v.id')
            ->where('p.business_id', $business_id)
            ->where('p.id', $id)
            ->select('v.id', 'v.name as variation_name', 'v.sub_sku',
                DB::raw('COALESCE(vld.qty_available, 0) as qty_available'))
            ->get();

        return response()->json(['success' => true, 'data' => $history]);
    }

    public function variations(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $variations = Variation::whereHas('product', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })->where('product_id', $id)->with(['variationLocationDetails'])->get();

        return response()->json(['success' => true, 'data' => $variations]);
    }

    public function viewGroupPrice(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $groupPrices = DB::table('variation_group_prices as vgp')
            ->join('variations as v', 'v.id', '=', 'vgp.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('selling_price_groups as spg', 'spg.id', '=', 'vgp.price_group_id')
            ->where('p.business_id', $business_id)
            ->where('p.id', $id)
            ->select('vgp.*', 'v.name as variation_name', 'spg.name as price_group_name')
            ->get();

        return response()->json(['success' => true, 'data' => $groupPrices]);
    }

    public function activate(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $product = Product::where('business_id', $business_id)->findOrFail($id);
        $product->status = 'active';
        $product->save();

        return response()->json(['success' => true, 'message' => 'Product activated.']);
    }

    public function massDeactivate(Request $request)
    {
        $business_id = $request->user()->business_id;
        $request->validate(['selected_products' => 'required|array']);

        Product::where('business_id', $business_id)
            ->whereIn('id', $request->selected_products)
            ->update(['status' => 'inactive']);

        return response()->json(['success' => true, 'message' => 'Products deactivated.']);
    }

    public function massDelete(Request $request)
    {
        $business_id = $request->user()->business_id;
        $request->validate(['selected_products' => 'required|array']);

        Product::where('business_id', $business_id)
            ->whereIn('id', $request->selected_products)
            ->whereDoesntHave('purchaseLines')
            ->whereDoesntHave('sellLines')
            ->delete();

        return response()->json(['success' => true, 'message' => 'Products deleted.']);
    }

    public function getUnits(Request $request)
    {
        $business_id = $request->user()->business_id;
        $units = Unit::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $units]);
    }

    public function getTaxRates(Request $request)
    {
        $business_id = $request->user()->business_id;
        $taxRates = TaxRate::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $taxRates]);
    }

    public function getVariationTemplates(Request $request)
    {
        $business_id = $request->user()->business_id;
        $templates = VariationTemplate::where('business_id', $business_id)
            ->with('values')
            ->get();

        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function getBarcodes(Request $request)
    {
        $business_id = $request->user()->business_id;
        $barcodes = Barcode::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $barcodes]);
    }

    public function getSellingPriceGroups(Request $request)
    {
        $business_id = $request->user()->business_id;
        $groups = SellingPriceGroup::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $groups]);
    }

    public function getWarranties(Request $request)
    {
        $business_id = $request->user()->business_id;
        $warranties = Warranty::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $warranties]);
    }

    public function getLabelPreview(Request $request)
    {
        return response()->json(['success' => true, 'data' => []]);
    }
}
