<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Transaction;
use App\TransactionPayment;
use App\ExpenseCategory;
use App\Account;
use Illuminate\Support\Facades\DB;

class ExpenseApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;
        $perPage = $request->get('per_page', 20);

        $query = Transaction::with(['expenseCategory', 'account'])
            ->where('business_id', $business_id)
            ->where('type', 'expense');

        if ($request->expense_category_id) {
            $query->where('expense_category_id', $request->expense_category_id);
        }
        if ($request->from_date && $request->to_date) {
            $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
        }

        $expenses = $query->orderBy('transaction_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $expenses->items(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $expense = Transaction::with(['expenseCategory', 'account', 'payment_lines'])
            ->where('business_id', $business_id)
            ->where('type', 'expense')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $expense]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'expense_category_id' => 'required|integer|exists:expense_categories,id',
            'transaction_date' => 'required|date',
            'final_total' => 'required|numeric|min:0',
            'location_id' => 'required|integer',
        ]);

        $expense = Transaction::create([
            'business_id' => $business_id,
            'type' => 'expense',
            'status' => 'final',
            'expense_category_id' => $request->expense_category_id,
            'transaction_date' => $request->transaction_date,
            'final_total' => $request->final_total,
            'location_id' => $request->location_id,
            'ref_no' => $request->ref_no,
            'account_id' => $request->account_id,
            'expense_for' => $request->expense_for,
            'additional_notes' => $request->additional_notes,
            'created_by' => $request->user()->id,
        ]);

        if ($request->payment) {
            TransactionPayment::create([
                'transaction_id' => $expense->id,
                'business_id' => $business_id,
                'amount' => $request->final_total,
                'method' => $request->payment['method'] ?? 'cash',
                'paid_on' => $request->transaction_date,
                'created_by' => $request->user()->id,
            ]);
        }

        return response()->json(['success' => true, 'data' => $expense], 201);
    }

    public function update(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $expense = Transaction::where('business_id', $business_id)
            ->where('type', 'expense')
            ->findOrFail($id);

        $expense->update($request->only([
            'expense_category_id', 'transaction_date', 'final_total',
            'location_id', 'ref_no', 'account_id', 'expense_for',
            'additional_notes',
        ]));

        return response()->json(['success' => true, 'data' => $expense]);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $expense = Transaction::where('business_id', $business_id)
            ->where('type', 'expense')
            ->findOrFail($id);

        $expense->delete();

        return response()->json(['success' => true, 'message' => 'Expense deleted.']);
    }

    public function getCategories(Request $request)
    {
        $business_id = $request->user()->business_id;

        $categories = ExpenseCategory::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }
}
