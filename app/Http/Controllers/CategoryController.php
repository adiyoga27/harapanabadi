<?php

namespace App\Http\Controllers;

use App\Category;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function getCategoriesApi(Request $request)
    {
        $business_id = $this->getBusinessIdFromToken($request);

        $categories = Category::where('business_id', $business_id)
            ->where('category_type', 'product')
            ->select('id', 'name', 'short_code')
            ->get();

        return response()->json($categories);
    }

    protected function getBusinessIdFromToken(Request $request)
    {
        return $request->attributes->get('business_id');
    }
}
