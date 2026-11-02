<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            // A line sells exactly one of the two: an inventory item OR a service
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            // Price snapshot at sale time — the catalog price can change later
            $table->integer('unit_price')->default(0);
            $table->integer('line_total')->default(0);
            $table->timestamps();

            $table->index(['invoice_id']);
        });

        // Exactly one of the two FKs must be set (enforced on MySQL 8.0.16+ / MariaDB 10.2+,
        // parsed-and-ignored on older MySQL — the model guards it as well).
        // Raw statement, so the table prefix must be added by hand.
        $table = DB::getTablePrefix().'invoice_items';
        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT invoice_items_one_target_check
             CHECK ((inventory_item_id IS NULL) <> (service_id IS NULL))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
