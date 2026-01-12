<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Back\V1\CategoryController;
use App\Http\Controllers\Api\Back\V1\SubcategoryController;
use App\Http\Controllers\Api\Back\V1\UserController;
use App\Http\Controllers\Api\Back\V1\BlanController;
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

// Apply Sanctum middleware to protect all routes in this group
Route::prefix('back')->as('back.')->middleware(["auth:sanctum"])->group(function () {
    Route::prefix('v1')->as('v1.')->group(function () {

        // All routes accessible by both admin and user roles
        Route::middleware(["auth:sanctum", 'role:admin|user|vendor'])->group(function () {

            // Blane related routes
            Route::post('/blanes/bulk-delete', [BlanController::class, 'bulkDestroy']);
            Route::post('/blanes/import', [BlanController::class, 'import']);
            Route::apiResource('blanes', BlanController::class);
            Route::patch('blanes/{id}/update-status', [BlanController::class, 'updateStatus']);
            Route::get('/getFeaturedBlanes', [BlanController::class, 'getFeaturedBlanes']);
            Route::get('/getBlanesByStartDate', [BlanController::class, 'getBlanesByStartDate']);
            Route::get('/getBlanesByCategory', [BlanController::class, 'getBlanesByCategory']);
            Route::get('/getBlanesByVendor', [BlanController::class, 'getBlanesByVendor']);
            Route::get('/getAllFilterBlane', [BlanController::class, 'getAllFilterBlane']);
            Route::put('/updateBlane/{id}', [BlanController::class, 'updateBlane']);
            Route::post('updateBlaneImage/{id}', [BlanImageController::class, 'updateBlaneImage']);

            Route::post('/uploadVendorImages', [BlanImageController::class, 'uploadVendorImages']);
            Route::post('/uploadBlaneMedia', [BlanImageController::class, 'uploadBlaneMedia']);




            // Blane sharing routes
            Route::post('blanes/{id}/share', [BlanController::class, 'generateShareLink']);
            Route::delete('blanes/{id}/share', [BlanController::class, 'revokeShareLink']);
            Route::patch('blanes/{id}/visibility', [BlanController::class, 'updateVisibility']);

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

            // Status updates
            Route::patch('coupons/{id}/update-status', [CouponController::class, 'updateStatus']);

            // Orders and reservations management
            Route::apiResource('orders', OrderController::class);
            Route::apiResource('reservations', ReservationController::class);
            // Route::apiResource('reservationslist', ReservationController::class, 'reservationlist');
            Route::get('/reservationslist', [ReservationController::class, 'reservationlist']);
            Route::get('/getOrdersList', [OrderController::class, 'getOrdersList']);
            Route::get('/getReservationsAndOrders', [ReservationController::class, 'getReservationsAndOrders']);
            Route::get('/getVendorReservationsAndOrders', [ReservationController::class, 'getVendorReservationsAndOrders']);
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
        });
    });
});
