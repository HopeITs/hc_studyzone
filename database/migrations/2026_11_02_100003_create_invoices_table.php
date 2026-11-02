<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Sequential per center, assigned by Invoice::nextNumberFor()
            $table->unsignedInteger('number');
            // paid / unpaid / cancelled — see App\Enums\InvoiceStatus
            $table->string('status')->default('unpaid');
            $table->string('beneficiary_name');
            $table->string('student_number')->nullable();
            $table->integer('discount')->default(0);
            $table->integer('total')->default(0);
            // cash only for now — see App\Enums\PaymentMethod
            $table->string('payment_method')->default('cash');
            $table->text('note')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['center_id', 'number']);
            $table->index(['center_id', 'status']);
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
