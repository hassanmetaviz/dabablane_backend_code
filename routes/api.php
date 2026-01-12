<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Back\V1\MobileAuthController;
use App\Http\Controllers\Api\Back\V1\VendorAuthController;
use App\Http\Controllers\Api\Back\V1\SocialAuthController;
use App\Http\Controllers\Api\Back\V1\UserProfileController;
use App\Http\Controllers\Api\Back\V1\VendorProfileController;
use App\Http\Controllers\Api\Back\V1\AdminVendorController;
use App\Http\Controllers\Api\Back\V1\AdminSubscriptionController;
use App\Http\Controllers\Api\Back\V1\VendorSubscriptionController;
use App\Http\Controllers\Api\Back\V1\TermsConditionController;

// Sanctum-protected route to get the authenticated user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication routes for API
Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::delete('/deleteAccount', [UserProfileController::class, 'deleteAccount'])->middleware('auth:sanctum');
Route::post('/signup', [MobileAuthController::class, 'mobileSignup']);
Route::post('/signin', [MobileAuthController::class, 'mobileLogin']);
Route::post('/vendorSignup', [VendorAuthController::class, 'vendorSignup']);
Route::post('/vendorSignin', [VendorAuthController::class, 'vendorLogin']);
Route::post('/forgotVendorPassword', [VendorAuthController::class, 'forgotVendorPassword']);
Route::post('/checkVendorCreatedByAdmin', [VendorAuthController::class, 'checkVendorCreatedByAdmin']);
Route::post('/socialLogin', [SocialAuthController::class, 'socialLogin']);
Route::put('/updateProfile', [UserProfileController::class, 'updateProfile'])->middleware('auth:sanctum');
Route::post('/updateVendor', [VendorProfileController::class, 'updateVendor'])->middleware('auth:sanctum');
Route::put('/updateVendorPassword', [VendorProfileController::class, 'updateVendorPassword'])->middleware('auth:sanctum');
Route::post('/setVendorPassword', [VendorProfileController::class, 'setVendorPassword'])->middleware('auth:sanctum');


Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    Route::post('/createVendor', [AdminVendorController::class, 'createVendorByAdmin']);

    Route::put('/updateVendor', [AdminVendorController::class, 'updateVendorByAdmin']);
    Route::put('/updateVendor/{vendorId}', [AdminVendorController::class, 'updateVendorByAdmin']);
    Route::post('/resetVendorPassword', [AdminVendorController::class, 'resetVendorPasswordByAdmin']);
    Route::post('/resetVendorPassword/{vendorId}', [AdminVendorController::class, 'resetVendorPasswordByAdmin']);
});

Route::get('/getAllVendors', [AdminVendorController::class, 'getAllVendors']);
Route::get('/getVendorByIdOrCompanyName', [AdminVendorController::class, 'getVendorByIdOrCompanyName'])->middleware('auth:sanctum');
Route::patch('/changeVendorStatus/{id}', [AdminVendorController::class, 'changeVendorStatus'])->middleware('auth:sanctum');


require __DIR__ . '/api/front/v1.php';
require __DIR__ . '/api/back/v1.php';


