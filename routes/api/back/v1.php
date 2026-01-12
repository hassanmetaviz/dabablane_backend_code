<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Back\V1\CategoryController;
use App\Http\Controllers\Api\Back\V1\SubcategoryController;
use App\Http\Controllers\Api\Back\V1\UserController;
use App\Http\Controllers\Api\Back\V1\BlanController;
use App\Http\Controllers\Api\Back\V1\BlaneCatalogController;
use App\Http\Controllers\Api\Back\V1\AdminVendorController;
use App\Http\Controllers\Api\Back\V1\BlaneShareController;
use App\Http\Controllers\Api\Back\V1\BlaneImportController;
use App\Http\Controllers\Api\Back\V1\BlanImageController;
use App\Http\Controllers\Api\Back\V1\AddressController;
use App\Http\Controllers\Api\Back\V1\CityController;
use App\Http\Controllers\Api\Back\V1\CouponController;
use App\Http\Controllers\Api\Back\V1\FaqController;
use App\Http\Controllers\Api\Back\V1\MenuItemController;
use App\Http\Controllers\Api\Back\V1\MerchantController;
use App\Http\Controllers\Api\Back\V1\OrderController;
use App\Http\Controllers\Api\Back\V1\ReservationController;
use App\Http\Controllers\Api\Back\V1\ShippingDetailController;
use App\Http\Controllers\Api\Back\V1\SiteFeedbackController;
use App\Http\Controllers\Api\Back\V1\AnalyticsController;
use App\Http\Controllers\Api\Back\V1\ContactController;
use App\Http\Controllers\Api\Back\V1\RatingController;
use App\Http\Controllers\Api\Back\V1\CustomersController;
use App\Http\Controllers\Api\Back\V1\NotificationController;
use App\Http\Controllers\Api\Back\V1\BannerController;
use App\Http\Controllers\Api\Back\V1\MobileBannerController;
use App\Http\Controllers\Api\Back\V1\CommissionController;
use App\Http\Controllers\Api\Back\V1\VendorPaymentController;

