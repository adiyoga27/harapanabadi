<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Transaction;
use App\PurchaseLine;
use App\Variation;

class StockAdjustmentApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;
        $perPage = $request->get('per_page', 20);

        $adjustments = Transaction::with(['location', 'purchase_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'stock_adjustment')
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $adjustments->items(),
            'meta' => [
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
                'per_page' => $adjustments->perPage(),
                'total' => $adjustments->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $adjustment = Transaction::with(['location', 'purchase_lines', 'purchase_lines.product'])
            ->where('business_id', $business_id)
            ->where('type', 'stock_adjustment')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $adjustment]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'transaction_date' => 'required|date',
            'adjustment_type' => 'required|string|in:normal,abnormal',
            'products' => 'required|array',
            'products.*.variation_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric',
            'location_id' => 'required|integer',
        ]);

        $adjustment = Transaction::create([
            'business_id' => $business_id,
            'type' => 'stock_adjustment',
            'status' => 'final',
            'adjustment_type' => $request->adjustment_type,
            'transaction_date' => $request->transaction_date,
            'location_id' => $request->location_id,
            'ref_no' => $request->ref_no,
            'staff_note' => $request->staff_note,
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->products as $product) {
            $variation = Variation::findOrFail($product['variation_id']);

            PurchaseLine::create([
                'transaction_id' => $adjustment->id,
                'product_id' => $variation->product_id,
                'variation_id' => $product['variation_id'],
                'quantity' => $product['quantity'],
                'purchase_price' => $product['unit_price'] ?? 0,
            ]);
        }

        return response()->json(['success' => true, 'data' => $adjustment], 201);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $adjustment = Transaction::where('business_id', $business_id)
            ->where('type', 'stock_adjustment')
            ->findOrFail($id);

        $adjustment->delete();

        return response()->json(['success' => true, 'message' => 'Stock adjustment deleted.']);
    }
}
