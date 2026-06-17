<?php

namespace App\Http\Controllers;

use App\Account;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class PaymentAccountController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function index()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $accounts = Account::where('business_id', $business_id)
                ->with('accountType')
                ->select(['id', 'name', 'account_number', 'note', 'account_type_id']);

            return DataTables::of($accounts)
                ->addColumn('action', function ($row) {
                    return view('payment_account.partials.action', compact('row'))->render();
                })
                ->editColumn('name', function ($row) {
                    return $row->name;
                })
                ->removeColumn('id')
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('payment_account.index');
    }

    public function create()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        return view('payment_account.create');
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            Account::create([
                'business_id' => $business_id,
                'name' => $request->name,
                'account_number' => $request->account_number,
                'account_type_id' => $request->account_type_id,
                'note' => $request->note,
                'created_by' => $request->session()->get('user.id'),
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.added_success')];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . " Line:" . $e->getLine() . " Message:" . $e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->route('payment-account.index')->with('status', $output);
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $account = Account::where('business_id', $business_id)->findOrFail($id);

        return view('payment_account.edit', compact('account'));
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $account = Account::where('business_id', $business_id)->findOrFail($id);
            $account->update([
                'name' => $request->name,
                'account_number' => $request->account_number,
                'account_type_id' => $request->account_type_id,
                'note' => $request->note,
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.updated_success')];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . " Line:" . $e->getLine() . " Message:" . $e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->route('payment-account.index')->with('status', $output);
    }

    public function destroy($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $account = Account::where('business_id', $business_id)->findOrFail($id);
            $account->delete();

            $output = ['success' => true, 'msg' => __('lang_v1.deleted_success')];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . " Line:" . $e->getLine() . " Message:" . $e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->route('payment-account.index')->with('status', $output);
    }
}
