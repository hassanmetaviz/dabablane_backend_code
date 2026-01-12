<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Front\V1\PaymentCmiController;
use Illuminate\Support\Facades\Mail;
use App\Mail\BlaneCreationNotification;
use App\Models\Blane;

// Test route for sending blane creation email
Route::get('/test/blane-email', function () {
    // Try to get an existing blane with a slug, or create a dummy one
    $blane = Blane::whereNotNull('slug')->first();

    if (!$blane) {
        // Create a dummy blane for testing
        $blane = new Blane();
        $blane->name = 'Blane de Test - Dummy Blane';
        $blane->description = 'Ceci est un blane de test pour vérifier l\'email de création de blane.';
        $blane->commerce_name = 'Commerce de Test';
        $blane->commerce_phone = '+212 6XX XXX XXX';
        $blane->city = 'Casablanca';
        $blane->district = 'Maarif';
        $blane->subdistricts = 'Maarif Centre';
        $blane->type = 'order';
        $blane->price_current = 199.99;
        $blane->price_old = 299.99;
        $blane->status = 'waiting';
        $blane->slug = 'blane-de-test-dummy-blane';
        $blane->advantages = 'Test advantage 1, Test advantage 2';
        $blane->conditions = 'Test conditions apply';
        $blane->created_at = now();
        $blane->updated_at = now();
    }

    try {
        Mail::to('hassanali.4xp@gmail.com')->send(new BlaneCreationNotification($blane));

        return response()->json([
            'success' => true,
            'message' => 'Test email sent successfully to hassanali.4xp@gmail.com',
            'blane' => [
                'id' => $blane->id,
                'name' => $blane->name,
                'slug' => $blane->slug,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to send email: ' . $e->getMessage(),
            'error' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.blane.email');
