<?php

namespace App\Console\Commands;

use App\Mail\VendorRegistrationNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestVendorEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vendor:test-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test vendor registration email notification';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('🧪 VENDOR REGISTRATION EMAIL TEST');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // Get and display the admin email
        $adminEmail = config('mail.contact_address');
        $fromEmail = config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->info('📧 EMAIL CONFIGURATION:');
        $this->line('───────────────────────────────────────────────────────────');
        $this->line('Admin Email (Recipient): ' . ($adminEmail ? "✅ $adminEmail" : "❌ NOT SET"));
        $this->line('From Email: ' . ($fromEmail ? $fromEmail : "NOT SET"));
        $this->line('From Name: ' . ($fromName ? $fromName : "NOT SET"));
        $this->newLine();

        if (!$adminEmail || $adminEmail === 'contact@example.com') {
            $this->error('⚠️  WARNING: Admin email is not configured!');
            $this->line('   Please set MAIL_CONTACT_ADDRESS in your .env file:');
            $this->line('   MAIL_CONTACT_ADDRESS=admin@yourdomain.com');
            $this->newLine();
            return 1;
        }

        $this->info('📨 MAIL DRIVER CONFIGURATION:');
        $this->line('───────────────────────────────────────────────────────────');
        $this->line('Mail Driver: ' . config('mail.default'));
        $this->line('Mail Host: ' . config('mail.mailers.smtp.host'));
        $this->line('Mail Port: ' . config('mail.mailers.smtp.port'));
        $this->newLine();

        if (!$this->confirm('Do you want to send a test email to ' . $adminEmail . '?', true)) {
            $this->warn('Test cancelled.');
            return 0;
        }

        $this->info('📤 SENDING TEST EMAIL...');
        $this->line('───────────────────────────────────────────────────────────');

        try {
            // Create a test vendor
            $testVendor = User::create([
                'name' => 'Test Vendor ' . date('H:i:s'),
                'email' => 'testvendor' . time() . '@example.com',
                'firebase_uid' => 'test_firebase_' . time(),
                'phone' => '+1234567890',
                'city' => 'Test City',
                'company_name' => 'Test Company Ltd',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign vendor role
            $testVendor->assignRole('vendor');

            $this->line("✅ Test vendor created (ID: {$testVendor->id})");

            // Send email
            Mail::to($adminEmail)->send(new VendorRegistrationNotification($testVendor));

            $this->info('✅ Email sent successfully!');
            $this->newLine();

            $this->info('📬 CHECK YOUR EMAIL:');
            $this->line('───────────────────────────────────────────────────────────');
            $this->line("Please check the inbox for: $adminEmail");
            $this->line('Subject: New Vendor Registration - Action Required - Dabablane');
            $this->line('If you don\'t see it, check your spam folder.');
            $this->newLine();

            // Clean up test vendor
            $testVendor->delete();
            $this->line('🧹 Test vendor cleaned up');

        } catch (\Exception $e) {
            $this->error('❌ ERROR: Failed to send email');
            $this->line('───────────────────────────────────────────────────────────');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            $this->line('💡 TROUBLESHOOTING:');
            $this->line('───────────────────────────────────────────────────────────');
            $this->line('1. Check your .env file for mail configuration:');
            $this->line('   MAIL_MAILER=smtp');
            $this->line('   MAIL_HOST=your-smtp-host');
            $this->line('   MAIL_PORT=587');
            $this->line('   MAIL_USERNAME=your-email@domain.com');
            $this->line('   MAIL_PASSWORD=your-password');
            $this->line('   MAIL_ENCRYPTION=tls');
            $this->line("   MAIL_CONTACT_ADDRESS=$adminEmail");
            $this->newLine();
            $this->line('2. For testing, you can use \'log\' driver:');
            $this->line('   MAIL_MAILER=log');
            $this->line('   Then check storage/logs/laravel.log');
            $this->newLine();
            return 1;
        }

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('✨ Test completed!');
        $this->info('═══════════════════════════════════════════════════════════');

        return 0;
    }
}
