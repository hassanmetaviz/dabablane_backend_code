<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Get the constraint information from the database
        $tables = ['reservations', 'orders', 'blanes'];
        
        // First, alter foreign keys to allow NULL values
        foreach ($tables as $table) {
            if ($table === 'reservations' || $table === 'orders') {
                // Make columns nullable
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('blane_id')->nullable()->change();
                    $table->unsignedBigInteger('customers_id')->nullable()->change();
                });
            }
        }
        
        // Get all foreign key constraint names from information_schema
        $constraints = DB::select("
            SELECT 
                TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
            FROM 
                information_schema.KEY_COLUMN_USAGE
            WHERE 
                REFERENCED_TABLE_SCHEMA = ? 
                AND TABLE_NAME IN ('reservations', 'orders', 'blanes')
                AND REFERENCED_TABLE_NAME IN ('blanes', 'customers', 'categories', 'subcategories')
        ", [DB::getDatabaseName()]);
        
        // Drop and recreate each constraint with SET NULL
        foreach ($constraints as $constraint) {
            $tableName = $constraint->TABLE_NAME;
            $columnName = $constraint->COLUMN_NAME;
            $constraintName = $constraint->CONSTRAINT_NAME;
            $referencedTable = $constraint->REFERENCED_TABLE_NAME;
            
            // Drop the existing constraint
            DB::statement("
                ALTER TABLE `{$tableName}` 
                DROP FOREIGN KEY `{$constraintName}`
            ");
            
            // Create new constraint with SET NULL
            DB::statement("
                ALTER TABLE `{$tableName}` 
                ADD CONSTRAINT `{$constraintName}` 
                FOREIGN KEY (`{$columnName}`) 
                REFERENCES `{$referencedTable}`(id) 
                ON DELETE SET NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get all foreign key constraint names from information_schema
        $constraints = DB::select("
            SELECT 
                TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
            FROM 
                information_schema.KEY_COLUMN_USAGE
            WHERE 
                REFERENCED_TABLE_SCHEMA = ? 
                AND TABLE_NAME IN ('reservations', 'orders', 'blanes')
                AND REFERENCED_TABLE_NAME IN ('blanes', 'customers', 'categories', 'subcategories')
        ", [DB::getDatabaseName()]);
        
        // Drop and recreate each constraint with CASCADE
        foreach ($constraints as $constraint) {
            $tableName = $constraint->TABLE_NAME;
            $columnName = $constraint->COLUMN_NAME;
            $constraintName = $constraint->CONSTRAINT_NAME;
            $referencedTable = $constraint->REFERENCED_TABLE_NAME;
            
            // Drop the existing constraint
            DB::statement("
                ALTER TABLE `{$tableName}` 
                DROP FOREIGN KEY `{$constraintName}`
            ");
            
            // Create new constraint with CASCADE
            DB::statement("
                ALTER TABLE `{$tableName}` 
                ADD CONSTRAINT `{$constraintName}` 
                FOREIGN KEY (`{$columnName}`) 
                REFERENCES `{$referencedTable}`(id) 
                ON DELETE CASCADE
            ");
        }
        
        // Set required columns back to non-nullable
        $tables = ['reservations', 'orders'];
        
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('blane_id')->nullable(false)->change();
                $table->unsignedBigInteger('customers_id')->nullable(false)->change();
            });
        }
    }
};
