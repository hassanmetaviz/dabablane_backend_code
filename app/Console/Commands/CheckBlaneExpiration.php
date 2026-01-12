<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blane;
use App\Models\User;
use App\Models\Notification;
use App\Notifications\BlaneExpirationNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
class CheckBlaneExpiration extends Command
{
    protected $signature = 'blane:check-expiration';
    protected $description = 'Check for expired Blane records and notify the admin.';



    public function handle()
    {

        $now = Carbon::now();

        $blanes = Blane::where('expiration_date', '<=', $now)
            ->where('status', '!=', 'expired')
            ->whereNotNull('expiration_date')
            ->get();

        if ($blanes->isEmpty()) {
            $this->info('No expired Blane records found that need status update.');
            return;
        }

        $updatedCount = 0;

        foreach ($blanes as $blane) {
            try {
                $blane->update(['status' => 'expired']);
                $updatedCount++;
                $this->info('Updated blane status to expired: ' . $blane->name . ' (ID: ' . $blane->id . ')');
            } catch (\Exception $e) {
                $this->error('Failed to update blane status: ' . $blane->name . ' - ' . $e->getMessage());
            }
        }

        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            $this->error('No admin users found.');
            return;
        }

        foreach ($admins as $admin) {
            foreach ($blanes as $blane) {

                $notificationExists = Notification::where('notifiable_id', $admin->id)
                    ->where('notifiable_type', User::class)
                    ->where('type', BlaneExpirationNotification::class)
                    ->where('data->blane_id', $blane->id)
                    ->exists();

                if (!$notificationExists) {

                    $admin->notify(new BlaneExpirationNotification($blane));
                    $this->info('Notification created for admin: ' . $admin->email . ' about expired blane: ' . $blane->name);
                } else {
                    $this->info('Notification already exists for admin: ' . $admin->email . ' about expired blane: ' . $blane->name);
                }
            }
        }

        $this->info('Successfully updated ' . $updatedCount . ' blane(s) to expired status.');
        $this->info('Notifications processed successfully for ' . $admins->count() . ' admin(s) about ' . $blanes->count() . ' expired blane(s).');
    }

    public function checkExpiration()
    {
        Artisan::call('blane:check-expiration');
        return response()->json([
            'status' => 'success',
            'message' => 'Expiration check completed'
        ]);
    }

}