// Apply Sanctum middleware to protect all routes in this group
// Note: This file is included in routes/api.php, so we don't need the 'back' prefix here
Route::prefix('back')->as('back.')->middleware(["auth:sanctum", "throttle:api"])->group(function () {
    Route::prefix('v1')->as('v1.')->group(function () {

        // All routes accessible by both admin and user roles
        Route::middleware(['role:admin|user|vendor'])->group(function () {

            // Blane related routes
            Route::get('/blanes/search', [BlaneCatalogController::class, 'search']);

            // Sensitive operations with stricter rate limiting
            Route::middleware(['throttle:strict'])->group(function () {
                Route::post('/blanes/bulk-delete', [BlanController::class, 'bulkDestroy']);
                Route::post('/blanes/import', [BlaneImportController::class, 'import']);
                Route::post('blanes/{id}/share', [BlaneShareController::class, 'generateShareLink']);
            });

            Route::apiResource('blanes', BlanController::class);
            Route::patch('blanes/{id}/update-status', [BlanController::class, 'updateStatus']);

            Route::get('/getBlanesByVendor', [BlaneCatalogController::class, 'getBlanesByVendor']);
            Route::get('/getAllFilterBlane', [BlaneCatalogController::class, 'getAllFilterBlane']);
            Route::put('/updateBlane/{id}', [BlanController::class, 'updateBlane']);
            Route::get('/getVendorByBlane', [BlaneCatalogController::class, 'getVendorByBlane']);
            Route::post('updateBlaneImage/{id}', [BlanImageController::class, 'updateBlaneImage']);

            Route::post('/uploadVendorImages', [BlanImageController::class, 'uploadVendorImages']);
            Route::post('/uploadBlaneMedia', [BlanImageController::class, 'uploadBlaneMedia']);

            // Blane sharing routes (delete and patch don't need strict limiting)
            Route::delete('blanes/{id}/share', [BlaneShareController::class, 'revokeShareLink']);
            Route::patch('blanes/{id}/visibility', [BlaneShareController::class, 'updateVisibility']);

            Route::apiResource('blan-images', BlanImageController::class);

            // User management
            Route::apiResource('users', UserController::class);
            Route::patch('users/{id}/assign-roles', [UserController::class, 'assignRoles']);

            // Categories and subcategories
            Route::apiResource('categories', CategoryController::class);
            Route::match(['put', 'patch'], 'categories/{id}/status', [CategoryController::class, 'updateStatus']);
            Route::match(['put', 'patch'], 'categories/{id}/update-status', [CategoryController::class, 'updateStatus']);
            Route::apiResource('subcategories', SubcategoryController::class);

            // Menu items management
            Route::apiResource('menu-items', MenuItemController::class);
            Route::patch('menu-items/{id}/update-status', [MenuItemController::class, 'updateStatus']);

            // Cities management
            Route::apiResource('cities', CityController::class);

            // Other resources
            Route::apiResource('addresses', AddressController::class);
            Route::apiResource('coupons', CouponController::class);
            Route::apiResource('faqs', FaqController::class);
            Route::apiResource('merchants', MerchantController::class);
            Route::apiResource('shipping-details', ShippingDetailController::class);
            Route::apiResource('site-feedbacks', SiteFeedbackController::class);
            Route::apiResource('ratings', RatingController::class);
            Route::apiResource('banners', BannerController::class);
            Route::apiResource('mobile-banners', MobileBannerController::class);

            // Status updates
            Route::patch('coupons/{id}/update-status', [CouponController::class, 'updateStatus']);

            // Orders and reservations management
            Route::apiResource('orders', OrderController::class);
            Route::apiResource('reservations', ReservationController::class);
            Route::get('/reservations/get-id-by-number/{num_res}', [ReservationController::class, 'getIdByNumber']);
            Route::get('/reservationslist', [ReservationController::class, 'reservationlist']);
            Route::get('/getOrdersList', [OrderController::class, 'getOrdersList']);
            Route::get('/getReservationsAndOrders', [ReservationController::class, 'getReservationsAndOrders']);
            Route::get('/getVendorReservationsAndOrders', [ReservationController::class, 'getVendorReservationsAndOrders']);
            Route::get('/getVendorPendingReservations', [ReservationController::class, 'getVendorPendingReservations']);
            Route::patch('orders/{id}/update-status', [OrderController::class, 'updateStatus']);
            Route::patch('reservations/{id}/update-status', [ReservationController::class, 'updateStatus']);

            // Contacts and customers
            Route::apiResource('contacts', ContactController::class);
            Route::apiResource('customers', CustomersController::class);

            // Analytics
            Route::prefix('analytics')->group(function () {
                Route::get('/', [AnalyticsController::class, 'getAnalytics']);
                Route::get('/blanes-status', [AnalyticsController::class, 'getBlanesStatus']);
                Route::get('/near-expiration', [AnalyticsController::class, 'getNearExpiration']);
                Route::get('/status-distribution', [AnalyticsController::class, 'getStatusDistribution']);
                Route::get('/vendor', [AnalyticsController::class, 'getVendorAnalytics']);
            });

            // Notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'index']);
                Route::post('/mark-as-read/{id}', [NotificationController::class, 'markAsRead']);
                Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
                Route::delete('/{id}', [NotificationController::class, 'destroy']);
                Route::delete('/', [NotificationController::class, 'destroyAll']);
                Route::post('/check-expiration', [NotificationController::class, 'checkExpiration']);
            });

            // Commission Management (Admin only)
            Route::prefix('commissions')->group(function () {
                // Routes accessible to both admin and vendor
                Route::middleware(['role:admin|vendor'])->group(function () {
                    Route::get('/', [CommissionController::class, 'index']);
                    Route::get('/vendors/{vendorId}', [CommissionController::class, 'getVendorRate']);
                    Route::get('/category-defaults/all', [CommissionController::class, 'listAllCategoryDefaults']);
                });

                // Admin-only routes
                Route::middleware(['role:admin'])->group(function () {
                    Route::get('/settings', [CommissionController::class, 'getSettings']);
                    Route::put('/settings', [CommissionController::class, 'updateSettings']);
                    Route::post('/', [CommissionController::class, 'store']);
                    Route::put('/{id}', [CommissionController::class, 'update']);
                    Route::delete('/{id}', [CommissionController::class, 'destroy']);
                    Route::put('/vendors/{vendorId}/rate', [CommissionController::class, 'setVendorRate']);
                    // Category default commission routes
                    Route::get('/category-defaults', [CommissionController::class, 'getCategoryDefaults']);
                    Route::post('/category-defaults', [CommissionController::class, 'setCategoryDefault']);
                });
            });

            Route::middleware(['role:admin'])->prefix('vendor-payments')->group(function () {
                Route::get('/', [VendorPaymentController::class, 'index']);

                // Place specific endpoints before catch-all ID routes
                Route::get('/logs', [VendorPaymentController::class, 'logs']);
                Route::get('/dashboard', [VendorPaymentController::class, 'dashboard']);
                Route::get('/weekly-summary', [VendorPaymentController::class, 'weeklySummary']);
                Route::get('/export/excel', [VendorPaymentController::class, 'exportExcel']);
                Route::get('/export/pdf', [VendorPaymentController::class, 'exportPDF']);
                Route::get('/banking-report', [VendorPaymentController::class, 'bankingReport']);
                Route::put('/mark-processed', [VendorPaymentController::class, 'markAsProcessed']);

                // Constrain {id} to numeric so strings like 'logs' do not match
                Route::get('/{id}', [VendorPaymentController::class, 'show'])->whereNumber('id');
                Route::put('/{id}/status', [VendorPaymentController::class, 'updateStatus'])->whereNumber('id');
                Route::put('/{id}/revert', [VendorPaymentController::class, 'revertToPending'])->whereNumber('id');
                Route::put('/{id}', [VendorPaymentController::class, 'update'])->whereNumber('id');
            });
        });

    });
});

// Public routes with public rate limiting (no token required)
Route::prefix('back/v1')->middleware('throttle:public')->group(function () {
    Route::get('/vendors/getBlanesByVendor', [BlaneCatalogController::class, 'getBlanesByVendorPublic']);
    Route::get('/getFeaturedBlanes', [BlaneCatalogController::class, 'getFeaturedBlanes']);
    Route::get('/getBlanesByStartDate', [BlaneCatalogController::class, 'getBlanesByStartDate']);
    Route::get('/getBlanesByCategory', [BlaneCatalogController::class, 'getBlanesByCategory']);
});
