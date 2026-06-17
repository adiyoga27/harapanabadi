<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Transaction;
use App\TransactionSellLine;
use App\PurchaseLine;
use App\Product;
use App\Variation;
use App\VariationLocationDetails;
use App\Contact;
use App\Business;
use App\CustomerGroup;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\DB;

class ReportApiController extends Controller
{
    public function getSalesReport(Request $request)
    {
        $business_id = $request->user()->business_id;

        $query = Transaction::with(['contact', 'sell_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final');

        if ($request->from_date && $request->to_date) {
            $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
        }
        if ($request->contact_id) {
            $query->where('contact_id', $request->contact_id);
        }
        if ($request->location_id) {
            $query->where('location_id', $request->location_id);
        }

        $sales = $query->orderBy('transaction_date', 'desc')->get();

        $summary = [
            'total_sales' => $sales->sum('final_total'),
            'total_transactions' => $sales->count(),
            'total_tax' => $sales->sum('tax_amount'),
            'total_shipping' => $sales->sum('shipping_charges'),
            'total_discount' => $sales->sum('discount_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => $sales,
                'summary' => $summary,
            ],
        ]);
    }

    public function getPurchaseReport(Request $request)
    {
        $business_id = $request->user()->business_id;

        $query = Transaction::with(['contact', 'purchase_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'purchase')
            ->where('status', 'received');

        if ($request->from_date && $request->to_date) {
            $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
        }
        if ($request->contact_id) {
            $query->where('contact_id', $request->contact_id);
        }

        $purchases = $query->orderBy('transaction_date', 'desc')->get();

        $summary = [
            'total_purchases' => $purchases->sum('final_total'),
            'total_transactions' => $purchases->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'purchases' => $purchases,
                'summary' => $summary,
            ],
        ]);
    }

