<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Purchase movements created from a warehouse-purchase expense.
            // Deleting the expense removes its movements, so stock stays
            // consistent with the money actually spent.
            $table->foreignId('expense_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('expenses')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });
    }
};