Route::prefix('back')->as('back.')->middleware(["auth:sanctum"])->group(function () {
    Route::prefix('v1')->as('v1.')->group(function () {

        Route::middleware(["auth:sanctum", 'role:admin|user|vendor'])->group(function () {

        });

        Route::middleware(['role:admin|user|vendor'])->prefix('admin/subscriptions')->group(function () {
            Route::post('plans', [AdminSubscriptionController::class, 'createPlan']);
            Route::put('plans/{plan}', [AdminSubscriptionController::class, 'updatePlan']);
            Route::get('plans', [AdminSubscriptionController::class, 'listPlans']);

            Route::post('add-ons', [AdminSubscriptionController::class, 'createAddOn']);
            Route::put('add-ons/{addOn}', [AdminSubscriptionController::class, 'updateAddOn']);
            Route::get('add-ons', [AdminSubscriptionController::class, 'listAddOns']);

            Route::post('promo-codes', [AdminSubscriptionController::class, 'createPromoCode']);
            Route::put('promo-codes/{promoCode}', [AdminSubscriptionController::class, 'updatePromoCode']);
            Route::get('promo-codes', [AdminSubscriptionController::class, 'listPromoCodes']);

            Route::post('configurations', [AdminSubscriptionController::class, 'updateConfiguration']);
            Route::get('configurations', [AdminSubscriptionController::class, 'getConfiguration']);

            Route::post('purchases/{purchase}/activate', [AdminSubscriptionController::class, 'activatePurchase']);
            Route::delete('plans/{plan}', [AdminSubscriptionController::class, 'deletePlan']);
            Route::delete('add-ons/{addOn}', [AdminSubscriptionController::class, 'deleteAddOn']);
            Route::delete('promo-codes/{promoCode}', [AdminSubscriptionController::class, 'deletePromoCode']);

            Route::post('purchases/manual', [AdminSubscriptionController::class, 'createManualPurchase']);

            Route::get('allVendorsSubscription', [AdminSubscriptionController::class, 'getVendorsWithSubscriptions']);

            Route::post('commissionChart/upload', [AdminSubscriptionController::class, 'uploadCommissionChart']);
            Route::post('commissionChart/{commissionChart}', [AdminSubscriptionController::class, 'updateCommissionChart']);
            Route::delete('commissionChart/{commissionChart}', [AdminSubscriptionController::class, 'deleteCommissionChart']);
            Route::get('commissionChart', [AdminSubscriptionController::class, 'listCommissionCharts']);

            Route::get('getAllVendorsList', [AdminSubscriptionController::class, 'getAllVendorsList']);

            Route::get('commissionChart/{commissionChart}/download', [AdminSubscriptionController::class, 'downloadCommissionChart']);
        });

        Route::middleware(['role:admin|user|vendor'])->prefix('vendor/subscriptions')->group(function () {
            Route::get('plans', [VendorSubscriptionController::class, 'getPlans']);
            Route::get('add-ons', [VendorSubscriptionController::class, 'getAddOns']);
            Route::post('promo-codes/apply', [VendorSubscriptionController::class, 'applyPromoCode']);
            Route::post('purchases', [VendorSubscriptionController::class, 'createPurchase']);
            Route::get('purchases', [VendorSubscriptionController::class, 'getPurchaseHistory']);
            Route::get('status', [VendorSubscriptionController::class, 'getSubscriptionStatus']);
            Route::get('invoices/{invoice}', [VendorSubscriptionController::class, 'downloadInvoice']);
            Route::get('commissionChartVendor', [VendorSubscriptionController::class, 'getCommissionCharts']);
            Route::get('commissionChartVendor/{commissionChart}/download', [VendorSubscriptionController::class, 'downloadCommissionChart']);
        });
    });
});


Route::get('/vendors/getVendorByIdOrCompanyName', [AdminVendorController::class, 'getVendorByIdOrCompanyNamePublic']);


Route::post('subscriptions/cmi-callback', [VendorSubscriptionController::class, 'handleCmiCallback']);

Route::post('subscriptions/payment/initiate', [App\Http\Controllers\Api\Front\V1\SubscriptionPaymentController::class, 'initiatePayment'])->middleware('auth:sanctum');
Route::post('subscriptions/payment/callback', [App\Http\Controllers\Api\Front\V1\SubscriptionPaymentController::class, 'handleCallback']);
Route::get('subscriptions/payment/success', [App\Http\Controllers\Api\Front\V1\SubscriptionPaymentController::class, 'success']);
Route::get('subscriptions/payment/failure', [App\Http\Controllers\Api\Front\V1\SubscriptionPaymentController::class, 'failure']);
Route::get('subscriptions/payment/timeout', [App\Http\Controllers\Api\Front\V1\SubscriptionPaymentController::class, 'timeout']);
Route::post('subscriptions/payment/retry', [App\Http\Controllers\Api\Front\V1\SubscriptionPaymentController::class, 'retryPayment'])->middleware('auth:sanctum');


Route::prefix('terms-conditions')->group(function () {
    Route::get('/', [TermsConditionController::class, 'index']);
    Route::get('/active', [TermsConditionController::class, 'getActiveTerms']);
    Route::get('/{id}', [TermsConditionController::class, 'show']);
    Route::post('/', [TermsConditionController::class, 'store']);
    Route::put('/{id}', [TermsConditionController::class, 'update']);
    Route::patch('/{id}/toggle-status', [TermsConditionController::class, 'toggleStatus']);
    Route::delete('/{id}', [TermsConditionController::class, 'destroy']);
});
