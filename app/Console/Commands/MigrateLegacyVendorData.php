<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LegacySupport;

class MigrateLegacyVendorData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'vendor:migrate-legacy
                            {--dry-run : Show what would be migrated without making changes}
                            {--model= : Migrate specific model only (blanes, orders, reservations, ratings)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate legacy commerce_name references to vendor_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $model = $this->option('model');

        if ($dryRun) {
            $this->info('Dry run mode - no changes will be made.');
            $this->newLine();
        }

        // Show current counts
        $this->info('Current legacy data status:');
        $this->table(
            ['Model', 'Legacy Records'],
            [
                ['Blanes', LegacySupport::getLegacyBlanesCount()],
            ]
        );
        $this->newLine();

        if ($dryRun) {
            return 0;
        }

        if (!$this->confirm('Do you want to proceed with migration?')) {
            $this->info('Migration cancelled.');
            return 0;
        }

        $results = [];

        if (!$model || $model === 'blanes') {
            $this->info('Migrating blanes...');
            $results['blanes'] = LegacySupport::migrateLegacyBlanes();
        }

        if (!$model || $model === 'orders') {
            $this->info('Migrating orders...');
            $results['orders'] = LegacySupport::migrateLegacyOrders();
        }

        if (!$model || $model === 'reservations') {
            $this->info('Migrating reservations...');
            $results['reservations'] = LegacySupport::migrateLegacyReservations();
        }

        if (!$model || $model === 'ratings') {
            $this->info('Migrating ratings...');
            $results['ratings'] = LegacySupport::migrateLegacyRatings();
        }

        $this->newLine();
        $this->info('Migration complete!');
        $this->newLine();

        // Show results
        foreach ($results as $modelName => $result) {
            $this->info(ucfirst($modelName) . ':');
            $this->line("  - Migrated: {$result['migrated']}");
            if (isset($result['orphaned'])) {
                $this->line("  - Orphaned: {$result['orphaned']}");
            }
            if (isset($result['failed'])) {
                $this->line("  - Failed: {$result['failed']}");
            }
        }

        // Check for remaining legacy data
        $remainingLegacy = LegacySupport::getLegacyBlanesCount();
        if ($remainingLegacy > 0) {
            $this->newLine();
            $this->warn("Warning: {$remainingLegacy} blanes still have legacy commerce_name without vendor_id.");
            $this->warn("These may be orphaned records without matching vendors.");
        }

        return 0;
    }
}
