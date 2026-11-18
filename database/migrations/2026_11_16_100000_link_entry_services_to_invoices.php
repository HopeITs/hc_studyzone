<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Set only on the invoice generated from an entry's services
            // ("entry invoice"). Point-of-sale invoices keep it null and are
            // linked to entries line by line through entry_services.invoice_item_id.
            $table->foreignUuid('entry_id')
                ->nullable()
                ->after('student_id')
                ->constrained('student_entries')
                ->nullOnDelete();
        });

        Schema::table('entry_services', function (Blueprint $table) {
            // The invoice line that charged this service (entry invoice or
            // point-of-sale invoice). Null = not billed yet.
            $table->foreignId('invoice_item_id')
                ->nullable()
                ->after('service_id')
                ->unique()
                ->constrained('invoice_items')
                ->nullOnDelete();

            // Services recorded before entries were billed through invoices.
            // They stay as history and are never turned into invoices, so
            // money already counted elsewhere is not counted twice.
            $table->boolean('pre_finance')->default(false)->after('invoice_item_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignUuid('visit_id')->nullable()->constrained('student_entries')->nullOnDelete();
        });

        DB::table('entry_services')->update(['pre_finance' => true]);
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visit_id');
        });

        Schema::table('entry_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_item_id');
            $table->dropColumn('pre_finance');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entry_id');
        });
    }
};
