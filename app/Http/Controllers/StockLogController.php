<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Product;
use App\Utils\ProductUtil;
use App\Utils\Util;
use App\Variation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class StockLogController extends Controller
{
    /**
     * All Utils instance.
     *
     */
    protected $productUtil;
    protected $commonUtil;

    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, Util $commonUtil)
    {
        $this->productUtil = $productUtil;
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of stock log: system stock vs real (sellable) stock per variation.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $location_id = request()->input('location_id');
            $only_mismatch = request()->input('only_mismatch');

            $pl_sum = $this->commonUtil->get_pl_quantity_sum_string('PL');

            $query = Variation::join('products as p', 'p.id', '=', 'variations.product_id')
                ->join('variation_location_details as vld', 'vld.variation_id', '=', 'variations.id')
                ->join('business_locations as l', 'l.id', '=', 'vld.location_id')
                ->where('p.business_id', $business_id)
                ->where('p.enable_stock', 1)
                ->select([
                    'variations.id as variation_id',
                    'variations.sub_sku as sku',
                    'variations.name as variation_name',
                    'variations.default_purchase_price',
                    'variations.default_sell_price',
                    'p.name as product_name',
                    'p.sku as product_sku',
                    'p.id as product_id',
                    'l.id as location_id',
                    'l.name as location_name',
                    DB::raw('vld.qty_available as system_stock'),
                    DB::raw("COALESCE((SELECT SUM(PL.quantity - ($pl_sum))
                            FROM purchase_lines PL
                            JOIN transactions t ON t.id = PL.transaction_id
                            WHERE t.business_id = p.business_id
                            AND t.location_id = vld.location_id
                            AND t.status = 'received'
                            AND t.type IN ('purchase', 'purchase_transfer', 'opening_stock', 'production_purchase')
                            AND PL.variation_id = variations.id), 0) as real_stock"),
                    DB::raw("(SELECT COUNT(*) FROM transaction_sell_lines_purchase_lines tsp
                            LEFT JOIN transaction_sell_lines sl ON sl.id = tsp.sell_line_id
                            JOIN purchase_lines PL2 ON PL2.id = tsp.purchase_line_id
                            WHERE sl.id IS NULL AND tsp.sell_line_id IS NOT NULL
                            AND PL2.variation_id = variations.id) as orphan_mappings"),
                ]);

            if (!empty($location_id)) {
                $query->where('l.id', $location_id);
            }

            if (!empty($only_mismatch)) {
                $pl_sum_3 = 'PL3.quantity_sold + PL3.quantity_adjusted + PL3.quantity_returned + PL3.mfg_quantity_used';
                $query->havingRaw("vld.qty_available != COALESCE((SELECT SUM(PL3.quantity - ($pl_sum_3))
                            FROM purchase_lines PL3
                            JOIN transactions t3 ON t3.id = PL3.transaction_id
                            WHERE t3.business_id = p.business_id
                            AND t3.location_id = vld.location_id
                            AND t3.status = 'received'
                            AND t3.type IN ('purchase', 'purchase_transfer', 'opening_stock', 'production_purchase')
                            AND PL3.variation_id = variations.id), 0)
                    OR (SELECT COUNT(*) FROM transaction_sell_lines_purchase_lines tsp2
                            LEFT JOIN transaction_sell_lines sl2 ON sl2.id = tsp2.sell_line_id
                            JOIN purchase_lines PL4 ON PL4.id = tsp2.purchase_line_id
                            WHERE sl2.id IS NULL AND tsp2.sell_line_id IS NOT NULL
                            AND PL4.variation_id = variations.id) > 0");
            }

            $search = request()->input('search.value');
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('p.name', 'like', '%' . $search . '%')
                        ->orWhere('p.sku', 'like', '%' . $search . '%')
                        ->orWhere('variations.sub_sku', 'like', '%' . $search . '%');
                });
            }

            return Datatables::of($query)
                ->editColumn('product_name', function ($row) {
                    $name = $row->product_name;
                    if (!empty($row->variation_name) && $row->variation_name != 'DUMMY') {
                        $name .= ' (' . $row->variation_name . ')';
                    }
                    return $name;
                })
                ->editColumn('sku', function ($row) {
                    return !empty($row->sku) ? $row->sku : '-';
                })
                ->editColumn('system_stock', function ($row) {
                    return '<span class="display_currency" data-is_quantity="true">' . $row->system_stock . '</span>';
                })
                ->editColumn('real_stock', function ($row) {
                    return '<span class="display_currency" data-is_quantity="true">' . $row->real_stock . '</span>';
                })
                ->addColumn('difference', function ($row) {
                    $difference = $row->system_stock - $row->real_stock;
                    $label = '<span class="label bg-' . ($difference == 0 ? 'green' : 'red') . '">'
                        . ($difference > 0 ? '+' : '') . $difference . '</span>';
                    return $label;
                })
                ->addColumn('status', function ($row) {
                    $difference = $row->system_stock - $row->real_stock;
                    $mismatch = $difference != 0 || $row->orphan_mappings > 0;
                    if ($mismatch) {
                        return '<span class="label bg-red">' . __('lang_v1.stock_mismatch') . '</span>';
                    }
                    return '<span class="label bg-green">' . __('lang_v1.stock_match') . '</span>';
                })
                ->editColumn('orphan_mappings', function ($row) {
                    if ($row->orphan_mappings > 0) {
                        return '<span class="label bg-red">' . $row->orphan_mappings . '</span>';
                    }
                    return '<span class="label bg-green">0</span>';
                })
                ->addColumn('action', function ($row) {
                    return '<a href="' . action('StockLogController@history', [$row->variation_id]) . '?location_id=' . $row->location_id . '" class="btn btn-xs btn-primary">'
                        . '<i class="fa fa-history"></i> ' . __('lang_v1.view_stock_log') . '</a>';
                })
                ->rawColumns(['product_name', 'system_stock', 'real_stock', 'difference', 'status', 'orphan_mappings', 'action'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        return view('stock_log.index')
                ->with(compact('business_locations'));
    }

    /**
     * Display stock history of a variation with purchase line breakdown & orphan mappings.
     *
     * @param  int  $id variation id
     * @return \Illuminate\Http\Response
     */
    public function history($id)
    {
        if (!auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $variation = Variation::where('id', $id)
                        ->with(['product', 'product_variation'])
                        ->firstOrFail();

        $business_locations = BusinessLocation::forDropdown($business_id);
        $location_id = request()->input('location_id');
        if (empty($location_id)) {
            $location_id = array_key_first($business_locations->toArray());
        }

        $stock_details = $this->productUtil->getVariationStockDetails($business_id, $id, $location_id);
        $stock_history = $this->productUtil->getVariationStockHistory($business_id, $id, $location_id);

        //Purchase lines breakdown: what is still available for selling (FIFO/LIFO mapping source)
        $pl_sum = $this->commonUtil->get_pl_quantity_sum_string('PL');
        $purchase_lines = DB::table('purchase_lines as PL')
                    ->join('transactions as t', 't.id', '=', 'PL.transaction_id')
                    ->where('t.business_id', $business_id)
                    ->where('t.location_id', $location_id)
                    ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
                    ->where('t.status', 'received')
                    ->where('PL.variation_id', $id)
                    ->select(
                        'PL.id',
                        'PL.quantity',
                        'PL.quantity_sold',
                        'PL.quantity_adjusted',
                        'PL.quantity_returned',
                        'PL.mfg_quantity_used',
                        DB::raw("(PL.quantity - ($pl_sum)) as quantity_available"),
                        't.type as transaction_type',
                        't.ref_no',
                        't.invoice_no',
                        't.transaction_date'
                    )
                    ->orderBy('t.transaction_date', 'asc')
                    ->get();

        $real_stock = $purchase_lines->sum('quantity_available');

        //Orphan mappings for this variation (sell lines deleted but mapping still exists)
        $orphan_mappings = DB::table('transaction_sell_lines_purchase_lines as tsp')
                    ->leftJoin('transaction_sell_lines as sl', 'sl.id', '=', 'tsp.sell_line_id')
                    ->join('purchase_lines as pl', 'pl.id', '=', 'tsp.purchase_line_id')
                    ->whereNull('sl.id')
                    ->whereNotNull('tsp.sell_line_id')
                    ->where('pl.variation_id', $id)
                    ->select(
                        'tsp.id',
                        'tsp.sell_line_id',
                        'tsp.purchase_line_id',
                        'tsp.quantity',
                        'tsp.created_at'
                    )
                    ->get();

        $location_name = '';
        $location = BusinessLocation::find($location_id);
        if (!empty($location)) {
            $location_name = $location->name;
        }

        return view('stock_log.history')
                ->with(compact(
                    'variation',
                    'business_locations',
                    'location_id',
                    'location_name',
                    'stock_details',
                    'stock_history',
                    'purchase_lines',
                    'real_stock',
                    'orphan_mappings'
                ));
    }
}
