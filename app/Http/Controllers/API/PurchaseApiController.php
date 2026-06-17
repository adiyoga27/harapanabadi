<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Transaction;
use App\PurchaseLine;
use App\TransactionPayment;
use App\Contact;
use App\Product;
use App\Variation;
use Illuminate\Support\Facades\DB;

class PurchaseApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;
        $perPage = $request->get('per_page', 20);

        $query = Transaction::with(['contact', 'payment_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'purchase');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->contact_id) {
            $query->where('contact_id', $request->contact_id);
        }
        if ($request->from_date && $request->to_date) {
            $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('ref_no', 'like', "%{$request->search}%");
            });
        }

        $purchases = $query->orderBy('transaction_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $purchases->items(),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $purchase = Transaction::with([
            'contact', 'payment_lines', 'purchase_lines', 'purchase_lines.product',
            'purchase_lines.variations', 'location',
        ])->where('business_id', $business_id)->where('type', 'purchase')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $purchase]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
            'transaction_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.variation_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.purchase_price' => 'required|numeric|min:0',
            'location_id' => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            $refNo = $request->ref_no ?? 'PO/' . date('Y/m/d') . '/' . rand(1000, 9999);

            $input = [
                'business_id' => $business_id,
                'type' => 'purchase',
                'status' => $request->status ?? 'received',
                'contact_id' => $request->contact_id,
                'transaction_date' => $request->transaction_date,
                'ref_no' => $refNo,
                'location_id' => $request->location_id,
                'discount_type' => $request->discount_type,
                'discount_amount' => $request->discount_amount ?? 0,
                'tax_amount' => $request->tax_amount ?? 0,
                'shipping_charges' => $request->shipping_charges ?? 0,
                'shipping_details' => $request->shipping_details,
                'staff_note' => $request->staff_note,
                'created_by' => $request->user()->id,
            ];

            $totalBeforeTax = 0;
            $purchaseLines = [];

            foreach ($request->products as $product) {
                $variation = Variation::findOrFail($product['variation_id']);
                $lineTotal = $product['quantity'] * $product['purchase_price'];

                $purchaseLines[] = new PurchaseLine([
                    'product_id' => $variation->product_id,
                    'variation_id' => $variation->id,
                    'quantity' => $product['quantity'],
                    'purchase_price' => $product['purchase_price'],
                    'purchase_price_inc_tax' => $product['purchase_price'],
                    'item_tax' => 0,
                    'tax_id' => $product['tax_id'] ?? null,
                ]);

                $totalBeforeTax += $lineTotal;
            }

            $input['final_total'] = $totalBeforeTax + ($request->shipping_charges ?? 0) + ($request->tax_amount ?? 0) - ($request->discount_amount ?? 0);

            $purchase = Transaction::create($input);
            $purchase->purchase_lines()->saveMany($purchaseLines);

            if ($request->payment) {
                TransactionPayment::create([
                    'transaction_id' => $purchase->id,
                    'business_id' => $business_id,
                    'amount' => min($request->payment['amount'], $purchase->final_total),
                    'method' => $request->payment['method'] ?? 'cash',
                    'paid_on' => $request->transaction_date,
                    'created_by' => $request->user()->id,
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'data' => $purchase->load('purchase_lines')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile() . " Line:" . $e->getLine() . " " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $purchase = Transaction::where('business_id', $business_id)->where('type', 'purchase')->findOrFail($id);

        if ($purchase->status === 'received') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a received purchase.',
            ], 422);
        }

        $purchase->delete();

        return response()->json(['success' => true, 'message' => 'Purchase deleted.']);
    }

    public function getPayments(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $payments = TransactionPayment::whereHas('transaction', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })->where('transaction_id', $id)->get();

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function addPayment(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'paid_on' => 'required|date',
        ]);

        $payment = TransactionPayment::create([
            'transaction_id' => $id,
            'business_id' => $business_id,
            'amount' => $request->amount,
            'method' => $request->method,
            'paid_on' => $request->paid_on,
            'note' => $request->note,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $payment], 201);
    }

    public function updateStatus(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'purchase_ids' => 'required|array',
            'status' => 'required|string|in:received,pending,ordered',
        ]);

        Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->whereIn('id', $request->purchase_ids)
            ->update(['status' => $request->status]);

        return response()->json(['success' => true, 'message' => 'Status updated.']);
    }

    public function printInvoice(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $purchase = Transaction::with(['contact', 'purchase_lines', 'payment_lines', 'location', 'business'])
            ->where('business_id', $business_id)->where('type', 'purchase')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $purchase]);
    }

    public function getPurchaseReturns(Request $request)
    {
        $business_id = $request->user()->business_id;

        $returns = Transaction::with(['contact'])
            ->where('business_id', $business_id)
            ->where('type', 'purchase_return')
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $returns]);
    }

    public function storePurchaseReturn(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Purchase return created.'], 201);
    }

    public function getPurchaseReturn(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $return = Transaction::with(['contact', 'purchase_lines', 'payment_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'purchase_return')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $return]);
    }
}
