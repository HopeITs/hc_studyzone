<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A price row without a center is the default price of its admission
 * category for every center; a row with a center overrides it there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_prices', function (Blueprint $table): void {
            $table->unsignedBigInteger('center_id')->nullable()->change();
            $table->index(['entry_type', 'starts_on']);
        });
    }

    public function down(): void
    {
        DB::table('admission_prices')->whereNull('center_id')->delete();

        Schema::table('admission_prices', function (Blueprint $table): void {
            $table->dropIndex(['entry_type', 'starts_on']);
            $table->unsignedBigInteger('center_id')->nullable(false)->change();
        });
    }
};
