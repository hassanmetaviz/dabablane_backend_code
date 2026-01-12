<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Purchase;
use App\Models\User;
use App\Mail\SubscriptionExpiringMail;
use App\Mail\SubscriptionExpiredMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckSubscriptionExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:check-expiration 
                            {--days-before=7 : Number of days before expiration to send warning email}
                            {--expiration-only : Only check for expired subscriptions (skip warning emails)}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check subscription expiration and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysBefore = (int) $this->option('days-before');
        $expirationOnly = $this->option('expiration-only');
        $dryRun = $this->option('dry-run');

        $this->info("Starting subscription expiration check...");
        $this->info("Expiration only mode: " . ($expirationOnly ? 'Yes' : 'No'));
        $this->info("Days before expiration: {$daysBefore}");
        $this->info("Dry run: " . ($dryRun ? 'Yes' : 'No'));

        if ($expirationOnly) {
            $this->checkExpired($dryRun);
        } else {
            $this->checkExpiringSoon($daysBefore, $dryRun);

            $this->checkExpired($dryRun);

            $this->checkActiveSubscriptions($dryRun);
        }

        $this->info("Subscription expiration check completed!");
    }

    /**
     * Check for subscriptions expiring soon and send warning emails
     */
    private function checkExpiringSoon($daysBefore, $dryRun)
    {
        $this->info("Checking for subscriptions expiring in {$daysBefore} days...");

        $expiringDate = Carbon::now()->addDays($daysBefore);
        $expiringPurchases = Purchase::where('status', 'completed')
            ->whereDate('end_date', $expiringDate)
            ->with(['user', 'plan'])
            ->get();

        $this->info("Found {$expiringPurchases->count()} subscriptions expiring soon");

        foreach ($expiringPurchases as $purchase) {
            try {
                if (!$dryRun) {
                    Mail::to($purchase->user->email)->send(
                        new SubscriptionExpiringMail($purchase, $daysBefore)
                    );

                    Log::info('Expiration warning email sent', [
                        'purchase_id' => $purchase->id,
                        'user_id' => $purchase->user_id,
                        'user_email' => $purchase->user->email,
                        'expires_at' => $purchase->end_date,
                        'days_remaining' => $daysBefore
                    ]);
                }

                $this->line("✓ Warning email sent to: {$purchase->user->email} (Purchase ID: {$purchase->id})");

            } catch (\Exception $e) {
                $this->error("Failed to send warning email to {$purchase->user->email}: " . $e->getMessage());
                Log::error('Failed to send expiration warning email', [
                    'purchase_id' => $purchase->id,
                    'user_email' => $purchase->user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Check for expired subscriptions and update their status
     */
    private function checkExpired($dryRun)
    {
        $this->info("Checking for expired subscriptions...");

        $expiredPurchases = Purchase::where('status', 'completed')
            ->where('end_date', '<', Carbon::now())
            ->with(['user', 'plan'])
            ->get();

        $this->info("Found {$expiredPurchases->count()} expired subscriptions");

        foreach ($expiredPurchases as $purchase) {
            try {
                if (!$dryRun) {
                    $purchase->update(['status' => 'expired']);

                    Mail::to($purchase->user->email)->send(
                        new SubscriptionExpiredMail($purchase)
                    );

                }

                $this->line("✓ Subscription expired: {$purchase->user->email} (Purchase ID: {$purchase->id})");

            } catch (\Exception $e) {
                $this->error("Failed to process expired subscription {$purchase->id}: " . $e->getMessage());
                Log::error('Failed to process expired subscription', [
                    'purchase_id' => $purchase->id,
                    'user_email' => $purchase->user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Check for subscriptions that should be active but aren't
     */
    private function checkActiveSubscriptions($dryRun)
    {
        $this->info("Checking for active subscriptions...");

        $activePurchases = Purchase::where('status', 'completed')
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>', Carbon::now())
            ->with(['user', 'plan'])
            ->get();

        $this->info("Found {$activePurchases->count()} active subscriptions");

        foreach ($activePurchases as $purchase) {
            $daysRemaining = Carbon::now()->diffInDays($purchase->end_date, false);

            Log::info('Active subscription check', [
                'purchase_id' => $purchase->id,
                'user_id' => $purchase->user_id,
                'user_email' => $purchase->user->email,
                'plan_title' => $purchase->plan->title,
                'days_remaining' => $daysRemaining,
                'expires_at' => $purchase->end_date
            ]);
        }
    }
}
