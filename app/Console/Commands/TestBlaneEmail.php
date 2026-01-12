<?php

namespace App\Console\Commands;

use App\Mail\BlaneCreationNotification;
use App\Models\Blane;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestBlaneEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blane:test-email
        {--to= : Recipient email address (defaults to MAIL_CONTACT_ADDRESS)}
        {--blane-id= : Existing blane ID to use (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test "blane creation" email (CLI-only)';

    public function handle(): int
    {
        $to = $this->option('to') ?: config('mail.contact_address');

        if (!$to || $to === 'contact@example.com') {
            $this->error('Recipient email is not configured.');
            $this->line('Set MAIL_CONTACT_ADDRESS in your .env or pass --to=');
            return 1;
        }

        $blaneId = $this->option('blane-id');
        $blane = null;

        if ($blaneId) {
            $blane = Blane::find($blaneId);
            if (!$blane) {
                $this->error("Blane with ID {$blaneId} not found.");
                return 1;
            }
        } else {
            $blane = Blane::whereNotNull('slug')->first();
        }

        if (!$blane) {
            // Create an in-memory dummy blane (no DB write) so email templates can render.
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

        $this->info("About to send a test blane email to: {$to}");

        if (!$this->confirm('Continue?', true)) {
            $this->warn('Cancelled.');
            return 0;
        }

        try {
            Mail::to($to)->send(new BlaneCreationNotification($blane));
            $this->info('Email sent successfully.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
            return 1;
        }
    }
}

