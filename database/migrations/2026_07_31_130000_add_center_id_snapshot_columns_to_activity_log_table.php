<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same pattern as migration 2026_07_31_120000: pre-extract center_id out of the JSON
 * `properties` column into indexed generated columns, so "which center did this entry
 * happen at" can be filtered with an index seek instead of a JSON scan over 300k+ rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name', 'activity_log');
        $grammarTable = DB::connection($connection)->getTablePrefix() . $table;

        $intExpr = fn (string $path) => "JSON_UNQUOTE(JSON_EXTRACT(properties, '\$.{$path}'))";

        DB::connection($connection)->statement(
            "ALTER TABLE `{$grammarTable}`
                ADD COLUMN `center_id_attr` BIGINT UNSIGNED GENERATED ALWAYS AS (NULLIF({$intExpr('attributes.center_id')}, 'null')) STORED,
                ADD COLUMN `center_id_old` BIGINT UNSIGNED GENERATED ALWAYS AS (NULLIF({$intExpr('old.center_id')}, 'null')) STORED"
        );

        Schema::connection($connection)->table($table, function ($table) {
            $table->index('center_id_attr', 'activity_log_center_id_attr_index');
            $table->index('center_id_old', 'activity_log_center_id_old_index');
        });
    }

    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name', 'activity_log');

        Schema::connection($connection)->table($table, function ($table) {
            $table->dropIndex('activity_log_center_id_attr_index');
            $table->dropIndex('activity_log_center_id_old_index');
            $table->dropColumn(['center_id_attr', 'center_id_old']);
        });
    }
};
