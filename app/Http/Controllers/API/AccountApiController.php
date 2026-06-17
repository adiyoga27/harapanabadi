<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Account;
use App\AccountTransaction;
use App\TransactionPayment;
use Illuminate\Support\Facades\DB;

class AccountApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;

        $accounts = Account::with('accountType')
            ->where('business_id', $business_id)
            ->get();

        return response()->json(['success' => true, 'data' => $accounts]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $account = Account::with(['accountType', 'transactions'])
            ->where('business_id', $business_id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $account]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'name' => 'required|string',
            'account_type_id' => 'required|integer',
        ]);

        $account = Account::create([
            'business_id' => $business_id,
            'name' => $request->name,
            'account_type_id' => $request->account_type_id,
            'account_number' => $request->account_number,
            'note' => $request->note,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $account], 201);
    }

    public function update(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $account = Account::where('business_id', $business_id)->findOrFail($id);
        $account->update($request->only(['name', 'account_number', 'note']));

        return response()->json(['success' => true, 'data' => $account]);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $account = Account::where('business_id', $business_id)->findOrFail($id);
        $account->delete();

        return response()->json(['success' => true, 'message' => 'Account deleted.']);
    }

    public function balanceSheet(Request $request)
    {
        $business_id = $request->user()->business_id;

        $accounts = Account::where('business_id', $business_id)
            ->withSum('transactions as total_credit', 'credit')
            ->withSum('transactions as total_debit', 'debit')
            ->get();

        return response()->json(['success' => true, 'data' => $accounts]);
    }

    public function trialBalance(Request $request)
    {
        $business_id = $request->user()->business_id;

        $accounts = Account::where('business_id', $business_id)
            ->withSum('transactions as total_credit', 'credit')
            ->withSum('transactions as total_debit', 'debit')
            ->get();

        return response()->json(['success' => true, 'data' => $accounts]);
    }

    public function cashFlow(Request $request)
    {
        $business_id = $request->user()->business_id;
        $from = $request->from_date ?? now()->startOfMonth();
        $to = $request->to_date ?? now()->endOfMonth();

        $inflows = TransactionPayment::where('business_id', $business_id)
            ->whereHas('transaction', function ($q) {
                $q->where('type', 'sell');
            })
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount');

        $outflows = TransactionPayment::where('business_id', $business_id)
            ->whereHas('transaction', function ($q) {
                $q->whereIn('type', ['purchase', 'expense']);
            })
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'inflows' => $inflows,
                'outflows' => $outflows,
                'net_cash_flow' => $inflows - $outflows,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}
