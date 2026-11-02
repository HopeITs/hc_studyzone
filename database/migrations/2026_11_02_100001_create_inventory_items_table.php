<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            // piece / box / kg / ream / meter — see App\Enums\InventoryUnit
            $table->string('unit')->default('piece');
            // Amounts in the new currency (integer, no decimals) — same as service_prices.price
            $table->integer('purchase_cost')->default(0);
            $table->integer('sale_price')->default(0);
            $table->integer('min_stock_threshold')->default(0);
            $table->string('supplier')->nullable();
            $table->date('last_purchase_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['center_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
