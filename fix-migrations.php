<?php

// Quick script to fix the phantom migration issue
// Run this from the laravel-backend directory

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Set up database connection
$capsule = new Capsule;

// Get database configuration
$config = require 'config/database.php';
$defaultConnection = $config['default'];
$connectionConfig = $config['connections'][$defaultConnection];

$capsule->addConnection($connectionConfig);
$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "🔍 Checking for phantom migration entries...\n";
    
    // Check if migrations table exists
    if (!Capsule::schema()->hasTable('migrations')) {
        echo "❌ Migrations table doesn't exist. Creating it...\n";
        
        // Create migrations table
        Capsule::schema()->create('migrations', function ($table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
        
        echo "✅ Migrations table created.\n";
    } else {
        echo "✅ Migrations table exists.\n";
        
        // Look for the problematic migration
        $problematicMigrations = Capsule::table('migrations')
            ->where('migration', 'like', '%162022_08_03_000000_create_vector_extension%')
            ->get();
        
        if ($problematicMigrations->count() > 0) {
            echo "🚨 Found phantom migration entries:\n";
            foreach ($problematicMigrations as $migration) {
                echo "   - {$migration->migration}\n";
            }
            
            // Delete the phantom entries
            $deleted = Capsule::table('migrations')
                ->where('migration', 'like', '%162022_08_03_000000_create_vector_extension%')
                ->delete();
            
            echo "🗑️  Deleted {$deleted} phantom migration entries.\n";
        } else {
            echo "✅ No phantom migration entries found.\n";
        }
        
        // Show all current migrations
        echo "\n📋 Current migration entries:\n";
        $migrations = Capsule::table('migrations')->orderBy('batch')->orderBy('migration')->get();
        
        if ($migrations->count() > 0) {
            foreach ($migrations as $migration) {
                echo "   - {$migration->migration} (batch: {$migration->batch})\n";
            }
        } else {
            echo "   No migrations recorded.\n";
        }
    }
    
    echo "\n✅ Migration cleanup completed successfully!\n";
    echo "💡 You can now run: php artisan migrate\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "💡 Try running: php artisan migrate:install\n";
}
