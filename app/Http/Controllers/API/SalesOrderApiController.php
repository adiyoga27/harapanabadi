<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Transaction;
use App\TransactionSellLine;

class SalesOrderApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;
        $perPage = $request->get('per_page', 20);

        $orders = Transaction::with(['contact', 'sell_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'sales_order')
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $order = Transaction::with(['contact', 'sell_lines', 'sell_lines.product'])
            ->where('business_id', $business_id)
            ->where('type', 'sales_order')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $order]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'contact_id' => 'required|integer',
            'transaction_date' => 'required|date',
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric',
            'products.*.unit_price' => 'required|numeric',
            'location_id' => 'required|integer',
        ]);

        $order = Transaction::create([
            'business_id' => $business_id,
            'type' => 'sales_order',
            'status' => 'ordered',
            'contact_id' => $request->contact_id,
            'transaction_date' => $request->transaction_date,
            'location_id' => $request->location_id,
            'staff_note' => $request->staff_note,
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->products as $product) {
            TransactionSellLine::create([
                'transaction_id' => $order->id,
                'product_id' => $product['product_id'],
                'variation_id' => $product['variation_id'] ?? null,
                'quantity' => $product['quantity'],
                'unit_price' => $product['unit_price'],
                'unit_price_inc_tax' => $product['unit_price'],
            ]);
        }

        return response()->json(['success' => true, 'data' => $order], 201);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $order = Transaction::where('business_id', $business_id)
            ->where('type', 'sales_order')
            ->findOrFail($id);

        $order->delete();

        return response()->json(['success' => true, 'message' => 'Sales order deleted.']);
    }
}
