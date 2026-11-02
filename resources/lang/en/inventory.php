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
        'signed_quantity' => 'Quantity (+/-)',
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
        'adjustment' => 'Adjustment',
    ],

    'filters' => [
        'all' => 'All',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'low_stock' => 'Low Stock',
        'low_only' => 'Low only',
        'ok_only' => 'Above threshold only',
    ],

    'helpers' => [
        'old_currency' => 'In new SYP — old currency value = value × 100',
        'signed_quantity' => 'Positive to increase stock, negative to decrease it (cannot be zero)',
    ],

    'messages' => [
        'purchase_recorded' => 'Purchase recorded successfully',
        'adjustment_recorded' => 'Adjustment recorded successfully',
    ],
];
