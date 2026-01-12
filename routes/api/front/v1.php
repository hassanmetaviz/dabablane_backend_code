<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Front\V1\CategoryController;
use App\Http\Controllers\Api\Front\V1\SubcategoryController;
use App\Http\Controllers\Api\Front\V1\UserController;
use App\Http\Controllers\Api\Front\V1\BlanController;
use App\Http\Controllers\Api\Front\V1\BlanImageController;
use App\Http\Controllers\Api\Front\V1\AddressController;
use App\Http\Controllers\Api\Front\V1\CityController;
use App\Http\Controllers\Api\Front\V1\CouponController;
use App\Http\Controllers\Api\Front\V1\FaqController;
use App\Http\Controllers\Api\Front\V1\HomeController;
use App\Http\Controllers\Api\Front\V1\MenuItemController;
use App\Http\Controllers\Api\Front\V1\MerchantController;
use App\Http\Controllers\Api\Front\V1\OrderController;
use App\Http\Controllers\Api\Front\V1\ReservationController;
use App\Http\Controllers\Api\Front\V1\ShippingDetailController;
use App\Http\Controllers\Api\Front\V1\SiteFeedbackController;
use App\Http\Controllers\Api\Front\V1\RatingController;
use App\Http\Controllers\Api\Front\V1\ContactController;
use App\Http\Controllers\Api\Front\V1\BannerController;
use App\Http\Controllers\Api\Front\V1\MobileBannerController;
use App\Http\Controllers\Api\Front\V1\PaymentCmiController;
use App\Http\Controllers\Api\Front\V1\VendorRevenueController;
use App\Http\Controllers\Api\Front\V1\VendorOrderController;
use App\Http\Controllers\Api\Front\V1\VendorReservationController;
use App\Http\Controllers\Api\Front\V1\VendorBlanController;


Route::prefix('front')->as('front.')->group(function () {
    Route::prefix('v1')->as('v1.')->group(function () {
        Route::get('home', [HomeController::class, 'index']);

        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('subcategories', SubcategoryController::class);
        Route::apiResource('blanes', BlanController::class);
        Route::get('blanes/shared/{token}', [BlanController::class, 'getByShareToken']);
        Route::apiResource('blan-images', BlanImageController::class);
        Route::apiResource('faqs', FaqController::class);
        Route::apiResource('menu-items', MenuItemController::class);
        Route::apiResource('cities', CityController::class);
        Route::apiResource('menu-items', MenuItemController::class)->only(['index', 'show']);
        Route::apiResource('faqs', FaqController::class)->only(['index', 'show']);
        Route::get('blanes/{id}/blaneImage', [BlanController::class, 'getblanImage']);
        Route::apiResource('blane-ratings', RatingController::class);
        Route::apiResource('coupons', CouponController::class);
        Route::post('contacts', [ContactController::class, 'store']);

        Route::patch('reservations/{id}/status', [ReservationController::class, 'changeStatus']);
        Route::patch('orders/{id}/status', [OrderController::class, 'changeStatus']);

        Route::apiResource('addresses', AddressController::class);
        Route::apiResource('banners', BannerController::class);
        Route::apiResource('mobile-banners', MobileBannerController::class)->only(['index', 'show']);
        Route::apiResource('orders', OrderController::class);
        Route::apiResource('reservations', ReservationController::class);
        Route::get('blanes/{slug}/available-time-slots', [ReservationController::class, 'getAvailableTimeSlots']);
        Route::apiResource('shipping-details', ShippingDetailController::class);
        Route::apiResource('site-feedbacks', SiteFeedbackController::class);
        Route::apiResource('user', UserController::class);
        Route::post('/payment/cmi/initiate', [PaymentCmiController::class, 'initiatePayment'])->name('payment.initiate');

        Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor/revenues')->group(function () {
            Route::get('/overview', [VendorRevenueController::class, 'overview']);
            Route::get('/transactions', [VendorRevenueController::class, 'transactions']);
            Route::post('/invoice/create', [VendorRevenueController::class, 'createInvoice']);
            Route::get('/invoice/{month}/{year}', [VendorRevenueController::class, 'downloadInvoice']);
            Route::get('/export/excel', [VendorRevenueController::class, 'exportExcel']);
            Route::get('/export/pdf', [VendorRevenueController::class, 'exportPDF']);
            Route::get('/statistics', [VendorRevenueController::class, 'statistics']);
        });

        Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->group(function () {
            Route::apiResource('orders', VendorOrderController::class);
            Route::apiResource('reservations', VendorReservationController::class);
            Route::get('blanes/{slug}', [VendorBlanController::class, 'show']);
            Route::get('blanes/{slug}/available-time-slots', [VendorReservationController::class, 'getAvailableTimeSlots']);
        });
    });

});
Route::post('/payment/cmi/callback', [PaymentCmiController::class, 'handleCallback'])->name('payment.callback');

