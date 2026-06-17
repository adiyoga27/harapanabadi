<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Transaction;
use App\PurchaseLine;
use App\Variation;

class OpeningStockApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;

        $openingStocks = Transaction::with(['purchase_lines', 'location'])
            ->where('business_id', $business_id)
            ->where('type', 'opening_stock')
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $openingStocks]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $stock = Transaction::with(['purchase_lines', 'purchase_lines.product', 'location'])
            ->where('business_id', $business_id)
            ->where('type', 'opening_stock')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $stock]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'transaction_date' => 'required|date',
            'products' => 'required|array',
            'products.*.variation_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric',
            'products.*.purchase_price' => 'required|numeric',
            'location_id' => 'required|integer',
        ]);

        $openingStock = Transaction::create([
            'business_id' => $business_id,
            'type' => 'opening_stock',
            'status' => 'final',
            'transaction_date' => $request->transaction_date,
            'location_id' => $request->location_id,
            'ref_no' => $request->ref_no,
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->products as $product) {
            $variation = Variation::findOrFail($product['variation_id']);

            PurchaseLine::create([
                'transaction_id' => $openingStock->id,
                'product_id' => $variation->product_id,
                'variation_id' => $product['variation_id'],
                'quantity' => $product['quantity'],
                'purchase_price' => $product['purchase_price'],
                'purchase_price_inc_tax' => $product['purchase_price'],
            ]);
        }

        return response()->json(['success' => true, 'data' => $openingStock], 201);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $stock = Transaction::where('business_id', $business_id)
            ->where('type', 'opening_stock')
            ->findOrFail($id);

        $stock->delete();

        return response()->json(['success' => true, 'message' => 'Opening stock deleted.']);
    }
}
