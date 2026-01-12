<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\Blane;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Rating;
use App\Models\User;

class LegacySupport
{
    /**
     * Check if any blanes still use commerce_name without vendor_id
     */
    public static function hasLegacyBlanes(): bool
    {
        return Blane::withoutGlobalScopes()
            ->whereNull('vendor_id')
            ->whereNotNull('commerce_name')
            ->exists();
    }

    /**
     * Get count of legacy blanes
     */
    public static function getLegacyBlanesCount(): int
    {
        return Blane::withoutGlobalScopes()
            ->whereNull('vendor_id')
            ->whereNotNull('commerce_name')
            ->count();
    }

    /**
     * Migrate legacy blanes to use vendor_id
     */
    public static function migrateLegacyBlanes(): array
    {
        $results = [
            'migrated' => 0,
            'failed' => 0,
            'orphaned' => 0,
        ];

        $legacyBlanes = Blane::withoutGlobalScopes()
            ->whereNull('vendor_id')
            ->whereNotNull('commerce_name')
            ->get();

        foreach ($legacyBlanes as $blane) {
            $vendor = User::role('vendor')
                ->where('company_name', $blane->commerce_name)
                ->first();

            if ($vendor) {
                $blane->vendor_id = $vendor->id;
                $blane->save();
                $results['migrated']++;

                Log::info('Migrated legacy blane to vendor_id', [
                    'blane_id' => $blane->id,
                    'vendor_id' => $vendor->id,
                    'commerce_name' => $blane->commerce_name
                ]);
            } else {
                $results['orphaned']++;
                Log::warning('Orphaned blane: no matching vendor found', [
                    'blane_id' => $blane->id,
                    'commerce_name' => $blane->commerce_name
                ]);
            }
        }

        return $results;
    }

    /**
     * Migrate legacy orders to use vendor_id
     */
    public static function migrateLegacyOrders(): array
    {
        $results = [
            'migrated' => 0,
            'failed' => 0,
        ];

        $legacyOrders = Order::withoutGlobalScopes()
            ->whereNull('vendor_id')
            ->whereNotNull('blane_id')
            ->get();

        foreach ($legacyOrders as $order) {
            $blane = Blane::withoutGlobalScopes()->find($order->blane_id);

            if ($blane && $blane->vendor_id) {
                $order->vendor_id = $blane->vendor_id;
                $order->save();
                $results['migrated']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Migrate legacy reservations to use vendor_id
     */
    public static function migrateLegacyReservations(): array
    {
        $results = [
            'migrated' => 0,
            'failed' => 0,
        ];

        $legacyReservations = Reservation::withoutGlobalScopes()
            ->whereNull('vendor_id')
            ->whereNotNull('blane_id')
            ->get();

        foreach ($legacyReservations as $reservation) {
            $blane = Blane::withoutGlobalScopes()->find($reservation->blane_id);

            if ($blane && $blane->vendor_id) {
                $reservation->vendor_id = $blane->vendor_id;
                $reservation->save();
                $results['migrated']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Migrate legacy ratings to use vendor_id
     */
    public static function migrateLegacyRatings(): array
    {
        $results = [
            'migrated' => 0,
            'failed' => 0,
        ];

        $legacyRatings = Rating::withoutGlobalScopes()
            ->whereNull('vendor_id')
            ->whereNotNull('blane_id')
            ->get();

        foreach ($legacyRatings as $rating) {
            $blane = Blane::withoutGlobalScopes()->find($rating->blane_id);

            if ($blane && $blane->vendor_id) {
                $rating->vendor_id = $blane->vendor_id;
                $rating->save();
                $results['migrated']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Run all legacy migrations
     */
    public static function migrateAll(): array
    {
        return [
            'blanes' => self::migrateLegacyBlanes(),
            'orders' => self::migrateLegacyOrders(),
            'reservations' => self::migrateLegacyReservations(),
            'ratings' => self::migrateLegacyRatings(),
        ];
    }

    /**
     * Check vendor ownership with legacy support
     * @deprecated Use BelongsToVendor trait instead
     */
    public static function isOwnedBy($model, User $user): bool
    {
        if (!config('multitenancy.legacy_commerce_name_support', true)) {
            return isset($model->vendor_id) && $model->vendor_id === $user->id;
        }

        // New way: Check by vendor_id
        if (isset($model->vendor_id) && $model->vendor_id === $user->id) {
            return true;
        }

        // Legacy way: Check by commerce_name
        if (isset($model->vendor_id) && $model->vendor_id === null &&
            isset($model->commerce_name) && $model->commerce_name === $user->company_name) {
            return true;
        }

        return false;
    }
}
