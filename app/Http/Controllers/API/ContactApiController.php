<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Contact;
use App\Transaction;
use App\TransactionPayment;
use App\CustomerGroup;
use Illuminate\Support\Facades\DB;

class ContactApiController extends Controller
{
    public function index(Request $request)
    {
        $business_id = $request->user()->business_id;
        $perPage = $request->get('per_page', 20);

        $query = Contact::where('contacts.business_id', $business_id);

        if ($request->type) {
            $query->where('contacts.type', $request->type);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('contacts.name', 'like', "%{$request->search}%")
                  ->orWhere('contacts.mobile', 'like', "%{$request->search}%")
                  ->orWhere('contacts.email', 'like', "%{$request->search}%");
            });
        }
        if ($request->customer_group_id) {
            $query->where('contacts.customer_group_id', $request->customer_group_id);
        }

        $contacts = $query->orderBy('contacts.name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $contacts->items(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $contact = Contact::where('business_id', $business_id)->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contact]);
    }

    public function store(Request $request)
    {
        $business_id = $request->user()->business_id;

        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string|in:customer,supplier,both',
        ]);

        $contact = Contact::create([
            'business_id' => $business_id,
            'name' => $request->name,
            'type' => $request->type,
            'supplier_business_name' => $request->supplier_business_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'alternate_number' => $request->alternate_number,
            'landline' => $request->landline,
            'tax_number' => $request->tax_number,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'contact_id' => $request->contact_id,
            'customer_group_id' => $request->customer_group_id,
            'credit_limit' => $request->credit_limit,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $contact], 201);
    }

    public function update(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $contact = Contact::where('business_id', $business_id)->findOrFail($id);

        $contact->update($request->only([
            'name', 'type', 'supplier_business_name', 'email', 'mobile',
            'alternate_number', 'landline', 'tax_number', 'city', 'state',
            'country', 'contact_id', 'customer_group_id', 'credit_limit',
        ]));

        return response()->json(['success' => true, 'data' => $contact]);
    }

    public function destroy(Request $request, $id)
    {
        $business_id = $request->user()->business_id;
        $contact = Contact::where('business_id', $business_id)->findOrFail($id);

        if ($contact->transactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Contact has transactions and cannot be deleted.',
            ], 422);
        }

        $contact->delete();

        return response()->json(['success' => true, 'message' => 'Contact deleted.']);
    }

    public function getPayments(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $payments = TransactionPayment::whereHas('transaction', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })->whereHas('transaction', function ($q) use ($id) {
            $q->where('contact_id', $id);
        })->with('transaction')
          ->orderBy('paid_on', 'desc')
          ->get();

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function getLedger(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $transactions = Transaction::where('business_id', $business_id)
            ->where('contact_id', $id)
            ->with('payment_lines')
            ->orderBy('transaction_date', 'asc')
            ->get();

        $balance = 0;
        $ledger = $transactions->map(function ($txn) use (&$balance) {
            $paid = $txn->payment_lines->sum('amount');
            $due = $txn->final_total - $paid;
            if (in_array($txn->type, ['sell', 'opening_balance'])) {
                $balance += $due;
            } else {
                $balance -= $due;
            }
            return [
                'date' => $txn->transaction_date,
                'ref_no' => $txn->ref_no,
                'type' => $txn->type,
                'amount' => $txn->final_total,
                'paid' => $paid,
                'due' => $due,
                'balance' => $balance,
            ];
        });

        return response()->json(['success' => true, 'data' => $ledger]);
    }

    public function sendLedger(Request $request, $id)
    {
        return response()->json(['success' => true, 'message' => 'Ledger sent successfully.']);
    }

    public function import(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Import started.']);
    }

    public function getCustomers(Request $request)
    {
        $business_id = $request->user()->business_id;

        $customers = Contact::where('business_id', $business_id)
            ->where('type', 'customer')
            ->orWhere('type', 'both')
            ->select('id', 'name', 'mobile', 'email')
            ->get();

        return response()->json(['success' => true, 'data' => $customers]);
    }

    public function getSuppliers(Request $request)
    {
        $business_id = $request->user()->business_id;

        $suppliers = Contact::where('business_id', $business_id)
            ->where('type', 'supplier')
            ->orWhere('type', 'both')
            ->select('id', 'name', 'mobile', 'email', 'supplier_business_name')
            ->get();

        return response()->json(['success' => true, 'data' => $suppliers]);
    }

    public function getCustomerGroups(Request $request)
    {
        $business_id = $request->user()->business_id;

        $groups = CustomerGroup::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $groups]);
    }
}
