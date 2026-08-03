<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The activity_log table (300k+ rows) is queried by the reports-audit filters
 * (subject period, registration mismatch) by extracting dates out of the JSON
 * `properties` column live, per row, on every request — that scan is what made
 * the Activity Log page freeze. These generated columns pre-parse the handful
 * of date fields we actually filter on (student_entries.entry_time and
 * student_educations.start_date/end_date, both the new and old values) at
 * write time, and are indexed, so filtering becomes a normal index lookup
 * instead of a full-table JSON scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name', 'activity_log');
        $grammarTable = DB::connection($connection)->getTablePrefix() . $table;

        // Stored dates look like "2026-07-29T08:15:04.000000Z" (Carbon's default JSON
        // serialization). ALTER TABLE evaluates generated columns in strict mode, so the
        // trailing "Z" must be matched literally or MySQL rejects every row as truncated.
        // JSON keys that are explicitly null (e.g. an open-ended education's end_date)
        // unquote to the literal string "null", not SQL NULL, which also blows up
        // STR_TO_DATE — NULLIF converts that back to a real NULL first.
        $dateExpr = fn (string $path) => "STR_TO_DATE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(properties, '\$.{$path}')), 'null'), '%Y-%m-%dT%H:%i:%s.%fZ')";

        DB::connection($connection)->statement(
            "ALTER TABLE `{$grammarTable}`
                ADD COLUMN `entry_time_attr` DATETIME GENERATED ALWAYS AS ({$dateExpr('attributes.entry_time')}) STORED,
                ADD COLUMN `entry_time_old` DATETIME GENERATED ALWAYS AS ({$dateExpr('old.entry_time')}) STORED,
                ADD COLUMN `education_start_attr` DATETIME GENERATED ALWAYS AS ({$dateExpr('attributes.start_date')}) STORED,
                ADD COLUMN `education_end_attr` DATETIME GENERATED ALWAYS AS ({$dateExpr('attributes.end_date')}) STORED,
                ADD COLUMN `education_start_old` DATETIME GENERATED ALWAYS AS ({$dateExpr('old.start_date')}) STORED,
                ADD COLUMN `education_end_old` DATETIME GENERATED ALWAYS AS ({$dateExpr('old.end_date')}) STORED"
        );

        Schema::connection($connection)->table($table, function ($table) {
            $table->index(['subject_type', 'event'], 'activity_log_subject_type_event_index');
            $table->index('entry_time_attr', 'activity_log_entry_time_attr_index');
            $table->index('entry_time_old', 'activity_log_entry_time_old_index');
            $table->index('education_start_attr', 'activity_log_education_start_attr_index');
            $table->index('education_end_attr', 'activity_log_education_end_attr_index');
            $table->index('education_start_old', 'activity_log_education_start_old_index');
            $table->index('education_end_old', 'activity_log_education_end_old_index');
        });
    }

    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name', 'activity_log');

        Schema::connection($connection)->table($table, function ($table) {
            $table->dropIndex('activity_log_subject_type_event_index');
            $table->dropIndex('activity_log_entry_time_attr_index');
            $table->dropIndex('activity_log_entry_time_old_index');
            $table->dropIndex('activity_log_education_start_attr_index');
            $table->dropIndex('activity_log_education_end_attr_index');
            $table->dropIndex('activity_log_education_start_old_index');
            $table->dropIndex('activity_log_education_end_old_index');
            $table->dropColumn([
                'entry_time_attr',
                'entry_time_old',
                'education_start_attr',
                'education_end_attr',
                'education_start_old',
                'education_end_old',
            ]);
        });
    }
};
