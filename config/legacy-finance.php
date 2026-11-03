<?php

use App\Enums\ExpenseType;

/*
 * Mapping between the legacy DBRestaurantProV5 SQL Server schema (one
 * attached database per center) and the new financial tables.
 *
 * IMPORTANT: the table/column names below are a first guess. Run
 *   php artisan finance:legacy-schema <connection>
 * against the attached legacy database first, then correct this map to the
 * real names before importing. finance:import-legacy refuses to run when a
 * mapped table or column does not exist, so a wrong name fails loudly,
 * never silently.
 */
return [

    /*
     * Currency: the app stores the NEW currency (old = new x 100, see
     * App\Support\FinanceDisplay). The legacy desktop app predates the
     * zero-removal, so by default EVERY legacy amount is divided by 100.
     * If a legacy file contains post-conversion rows, set the cutoff date
     * (or pass --cutoff=YYYY-MM-DD): only rows dated BEFORE it are divided.
     */
    'currency_divisor' => 100,
    'currency_cutoff' => env('LEGACY_CURRENCY_CUTOFF'), // null => convert everything

    'map' => [

        'items' => [
            'table' => 'Items',
            'id' => 'ItemID',
            'name' => 'ItemName',
            'category' => 'CategoryName',   // null when the legacy table has none
            'purchase_cost' => 'BuyPrice',
            'sale_price' => 'SalePrice',
            'stock' => 'Quantity',          // current stock snapshot -> opening movement
        ],

        'invoices' => [
            'table' => 'Bills',
            'id' => 'BillID',
            'date' => 'BillDate',
            'beneficiary' => 'CustomerName', // null => imported as "-"
            'total' => 'Total',
            'discount' => 'Discount',        // null when the legacy table has none
            'note' => 'Notes',               // null when the legacy table has none
        ],

        'invoice_lines' => [
            'table' => 'BillItems',
            'bill_id' => 'BillID',
            'item_id' => 'ItemID',
            'quantity' => 'Qty',
            'unit_price' => 'Price',
        ],

        'expenses' => [
            'table' => 'Expenses',
            'id' => 'ExpenseID',
            'date' => 'ExpDate',
            'type' => 'ExpType',             // legacy label, mapped below
            'payee' => 'Payee',              // null when the legacy table has none
            'amount' => 'Amount',
            'note' => 'Notes',               // null when the legacy table has none
        ],
    ],

    /*
     * Legacy expense-type label => App\Enums\ExpenseType. Unmapped labels
     * fall back to Other and keep the original label in the note.
     */
    'expense_types' => [
        'تشغيلية' => ExpenseType::Operations,
        'صيانة' => ExpenseType::Maintenance,
        'شراء مستودع' => ExpenseType::WarehousePurchase,
        'نقل' => ExpenseType::Transport,
        'ضيافة' => ExpenseType::Hospitality,
        'سلفة' => ExpenseType::Advance,
    ],
];
