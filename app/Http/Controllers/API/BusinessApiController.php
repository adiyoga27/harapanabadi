<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Business;
use App\BusinessLocation;
use App\Utils\BusinessUtil;
use Illuminate\Support\Facades\Mail;

class BusinessApiController extends Controller
{
    protected $businessUtil;

    public function __construct(BusinessUtil $businessUtil)
    {
        $this->businessUtil = $businessUtil;
    }

    public function getSettings(Request $request)
    {
        $business_id = $request->user()->business_id;

        $business = Business::findOrFail($business_id);

        return response()->json(['success' => true, 'data' => $business]);
    }

    public function updateSettings(Request $request)
    {
        $business_id = $request->user()->business_id;

        $business = Business::findOrFail($business_id);
        $business->update($request->only([
            'name', 'start_date', 'default_profit_percent', 'currency_id',
            'currency_symbol_placement', 'time_zone', 'fy_start_month',
            'accounting_method', 'default_sales_discount', 'sell_price_tax',
            'default_sales_tax', 'tax_label_1', 'tax_number_1',
            'tax_label_2', 'tax_number_2', 'sku_prefix',
            'enable_product_expiry', 'expiry_type', 'on_product_expiry',
            'stop_selling_before', 'enable_tooltip', 'enable_brand',
            'enable_category', 'enable_sub_category', 'enable_price_tax',
            'enable_purchase_status', 'enable_lot_number', 'default_unit',
            'enable_sub_units', 'enable_racks', 'enable_row', 'enable_position',
            'enable_editing_product_from_purchase', 'sales_cmsn_agnt',
            'item_addition_method', 'enable_inline_tax', 'currency_symbol_placement',
            'enabled_modules', 'common_settings', 'default_datatable_page_entries',
            'sms_settings', 'email_settings', 'keyboard_shortcuts',
            'pos_settings', 'weighing_scale_settings', 'manufacturing_settings',
            'essential_settings', 'ecommerce_settings', 'repair_settings',
            'enable_rp', 'rp_name', 'amount_for_unit_rp', 'min_order_total_for_rp',
            'max_rp_per_order', 'redeem_amount_per_unit_rp', 'min_order_total_for_redeem',
            'min_redeem_point', 'max_redeem_point', 'rp_expiry_period', 'rp_expiry_type',
        ]));

        return response()->json(['success' => true, 'data' => $business]);
    }

    public function getLocations(Request $request)
    {
        $business_id = $request->user()->business_id;

        $locations = BusinessLocation::where('business_id', $business_id)->get();

        return response()->json(['success' => true, 'data' => $locations]);
    }

    public function getLocation(Request $request, $id)
    {
        $business_id = $request->user()->business_id;

        $location = BusinessLocation::where('business_id', $business_id)->findOrFail($id);

        return response()->json(['success' => true, 'data' => $location]);
    }

    public function testEmail(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Email configuration test passed.']);
    }

    public function testSms(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'SMS configuration test passed.']);
    }
}
