<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->integer('opening_cash')->default(0);
            // Snapshots stored when the shift is closed; live values come from accessors
            $table->integer('cash_income')->nullable();
            $table->integer('expenses_total')->nullable();
            $table->integer('counted_cash')->nullable();
            $table->integer('difference')->nullable();
            $table->text('closing_note')->nullable();
            // open / closed — see App\Enums\CashShiftStatus
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index(['center_id', 'status']);
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_shifts');
    }
};
