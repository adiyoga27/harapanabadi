<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductApiController;
use App\Http\Controllers\API\ContactApiController;
use App\Http\Controllers\API\SellApiController;
use App\Http\Controllers\API\PurchaseApiController;
use App\Http\Controllers\API\BusinessApiController;
use App\Http\Controllers\API\ReportApiController;
use App\Http\Controllers\API\HomeApiController;
use App\Http\Controllers\API\ExpenseApiController;
use App\Http\Controllers\API\BrandApiController;
use App\Http\Controllers\API\CategoryApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.')->group(function () {

    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:api')->group(function () {

        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);

        // Home / Dashboard
        Route::get('home/totals', [HomeApiController::class, 'getTotals']);
        Route::get('home/stock-alert', [HomeApiController::class, 'getStockAlert']);
        Route::get('home/purchase-dues', [HomeApiController::class, 'getPurchaseDues']);
        Route::get('home/sales-dues', [HomeApiController::class, 'getSalesDues']);
        Route::get('home/calendar', [HomeApiController::class, 'getCalendar']);
        Route::get('home/notifications', [HomeApiController::class, 'getNotifications']);

        // Business
        Route::get('business/settings', [BusinessApiController::class, 'getSettings']);
        Route::put('business/settings', [BusinessApiController::class, 'updateSettings']);
        Route::get('business/locations', [BusinessApiController::class, 'getLocations']);
        Route::get('business/locations/{id}', [BusinessApiController::class, 'getLocation']);
        Route::post('business/test-email', [BusinessApiController::class, 'testEmail']);
        Route::post('business/test-sms', [BusinessApiController::class, 'testSms']);

        // Products
        Route::apiResource('products', ProductApiController::class);
        Route::get('products/{id}/stock-history', [ProductApiController::class, 'stockHistory']);
        Route::get('products/{id}/variations', [ProductApiController::class, 'variations']);
        Route::get('products/{id}/group-price', [ProductApiController::class, 'viewGroupPrice']);
        Route::post('products/{id}/activate', [ProductApiController::class, 'activate']);
        Route::post('products/mass-deactivate', [ProductApiController::class, 'massDeactivate']);
        Route::post('products/mass-delete', [ProductApiController::class, 'massDelete']);
        Route::get('products-list', [ProductApiController::class, 'listProducts']);

        // Categories
        Route::apiResource('categories', CategoryApiController::class);
        Route::get('categories/{id}/sub-categories', [CategoryApiController::class, 'subCategories']);

        // Brands
        Route::apiResource('brands', BrandApiController::class);

        // Units
        Route::get('units', [ProductApiController::class, 'getUnits']);

        // Tax Rates
        Route::get('tax-rates', [ProductApiController::class, 'getTaxRates']);

        // Variation Templates
        Route::get('variation-templates', [ProductApiController::class, 'getVariationTemplates']);

        // Contacts
        Route::apiResource('contacts', ContactApiController::class);
        Route::get('contacts/{id}/payments', [ContactApiController::class, 'getPayments']);
        Route::get('contacts/{id}/ledger', [ContactApiController::class, 'getLedger']);
        Route::post('contacts/{id}/send-ledger', [ContactApiController::class, 'sendLedger']);
        Route::post('contacts/import', [ContactApiController::class, 'import']);
        Route::get('customers', [ContactApiController::class, 'getCustomers']);
        Route::get('suppliers', [ContactApiController::class, 'getSuppliers']);

        // Customer Groups
        Route::get('customer-groups', [ContactApiController::class, 'getCustomerGroups']);

        // Sell / POS
        Route::apiResource('sells', SellApiController::class);
        Route::get('sells/{id}/payments', [SellApiController::class, 'getPayments']);
        Route::post('sells/{id}/add-payment', [SellApiController::class, 'addPayment']);
        Route::get('sells/{id}/print', [SellApiController::class, 'printInvoice']);
        Route::get('sells/drafts', [SellApiController::class, 'getDrafts']);
        Route::get('sells/quotations', [SellApiController::class, 'getQuotations']);
        Route::post('sells/{id}/convert-to-invoice', [SellApiController::class, 'convertToInvoice']);
        Route::post('sells/{id}/convert-to-proforma', [SellApiController::class, 'convertToProforma']);
        Route::get('sells/{id}/duplicate', [SellApiController::class, 'duplicate']);
        Route::get('sell-returns', [SellApiController::class, 'getSellReturns']);
        Route::post('sell-returns', [SellApiController::class, 'storeSellReturn']);
        Route::get('sell-returns/{id}', [SellApiController::class, 'getSellReturn']);

        // POS
        Route::get('pos/product-row/{variation_id}/{location_id}', [SellApiController::class, 'getProductRow']);
        Route::post('pos/payment-row', [SellApiController::class, 'getPaymentRow']);
        Route::get('pos/recent-transactions', [SellApiController::class, 'getRecentTransactions']);
        Route::get('pos/product-suggestion', [SellApiController::class, 'getProductSuggestion']);
        Route::get('pos/featured-products/{location_id}', [SellApiController::class, 'getFeaturedProducts']);

        // Purchase
        Route::apiResource('purchases', PurchaseApiController::class);
        Route::get('purchases/{id}/payments', [PurchaseApiController::class, 'getPayments']);
        Route::post('purchases/{id}/add-payment', [PurchaseApiController::class, 'addPayment']);
        Route::post('purchases/update-status', [PurchaseApiController::class, 'updateStatus']);
        Route::get('purchases/{id}/print', [PurchaseApiController::class, 'printInvoice']);
        Route::get('purchase-returns', [PurchaseApiController::class, 'getPurchaseReturns']);
        Route::post('purchase-returns', [PurchaseApiController::class, 'storePurchaseReturn']);
        Route::get('purchase-returns/{id}', [PurchaseApiController::class, 'getPurchaseReturn']);
        Route::apiResource('purchase-orders', App\Http\Controllers\API\PurchaseOrderApiController::class);

        // Sales Orders
        Route::apiResource('sales-orders', App\Http\Controllers\API\SalesOrderApiController::class);

        // Expenses
        Route::apiResource('expenses', ExpenseApiController::class);
        Route::get('expense-categories', [ExpenseApiController::class, 'getCategories']);

        // Payments
        Route::get('payments', [SellApiController::class, 'getAllPayments']);
        Route::get('payments/{id}', [SellApiController::class, 'getPayment']);
        Route::get('payments/contact-due/{contact_id}', [SellApiController::class, 'getContactDue']);
        Route::post('payments/pay-contact-due', [SellApiController::class, 'payContactDue']);

        // Stock
        Route::get('stock/current', [ReportApiController::class, 'getCurrentStock']);
        Route::get('stock/details', [ReportApiController::class, 'getStockDetails']);
        Route::get('stock/expiry', [ReportApiController::class, 'getStockExpiry']);
        Route::get('stock/value', [ReportApiController::class, 'getStockValue']);
        Route::apiResource('stock-adjustments', App\Http\Controllers\API\StockAdjustmentApiController::class);
        Route::get('stock-transfers', [ReportApiController::class, 'getStockTransfers']);
        Route::apiResource('opening-stock', App\Http\Controllers\API\OpeningStockApiController::class);

        // Reports
        Route::get('reports/sales', [ReportApiController::class, 'getSalesReport']);
        Route::get('reports/purchases', [ReportApiController::class, 'getPurchaseReport']);
        Route::get('reports/profit-loss', [ReportApiController::class, 'getProfitLoss']);
        Route::get('reports/trending-products', [ReportApiController::class, 'getTrendingProducts']);
        Route::get('reports/tax', [ReportApiController::class, 'getTaxReport']);
        Route::get('reports/expense', [ReportApiController::class, 'getExpenseReport']);
        Route::get('reports/register', [ReportApiController::class, 'getRegisterReport']);
        Route::get('reports/customer-group', [ReportApiController::class, 'getCustomerGroupReport']);
        Route::get('reports/sell-payment', [ReportApiController::class, 'getSellPaymentReport']);
        Route::get('reports/purchase-payment', [ReportApiController::class, 'getPurchasePaymentReport']);
        Route::get('reports/product-sell', [ReportApiController::class, 'getProductSellReport']);
        Route::get('reports/product-purchase', [ReportApiController::class, 'getProductPurchaseReport']);
        Route::get('reports/activity-log', [ReportApiController::class, 'getActivityLog']);

        // Accounts
        Route::apiResource('accounts', App\Http\Controllers\API\AccountApiController::class);
        Route::get('account-balance-sheet', [App\Http\Controllers\API\AccountApiController::class, 'balanceSheet']);
        Route::get('account-trial-balance', [App\Http\Controllers\API\AccountApiController::class, 'trialBalance']);
        Route::get('account-cash-flow', [App\Http\Controllers\API\AccountApiController::class, 'cashFlow']);

        // Discounts
        Route::get('discounts', [SellApiController::class, 'getDiscounts']);

        // Barcode / Labels
        Route::get('barcodes', [ProductApiController::class, 'getBarcodes']);
        Route::get('labels/preview', [ProductApiController::class, 'getLabelPreview']);

        // Selling Price Groups
        Route::get('selling-price-groups', [ProductApiController::class, 'getSellingPriceGroups']);

        // Invoice Schemes / Layouts
        Route::get('invoice-schemes', [SellApiController::class, 'getInvoiceSchemes']);

        // Types of Service
        Route::get('types-of-service', [SellApiController::class, 'getTypesOfService']);

        // Warranty
        Route::get('warranties', [ProductApiController::class, 'getWarranties']);

        //  T ables (Restaurant)
        Route::get('tables', [HomeApiController::class, 'getTables']);

        // Cash Register
        Route::get('cash-register', [SellApiController::class, 'getCashRegister']);
        Route::post('cash-register/close', [SellApiController::class, 'closeCashRegister']);

        // Users
        Route::get('users-list', [HomeApiController::class, 'getUsers']);
        Route::get('roles', [HomeApiController::class, 'getRoles']);
    });
});
