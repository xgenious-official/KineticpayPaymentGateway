<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/* frontend routes */
Route::prefix('kineticpaypaymentgateway')->group(function() {
    Route::post("landlord-price-plan-kineticpay",[\Modules\KineticpayPaymentGateway\Http\Controllers\KineticpayPaymentGatewayController::class,"landlordPricePlanIpn"])
//        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->name("kineticpaypaymentgateway.landlord.price.plan.ipn");

});


/* tenant payment ipn route*/
Route::middleware([
    'web',
    \App\Http\Middleware\Tenant\InitializeTenancyByDomainCustomisedMiddleware::class,
    PreventAccessFromCentralDomains::class
])->prefix('kineticpaypaymentgateway')->group(function () {
    Route::get("tenant-price-plan-wipay",[\Modules\KineticpayPaymentGateway\Http\Controllers\KineticpayPaymentGatewayController::class,"TenantSiteswayIpn"])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->name("kineticpaypaymentgateway.tenant.price.plan.ipn");

});

/* admin panel routes landlord */
Route::group(['middleware' => ['auth:admin','adminglobalVariable', 'set_lang'],'prefix' => 'admin-home'],function () {
    Route::prefix('kineticpaypaymentgateway')->group(function() {
        Route::get('/settings', [\Modules\KineticpayPaymentGateway\Http\Controllers\KineticpayPaymentGatewayAdminPanelController::class,"settings"])
            ->name("kineticpaypaymentgateway.landlord.admin.settings");
        Route::post('/settings', [\Modules\KineticpayPaymentGateway\Http\Controllers\KineticpayPaymentGatewayAdminPanelController::class,"settingsUpdate"]);
    });
});


Route::group(['middleware' => [
    \App\Http\Middleware\Tenant\InitializeTenancyByDomainCustomisedMiddleware::class,
    PreventAccessFromCentralDomains::class,
    'auth:admin',
    'tenant_admin_glvar',
    'package_expire',
    'tenantAdminPanelMailVerify',
    'tenant_status',
    'set_lang'
    ],'prefix' => 'admin-home'],function () {
    Route::prefix('kineticpaypaymentgateway/tenant')->group(function() {
        Route::get('/settings', [\Modules\KineticpayPaymentGateway\Http\Controllers\KineticpayPaymentGatewayAdminPanelController::class,"settings"])
            ->name("kineticpaypaymentgateway.tenant.admin.settings");
        Route::post('/settings', [\Modules\KineticpayPaymentGateway\Http\Controllers\KineticpayPaymentGatewayAdminPanelController::class,"settingsUpdate"]);
    });
});

