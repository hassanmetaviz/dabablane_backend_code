<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blane;
use App\Http\Resources\Back\V1\BlaneResource;
use Illuminate\Support\Facades\Log;

class TestBlaneResource extends Command
{
    protected $signature = 'blane:test-resource {id? : The ID of the blane to test}';
    protected $description = 'Test BlaneResource transformation to identify errors';

    public function handle()
    {
        $blaneId = $this->argument('id');

        if ($blaneId) {
            $blane = Blane::find($blaneId);
            if (!$blane) {
                $this->error("Blane with ID {$blaneId} not found");
                return 1;
            }
            $blanes = collect([$blane]);
        } else {
            $blanes = Blane::limit(5)->get();
        }

        $this->info('Testing BlaneResource transformation...');
        $this->newLine();

        foreach ($blanes as $blane) {
            $this->info("Testing Blane ID: {$blane->id} - {$blane->name}");

            try {
                // Test 1: Basic model access
                $this->line('  1. Testing basic model attributes...');
                $this->line("     - ID: {$blane->id}");
                $this->line("     - Name: {$blane->name}");
                $this->line("     - Vendor ID: " . ($blane->vendor_id ?? 'NULL'));
                $this->line("     ✓ Basic attributes OK");

                // Test 2: Vendor relationship
                $this->line('  2. Testing vendor relationship...');
                try {
                    $vendor = $blane->vendor;
                    if ($vendor) {
                        $this->line("     - Vendor ID: {$vendor->id}");
                        $this->line("     - Vendor Name: {$vendor->name}");
                        $this->line("     ✓ Vendor relationship OK");
                    } else {
                        $this->line("     - Vendor is NULL (OK for blanes without vendor)");
                    }
                } catch (\Exception $e) {
                    $this->error("     ✗ Vendor relationship failed: " . $e->getMessage());
                    $this->line("     Error in: " . $e->getFile() . ":" . $e->getLine());
                    return 1;
                }

                // Test 3: Other relationships
                $this->line('  3. Testing other relationships...');
                try {
                    $category = $blane->category;
                    $subcategory = $blane->subcategory;
                    $this->line("     ✓ Category: " . ($category ? $category->name : 'NULL'));
                    $this->line("     ✓ Subcategory: " . ($subcategory ? $subcategory->name : 'NULL'));
                } catch (\Exception $e) {
                    $this->error("     ✗ Relationships failed: " . $e->getMessage());
                    return 1;
                }

                // Test 4: Resource transformation
                $this->line('  4. Testing BlaneResource transformation...');
                try {
                    $resource = new BlaneResource($blane);
                    $array = $resource->toArray(request());
                    $this->line("     ✓ Resource transformation OK");
                    $this->line("     - Keys in resource: " . implode(', ', array_keys($array)));
                } catch (\Exception $e) {
                    $this->error("     ✗ Resource transformation failed!");
                    $this->error("     Error: " . $e->getMessage());
                    $this->error("     File: " . $e->getFile() . ":" . $e->getLine());
                    $this->error("     Trace: " . $e->getTraceAsString());
                    return 1;
                }

                $this->info("  ✓ Blane ID {$blane->id} passed all tests");
                $this->newLine();

            } catch (\Exception $e) {
                $this->error("✗ Failed for Blane ID {$blane->id}");
                $this->error("Error: " . $e->getMessage());
                $this->error("File: " . $e->getFile() . ":" . $e->getLine());
                return 1;
            }
        }

        $this->info('All tests passed!');
        return 0;
    }
}



