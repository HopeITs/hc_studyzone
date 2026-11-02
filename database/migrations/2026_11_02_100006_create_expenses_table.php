<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // operations / maintenance / warehouse_purchase / transport / hospitality /
            // advance / other — see App\Enums\ExpenseType
            $table->string('type');
            $table->string('payee')->nullable();
            $table->integer('amount');
            $table->date('date');
            $table->text('note')->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $table->timestamps();

            $table->index(['center_id', 'date']);
            $table->index(['center_id', 'type']);
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
