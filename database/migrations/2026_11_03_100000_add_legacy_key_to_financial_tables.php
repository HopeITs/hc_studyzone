<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency key for the legacy DBRestaurantProV5 import: each imported
     * row remembers its legacy primary key (e.g. "item:42"), so re-running
     * finance:import-legacy can skip rows that already exist.
     */
    private const TABLES = ['inventory_items', 'invoices', 'expenses', 'stock_movements'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->string('legacy_key', 100)->nullable();
                // NULL rows (everything created inside the app) never collide.
                $blueprint->unique(['center_id', 'legacy_key'], "{$table}_center_legacy_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique("{$table}_center_legacy_unique");
                $blueprint->dropColumn('legacy_key');
            });
        }
    }
};