    public function getProfitLoss(Request $request)
    {
        $business_id = $request->user()->business_id;
        $from = $request->from_date ?? now()->startOfMonth();
        $to = $request->to_date ?? now()->endOfMonth();

        $sales = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('final_total');

        $purchases = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->where('status', 'received')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('final_total');

        $expenses = Transaction::where('business_id', $business_id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('final_total');

        $sellReturns = Transaction::where('business_id', $business_id)
            ->where('type', 'sell_return')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('final_total');

        $purchaseReturns = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase_return')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('final_total');

        $grossProfit = $sales - $purchases - $sellReturns + $purchaseReturns;
        $netProfit = $grossProfit - $expenses;

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => $sales,
                'sell_returns' => $sellReturns,
                'purchases' => $purchases,
                'purchase_returns' => $purchaseReturns,
                'expenses' => $expenses,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'from_date' => $from,
                'to_date' => $to,
            ],
        ]);
    }

    public function getTrendingProducts(Request $request)
    {
        $business_id = $request->user()->business_id;
        $limit = $request->get('limit', 10);

        $products = TransactionSellLine::join('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
            ->join('products as p', 'p.id', '=', 'transaction_sell_lines.product_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->select('p.id', 'p.name', 'p.sku', DB::raw('SUM(transaction_sell_lines.quantity) as total_sold'))
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByRaw('SUM(transaction_sell_lines.quantity) DESC')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function getTaxReport(Request $request)
    {
        $business_id = $request->user()->business_id;
        $from = $request->from_date ?? now()->startOfMonth();
        $to = $request->to_date ?? now()->endOfMonth();

        $taxReport = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween('transaction_date', [$from, $to])
            ->select(DB::raw('SUM(tax_amount) as total_tax'), DB::raw('SUM(final_total) as total_sales'))
            ->first();

        return response()->json(['success' => true, 'data' => $taxReport]);
    }

    public function getExpenseReport(Request $request)
    {
        $business_id = $request->user()->business_id;
        $from = $request->from_date ?? now()->startOfMonth();
        $to = $request->to_date ?? now()->endOfMonth();

        $expenses = Transaction::with('expenseCategory')
            ->where('business_id', $business_id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$from, $to])
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'expenses' => $expenses,
                'total' => $expenses->sum('final_total'),
            ],
        ]);
    }

    public function getRegisterReport(Request $request)
    {
        $business_id = $request->user()->business_id;

        $registers = \App\CashRegister::with('user')
            ->where('business_id', $business_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $registers]);
    }

    public function getCustomerGroupReport(Request $request)
    {
        $business_id = $request->user()->business_id;

        $report = CustomerGroup::where('business_id', $business_id)
            ->withCount('customers')
            ->get();

        return response()->json(['success' => true, 'data' => $report]);
    }

    public function getSellPaymentReport(Request $request)
    {
        $business_id = $request->user()->business_id;
        $from = $request->from_date ?? now()->startOfMonth();
        $to = $request->to_date ?? now()->endOfMonth();

        $payments = \App\TransactionPayment::whereHas('transaction', function ($q) use ($business_id) {
            $q->where('business_id', $business_id)->where('type', 'sell');
        })->whereBetween('paid_on', [$from, $to])
          ->with('transaction.contact')
          ->orderBy('paid_on', 'desc')
          ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments,
                'total' => $payments->sum('amount'),
            ],
        ]);
    }

    public function getPurchasePaymentReport(Request $request)
    {
        $business_id = $request->user()->business_id;
        $from = $request->from_date ?? now()->startOfMonth();
        $to = $request->to_date ?? now()->endOfMonth();

        $payments = \App\TransactionPayment::whereHas('transaction', function ($q) use ($business_id) {
            $q->where('business_id', $business_id)->where('type', 'purchase');
        })->whereBetween('paid_on', [$from, $to])
          ->with('transaction.contact')
          ->orderBy('paid_on', 'desc')
          ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments,
                'total' => $payments->sum('amount'),
            ],
        ]);
    }

    public function getProductSellReport(Request $request)
    {
        $business_id = $request->user()->business_id;
        $from = $request->from_date ?? now()->startOfMonth();
        $to = $request->to_date ?? now()->endOfMonth();

        $report = TransactionSellLine::join('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
            ->join('products as p', 'p.id', '=', 'transaction_sell_lines.product_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereBetween('t.transaction_date', [$from, $to])
            ->select('p.id', 'p.name', 'p.sku',
                DB::raw('SUM(transaction_sell_lines.quantity) as total_qty'),
                DB::raw('SUM(transaction_sell_lines.quantity * transaction_sell_lines.unit_price) as total_amount'))
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByRaw('SUM(transaction_sell_lines.quantity) DESC')
            ->get();

        return response()->json(['success' => true, 'data' => $report]);
    }

    public function getProductPurchaseReport(Request $request)
    {
        $business_id = $request->user()->business_id;
        $from = $request->from_date ?? now()->startOfMonth();
        $to = $request->to_date ?? now()->endOfMonth();

        $report = PurchaseLine::join('transactions as t', 't.id', '=', 'purchase_lines.transaction_id')
            ->join('products as p', 'p.id', '=', 'purchase_lines.product_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'purchase')
            ->where('t.status', 'received')
            ->whereBetween('t.transaction_date', [$from, $to])
            ->select('p.id', 'p.name', 'p.sku',
                DB::raw('SUM(purchase_lines.quantity) as total_qty'),
                DB::raw('SUM(purchase_lines.quantity * purchase_lines.purchase_price) as total_amount'))
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByRaw('SUM(purchase_lines.quantity) DESC')
            ->get();

        return response()->json(['success' => true, 'data' => $report]);
    }

    public function getActivityLog(Request $request)
    {
        $business_id = $request->user()->business_id;

        $logs = Activity::orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'description' => $log->description,
                    'user' => $log->causer ? $log->causer->username : 'System',
                    'created_at' => $log->created_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function getCurrentStock(Request $request)
    {
        $business_id = $request->user()->business_id;

        $stock = VariationLocationDetails::join('variations as v', 'v.id', '=', 'variation_location_details.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $business_id)
            ->select('p.id as product_id', 'p.name', 'p.sku', 'v.name as variation_name',
                'v.sub_sku', 'variation_location_details.qty_available',
                'variation_location_details.location_id')
            ->get();

        return response()->json(['success' => true, 'data' => $stock]);
    }

    public function getStockDetails(Request $request)
    {
        return $this->getCurrentStock($request);
    }

    public function getStockExpiry(Request $request)
    {
        $business_id = $request->user()->business_id;

        $expiry = PurchaseLine::join('transactions as t', 't.id', '=', 'purchase_lines.transaction_id')
            ->join('products as p', 'p.id', '=', 'purchase_lines.product_id')
            ->where('t.business_id', $business_id)
            ->whereNotNull('purchase_lines.exp_date')
            ->select('purchase_lines.id', 'p.name', 'purchase_lines.exp_date',
                'purchase_lines.quantity', 'purchase_lines.quantity_sold',
                'purchase_lines.quantity_adjusted')
            ->orderBy('purchase_lines.exp_date')
            ->get();

        return response()->json(['success' => true, 'data' => $expiry]);
    }

    public function getStockValue(Request $request)
    {
        $business_id = $request->user()->business_id;

        $stockValue = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $business_id)
            ->where('vld.qty_available', '>', 0)
            ->select(DB::raw('SUM(vld.qty_available * COALESCE(v.default_purchase_price, 0)) as total_stock_value'))
            ->first();

        return response()->json(['success' => true, 'data' => $stockValue]);
    }

    public function getStockTransfers(Request $request)
    {
        $business_id = $request->user()->business_id;

        $transfers = Transaction::with(['location', 'transferParent'])
            ->where('business_id', $business_id)
            ->where('type', 'stock_transfer')
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $transfers]);
    }
}
