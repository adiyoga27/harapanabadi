<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Business;
use App\BusinessLocation;
use App\Transaction;
use App\TransactionSellLine;
use App\PurchaseLine;
use App\Product;
use App\Variation;
use App\VariationLocationDetails;
use App\Contact;
use App\NotificationTemplate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class HomeApiController extends Controller
{
    public function getTotals(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $totals = [
            'total_sales_today' => Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->whereDate('transaction_date', today())
                ->sum('final_total'),

            'total_purchases_today' => Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->where('status', 'received')
                ->whereDate('transaction_date', today())
                ->sum('final_total'),

            'total_expenses_today' => Transaction::where('business_id', $business_id)
                ->where('type', 'expense')
                ->whereDate('transaction_date', today())
                ->sum('final_total'),

            'total_sales_this_month' => Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->whereMonth('transaction_date', now()->month)
                ->sum('final_total'),

            'total_purchases_this_month' => Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->where('status', 'received')
                ->whereMonth('transaction_date', now()->month)
                ->sum('final_total'),
        ];

        return response()->json(['success' => true, 'data' => $totals]);
    }

    public function getStockAlert(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $products = Product::where('products.business_id', $business_id)
            ->join('variations as v', 'v.product_id', '=', 'products.id')
            ->leftJoin('variation_location_details as vld', 'vld.variation_id', '=', 'v.id')
            ->where('products.alert_quantity', '>', DB::raw('COALESCE(vld.qty_available, 0)'))
            ->select('products.id', 'products.name', 'products.sku', 'products.alert_quantity',
                DB::raw('COALESCE(vld.qty_available, 0) as current_stock'))
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function getPurchaseDues(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $dues = Transaction::where('transactions.business_id', $business_id)
            ->where('transactions.type', 'purchase')
            ->where('transactions.status', 'received')
            ->join('contacts as c', 'c.id', '=', 'transactions.contact_id')
            ->leftJoin('transaction_payments as tp', 'tp.transaction_id', '=', 'transactions.id')
            ->whereRaw('transactions.final_total > COALESCE(SUM(tp.amount), 0)')
            ->select('transactions.id', 'transactions.ref_no', 'transactions.transaction_date',
                'transactions.final_total', 'c.name as supplier_name',
                DB::raw('COALESCE(SUM(tp.amount), 0) as paid_amount'))
            ->groupBy('transactions.id', 'transactions.ref_no', 'transactions.transaction_date',
                'transactions.final_total', 'c.name')
            ->orderBy('transactions.transaction_date', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $dues]);
    }

    public function getSalesDues(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $dues = Transaction::where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->join('contacts as c', 'c.id', '=', 'transactions.contact_id')
            ->leftJoin('transaction_payments as tp', 'tp.transaction_id', '=', 'transactions.id')
            ->whereRaw('transactions.final_total > COALESCE(SUM(tp.amount), 0)')
            ->select('transactions.id', 'transactions.invoice_no', 'transactions.transaction_date',
                'transactions.final_total', 'c.name as customer_name',
                DB::raw('COALESCE(SUM(tp.amount), 0) as paid_amount'))
            ->groupBy('transactions.id', 'transactions.invoice_no', 'transactions.transaction_date',
                'transactions.final_total', 'c.name')
            ->orderBy('transactions.transaction_date', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $dues]);
    }

    public function getCalendar(Request $request)
    {
        $business_id = $this->getBusinessId($request);
        $start = $request->start;
        $end = $request->end;

        $events = Transaction::where('business_id', $business_id)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereIn('type', ['sell', 'purchase'])
            ->select('id', 'transaction_date as start', 'ref_no as title', 'type')
            ->get();

        return response()->json(['success' => true, 'data' => $events]);
    }

    public function getNotifications(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'message' => $n->data['msg'] ?? '',
                    'link' => $n->data['link'] ?? '',
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    public function getUsers(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $users = User::where('business_id', $business_id)
            ->select('id', 'username', 'email', 'first_name', 'last_name', 'surname', 'status')
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function getRoles(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $roles = Role::where('business_id', $business_id)
            ->select('id', 'name')
            ->get();

        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function getTables(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $tables = \App\Restaurant\Booking::where('business_id', $business_id)
            ->whereDate('booking_start', today())
            ->with('customer')
            ->get();

        return response()->json(['success' => true, 'data' => $tables]);
    }

    protected function getBusinessId(Request $request)
    {
        return $request->user()->business_id;
    }
}
