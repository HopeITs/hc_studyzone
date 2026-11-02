<?php

return [
    'item' => 'Item',
    'items' => 'Buffet Inventory',

    'fields' => [
        'name' => 'Item Name',
        'category' => 'Category',
        'unit' => 'Unit',
        'purchase_cost' => 'Purchase Cost',
        'sale_price' => 'Sale Price',
        'min_stock_threshold' => 'Minimum Stock Threshold',
        'supplier' => 'Supplier',
        'last_purchase_date' => 'Last Purchase Date',
        'is_active' => 'Active',
        'center' => 'Center',
        'current_stock' => 'Current Stock',
        'created_at' => 'Date',
        'type' => 'Movement Type',
        'quantity' => 'Quantity',
        'counted_quantity' => 'Actual Counted Quantity',
        'total_cost' => 'Total Cost',
        'cost' => 'Cost',
        'user' => 'User',
        'invoice' => 'Invoice',
        'note' => 'Note',
    ],

    'units' => [
        'piece' => 'Piece',
        'box' => 'Box',
        'kg' => 'Kilogram',
        'ream' => 'Ream',
        'meter' => 'Meter',
    ],

    'movement_types' => [
        'purchase' => 'Purchase',
        'sale' => 'Sale',
        'adjustment' => 'Adjustment',
        'team_allocation' => 'Team Allocation',
    ],

    'movements' => [
        'title' => 'Stock Movements',
    ],

    'actions' => [
        'add_purchase' => 'Add Purchase',
        'adjustment' => 'Stock Count Adjustment',
        'export' => 'Export to Excel',
    ],

    'filters' => [
        'all' => 'All',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'category' => 'Category',
        'unit' => 'Unit',
        'stock_status' => 'Stock Status',
        'below_threshold' => 'Below threshold',
        'in_stock' => 'In stock',
        'out_of_stock' => 'Out of stock',
    ],

    'helpers' => [
        'old_currency' => 'In new SYP — old currency value = value × 100',
        'counted_quantity' => 'Enter the actual quantity counted during the stock take; the system records an adjustment movement for the difference automatically',
    ],

    'messages' => [
        'purchase_recorded' => 'Purchase recorded successfully',
        'adjustment_recorded' => 'Adjustment recorded successfully',
        'adjustment_no_difference' => 'No difference between the entered quantity and the current stock — no movement was recorded',
    ],
];
