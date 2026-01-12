<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseBlaneIssues extends Command
{
    protected $signature = 'blane:diagnose';
    protected $description = 'Diagnose database issues with Blane model and vendor_id column';

    public function handle()
    {
        $this->info('Diagnosing Blane database issues...');
        $this->newLine();

        // Check if vendor_id column exists
        $this->info('1. Checking if vendor_id column exists in blanes table...');
        if (Schema::hasColumn('blanes', 'vendor_id')) {
            $this->info('   ✓ vendor_id column exists');
        } else {
            $this->error('   ✗ vendor_id column does NOT exist!');
            $this->warn('   → Run migration: php artisan migrate');
            return 1;
        }
        $this->newLine();

        // Check for orphaned vendor_id values
        $this->info('2. Checking for orphaned vendor_id values...');
        $orphaned = DB::table('blanes')
            ->whereNotNull('vendor_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'blanes.vendor_id');
            })
            ->count();

        if ($orphaned > 0) {
            $this->error("   ✗ Found {$orphaned} blanes with orphaned vendor_id values");
            $this->warn('   → These need to be fixed before the API will work');
        } else {
            $this->info('   ✓ No orphaned vendor_id values found');
        }
        $this->newLine();

        // Check if roles table exists and has vendor role
        $this->info('3. Checking roles table...');
        if (Schema::hasTable('roles')) {
            $vendorRole = DB::table('roles')->where('name', 'vendor')->first();
            if ($vendorRole) {
                $this->info('   ✓ vendor role exists');
            } else {
                $this->error('   ✗ vendor role does NOT exist!');
                $this->warn('   → Run seeder: php artisan db:seed --class=RoleSeeder');
            }
        } else {
            $this->error('   ✗ roles table does NOT exist!');
            $this->warn('   → Run migrations: php artisan migrate');
        }
        $this->newLine();

        // Check foreign key constraints
        $this->info('4. Checking foreign key constraints...');
        try {
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = 'blanes'
                AND COLUMN_NAME = 'vendor_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [DB::getDatabaseName()]);

            if (count($constraints) > 0) {
                $this->info('   ✓ Foreign key constraint exists for vendor_id');
            } else {
                $this->warn('   ⚠ No foreign key constraint found for vendor_id');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error checking constraints: ' . $e->getMessage());
        }
        $this->newLine();

        // Check sample blane data
        $this->info('5. Checking sample blane data...');
        try {
            $sampleBlane = DB::table('blanes')->first();
            if ($sampleBlane) {
                $this->info('   ✓ Found blanes in database');
                if (isset($sampleBlane->vendor_id)) {
                    $this->info("   → Sample blane vendor_id: " . ($sampleBlane->vendor_id ?? 'NULL'));
                }
            } else {
                $this->warn('   ⚠ No blanes found in database');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error accessing blanes table: ' . $e->getMessage());
            $this->error('   → This indicates a serious database issue');
        }
        $this->newLine();

        $this->info('Diagnosis complete!');
        return 0;
    }
}



