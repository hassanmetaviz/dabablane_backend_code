<?php
/**
 * Test Notifications in Tinker
 * 
 * Copy and paste these commands into Laravel Tinker:
 * php artisan tinker
 */

// ========================================
// TEST 1: Vendor Registration Notification
// ========================================
echo "=== Testing Vendor Registration Notification ===\n";

// Get admin
$admin = \App\Models\User::role('admin')->first();
echo "Admin: {$admin->email} (ID: {$admin->id})\n";
$beforeCount = $admin->notifications()->count();
echo "Notifications before: {$beforeCount}\n\n";

// Create test vendor
$testVendor = \App\Models\User::updateOrCreate(
    ['email' => 'test-vendor-notif@test.com'],
    [
        'name' => 'Test Vendor Notif',
        'password' => bcrypt('password'),
        'company_name' => 'Test Company',
        'phone' => '+1234567890',
        'status' => 'pending'
    ]
);
$testVendor->assignRole('vendor');

// Send notification
$admin->notify(new \App\Notifications\VendorRegistrationNotification($testVendor));

// Check result
$afterCount = $admin->notifications()->count();
$latest = $admin->notifications()->latest()->first();
echo "Notifications after: {$afterCount}\n";
echo "Latest notification:\n";
echo "  - Type: {$latest->type}\n";
echo "  - Vendor: " . ($latest->data['vendor_name'] ?? 'N/A') . "\n";
echo "  - Email: " . ($latest->data['email'] ?? 'N/A') . "\n";
echo "  - Created: {$latest->created_at}\n\n";

// ========================================
// TEST 2: Blane Creation Notification
// ========================================
echo "=== Testing Blane Creation Notification ===\n";

$beforeCount = $admin->notifications()->count();
echo "Notifications before: {$beforeCount}\n\n";

// Create test blane
$testBlane = \App\Models\Blane::create([
    'name' => 'Test Blane Notification',
    'commerce_name' => 'Test Commerce',
    'categories_id' => 1, // Make sure this category exists
    'prix_par_personne' => 100,
    'expiration_date' => now()->addMonths(1),
]);

// Send notification
$admin->notify(new \App\Notifications\BlaneCreationNotification($testBlane));

// Check result
$afterCount = $admin->notifications()->count();
$latest = $admin->notifications()->latest()->first();
echo "Notifications after: {$afterCount}\n";
echo "Latest notification:\n";
echo "  - Type: {$latest->type}\n";
echo "  - Blane: " . ($latest->data['blane_name'] ?? 'N/A') . "\n";
echo "  - Created: {$latest->created_at}\n\n";

// ========================================
// TEST 3: Contact Form Notification
// ========================================
echo "=== Testing Contact Form Notification ===\n";

$beforeCount = $admin->notifications()->count();
echo "Notifications before: {$beforeCount}\n\n";

// Create test contact
$testContact = \App\Models\Contact::create([
    'fullName' => 'Test User',
    'email' => 'testuser@test.com',
    'phone' => '+1234567890',
    'subject' => 'Test Subject',
    'message' => 'This is a test message',
    'type' => 'client',
    'status' => 'pending'
]);

// Send notification
$admin->notify(new \App\Notifications\ContactFormNotification($testContact));

// Check result
$afterCount = $admin->notifications()->count();
$latest = $admin->notifications()->latest()->first();
echo "Notifications after: {$afterCount}\n";
echo "Latest notification:\n";
echo "  - Type: {$latest->type}\n";
echo "  - Sender: " . ($latest->data['sender_name'] ?? 'N/A') . "\n";
echo "  - Subject: " . ($latest->data['subject'] ?? 'N/A') . "\n";
echo "  - Created: {$latest->created_at}\n\n";

echo "=== All Tests Complete ===\n";

// Clean up (optional)
// $testVendor->delete();
// $testBlane->delete();
// $testContact->delete();


