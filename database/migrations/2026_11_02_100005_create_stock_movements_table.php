<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            // purchase / sale / adjustment / team_allocation — see App\Enums\StockMovementType
            $table->string('type');
            // Signed: purchases positive, sales/team allocations negative.
            // Current stock = SUM(quantity) per item.
            $table->integer('quantity');
            $table->integer('cost')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'type']);
            $table->index(['center_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
