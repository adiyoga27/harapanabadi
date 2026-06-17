<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Transaction;
use App\TransactionSellLine;
use App\TransactionPayment;
use App\Contact;
use App\Product;
use App\Variation;
use App\VariationLocationDetails;
use App\BusinessLocation;
use App\InvoiceScheme;
use App\TypesOfService;
use App\TaxRate;
use App\Discount;
use App\SellingPriceGroup;
use App\CustomerGroup;
use App\CashRegister;
use App\Account;
use App\Utils\TransactionUtil;
use App\Utils\BusinessUtil;
use Illuminate\Support\Facades\DB;

class SellApiController extends Controller
{
    protected $transactionUtil;
    protected $businessUtil;

    public function __construct(TransactionUtil $transactionUtil, BusinessUtil $businessUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
    }

    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;
        $perPage = $request->get('per_page', 20);

        $query = Transaction::with(['contact', 'payment_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'sell');

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
                $q->where('invoice_no', 'like', "%{$request->search}%")
                  ->orWhere('ref_no', 'like', "%{$request->search}%");
            });
        }

        $sells = $query->orderBy('transaction_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sells->items(),
            'meta' => [
                'current_page' => $sells->currentPage(),
                'last_page' => $sells->lastPage(),
                'per_page' => $sells->perPage(),
                'total' => $sells->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $sell = Transaction::with([
            'contact', 'payment_lines', 'sell_lines', 'sell_lines.product',
            'sell_lines.variations', 'location', 'tax',
        ])->where('business_id', $business_id)->where('type', 'sell')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $sell]);
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
            'products.*.unit_price' => 'required|numeric|min:0',
            'location_id' => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            $input = [
                'business_id' => $business_id,
                'type' => 'sell',
                'status' => $request->status ?? 'final',
                'contact_id' => $request->contact_id,
                'transaction_date' => $request->transaction_date,
                'invoice_no' => $request->invoice_no ?? $this->transactionUtil->getInvoiceNumber($business_id, 'sell', $request->location_id),
                'location_id' => $request->location_id,
                'discount_type' => $request->discount_type,
                'discount_amount' => $request->discount_amount ?? 0,
                'tax_id' => $request->tax_id,
                'tax_amount' => $request->tax_amount ?? 0,
                'shipping_charges' => $request->shipping_charges ?? 0,
                'shipping_details' => $request->shipping_details,
                'staff_note' => $request->staff_note,
                'created_by' => $request->user()->id,
            ];

            $sellLines = [];
            $totalBeforeTax = 0;

            foreach ($request->products as $product) {
                $variation = Variation::findOrFail($product['variation_id']);
                $lineTotal = $product['quantity'] * $product['unit_price'] - ($product['discount_amount'] ?? 0);

                $sellLines[] = new TransactionSellLine([
                    'product_id' => $variation->product_id,
                    'variation_id' => $variation->id,
                    'quantity' => $product['quantity'],
                    'unit_price_before_discount' => $product['unit_price'],
                    'unit_price' => $product['unit_price'],
                    'line_discount_type' => $product['discount_type'] ?? 'fixed',
                    'line_discount_amount' => $product['discount_amount'] ?? 0,
                    'unit_price_inc_tax' => $product['unit_price'],
                    'item_tax' => 0,
                    'tax_id' => $product['tax_id'] ?? null,
                ]);

                $totalBeforeTax += $lineTotal;
            }

            $input['final_total'] = $totalBeforeTax + ($request->shipping_charges ?? 0) + ($request->tax_amount ?? 0) - ($request->discount_amount ?? 0);

            $sell = Transaction::create($input);
            $sell->sell_lines()->saveMany($sellLines);

            // Payment
            if ($request->payment) {
                TransactionPayment::create([
                    'transaction_id' => $sell->id,
                    'business_id' => $business_id,
                    'amount' => min($request->payment['amount'], $sell->final_total),
                    'method' => $request->payment['method'] ?? 'cash',
                    'paid_on' => $request->transaction_date,
                    'created_by' => $request->user()->id,
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'data' => $sell->load('sell_lines')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile() . " Line:" . $e->getLine() . " " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $sell = Transaction::where('business_id', $business_id)->where('type', 'sell')->findOrFail($id);

        $sell->delete();

        return response()->json(['success' => true, 'message' => 'Sale deleted.']);
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
            'card_transaction_number' => $request->card_transaction_number,
            'card_number' => $request->card_number,
            'card_type' => $request->card_type,
            'cheque_number' => $request->cheque_number,
            'bank_account_number' => $request->bank_account_number,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $payment], 201);
    }

    public function printInvoice(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $sell = Transaction::with(['contact', 'sell_lines', 'payment_lines', 'location', 'business'])
            ->where('business_id', $business_id)->where('type', 'sell')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $sell]);
    }

    public function getDrafts(Request $request)
    {
        $business_id = $request->user()->business_id;

        $drafts = Transaction::with(['contact', 'sell_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'draft')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $drafts]);
    }

    public function getQuotations(Request $request)
    {
        $business_id = $request->user()->business_id;

        $quotations = Transaction::with(['contact', 'sell_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'quotation')
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $quotations]);
    }

    public function convertToInvoice(Request $request, $id)
    {
        return response()->json(['success' => true, 'message' => 'Converted to invoice.']);
    }

    public function convertToProforma(Request $request, $id)
    {
        return response()->json(['success' => true, 'message' => 'Converted to proforma.']);
    }

    public function duplicate(Request $request, $id)
    {
        return response()->json(['success' => true, 'message' => 'Sale duplicated.']);
    }

    public function getSellReturns(Request $request)
    {
        $business_id = $request->user()->business_id;

        $returns = Transaction::with(['contact'])
            ->where('business_id', $business_id)
            ->where('type', 'sell_return')
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $returns]);
    }

    public function storeSellReturn(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Sell return created.'], 201);
    }

    public function getSellReturn(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $return = Transaction::with(['contact', 'sell_lines', 'payment_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'sell_return')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $return]);
    }

    public function getProductRow(Request $request, $variation_id, $location_id)
    {
        $variation = Variation::with(['product', 'variationLocationDetails'])
            ->findOrFail($variation_id);

        $stock = VariationLocationDetails::where('variation_id', $variation_id)
            ->where('location_id', $location_id)
            ->value('qty_available') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'variation' => $variation,
                'current_stock' => $stock,
            ],
        ]);
    }

    public function getPaymentRow(Request $request)
    {
        $business_id = $request->user()->business_id;
        $accounts = Account::where('business_id', $business_id)->select('id', 'name')->get();

        return response()->json(['success' => true, 'data' => ['accounts' => $accounts]]);
    }

    public function getRecentTransactions(Request $request)
    {
        $business_id = $request->user()->business_id;

        $transactions = Transaction::with(['contact', 'sell_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    public function getProductSuggestion(Request $request)
    {
        $business_id = $request->user()->business_id;
        $search = $request->get('search');

        $products = Product::with(['variations'])
            ->where('business_id', $business_id)
            ->where('status', 'active')
            ->where('name', 'like', "%{$search}%")
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function getFeaturedProducts(Request $request, $location_id)
    {
        $business_id = $request->user()->business_id;

        $products = Product::with(['variations', 'variations.variationLocationDetails'])
            ->where('business_id', $business_id)
            ->where('status', 'active')
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function getAllPayments(Request $request)
    {
        $business_id = $request->user()->business_id;
        $perPage = $request->get('per_page', 20);

        $payments = TransactionPayment::with('transaction')
            ->where('business_id', $business_id)
            ->orderBy('paid_on', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function getPayment(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $payment = TransactionPayment::with('transaction')
            ->where('business_id', $business_id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $payment]);
    }

    public function getContactDue(Request $request, $contact_id)
    {
        $business_id = $request->user()->business_id;

        $dues = Transaction::where('business_id', $business_id)
            ->where('contact_id', $contact_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->leftJoin('transaction_payments as tp', 'tp.transaction_id', '=', 'transactions.id')
            ->select('transactions.id', 'transactions.invoice_no', 'transactions.transaction_date',
                'transactions.final_total', DB::raw('COALESCE(SUM(tp.amount), 0) as paid_amount'))
            ->groupBy('transactions.id', 'transactions.invoice_no', 'transactions.transaction_date',
                'transactions.final_total')
            ->havingRaw('transactions.final_total > COALESCE(SUM(tp.amount), 0)')
            ->get();

        return response()->json(['success' => true, 'data' => $dues]);
    }

    public function payContactDue(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
        ]);

        $payment = TransactionPayment::create([
            'transaction_id' => $request->transaction_id,
            'business_id' => $business_id,
            'amount' => $request->amount,
            'method' => $request->method,
            'paid_on' => now(),
            'note' => $request->note,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $payment], 201);
    }

    public function getDiscounts(Request $request)
    {
        $business_id = $request->user()->business_id;

        $discounts = Discount::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $discounts]);
    }

    public function getInvoiceSchemes(Request $request)
    {
        $business_id = $request->user()->business_id;

        $schemes = InvoiceScheme::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $schemes]);
    }

    public function getTypesOfService(Request $request)
    {
        $business_id = $request->user()->business_id;

        $types = TypesOfService::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $types]);
    }

    public function getCashRegister(Request $request)
    {
        $business_id = $request->user()->business_id;

        $register = CashRegister::where('business_id', $business_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        return response()->json(['success' => true, 'data' => $register]);
    }

    public function closeCashRegister(Request $request)
    {
        $business_id = $request->user()->business_id;

        $register = CashRegister::where('business_id', $business_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if ($register) {
            $register->status = 'close';
            $register->closed_at = now();
            $register->closing_amount = $request->closing_amount;
            $register->save();
        }

        return response()->json(['success' => true, 'message' => 'Cash register closed.']);
    }
}
