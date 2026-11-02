<?php

return [

    // Resource names
    'expenses' => 'Expenses',
    'expense' => 'Expense',
    'cash_shifts' => 'Cash Shifts',
    'cash_shift' => 'Cash Shift',

    // Expense types
    'expense_types' => [
        'operations' => 'Operations',
        'maintenance' => 'Maintenance',
        'warehouse_purchase' => 'Warehouse Purchase',
        'transport' => 'Transport',
        'hospitality' => 'Hospitality',
        'advance' => 'Advance',
        'other' => 'Other',
    ],

    // Shift status
    'shift_status' => [
        'open' => 'Open',
        'closed' => 'Closed',
    ],

    // Labels
    'labels' => [
        'type' => 'Type',
        'payee' => 'Payee',
        'amount' => 'Amount',
        'date' => 'Date',
        'note' => 'Note',
        'receipt' => 'Receipt',
        'has_receipt' => 'Has Receipt',
        'center' => 'Center',
        'city' => 'City',
        'user' => 'User',
        'shift' => 'Shift',
        'in_shift' => 'In Shift',
        'no_shift' => 'No Shift',
        'status' => 'Status',
        'opened_at' => 'Opened At',
        'closed_at' => 'Closed At',
        'opening_cash' => 'Opening Cash',
        'cash_income' => 'Cash Income',
        'expenses_total' => 'Total Expenses',
        'expected_cash' => 'Expected Cash',
        'counted_cash' => 'Counted Cash',
        'difference' => 'Difference',
        'closing_note' => 'Closing Note',
        'from' => 'From',
        'to' => 'To',
        'warehouse_items' => 'Warehouse Items',
        'warehouse_items_hint' => 'Items added here automatically create purchase stock movements linked to this expense.',
        'item' => 'Item',
        'quantity' => 'Quantity',
        'cost' => 'Cost',
        'filtered_total' => 'Total of Shown Expenses',
        'filtered_count' => 'Records: :count',
        'shift_details' => 'Shift Details',
        'live_values' => 'Live Values (Open Shift)',
        'snapshot_values' => 'Closing Snapshot',
    ],

    // Actions
    'actions' => [
        'open_shift' => 'Open Shift',
        'close_shift' => 'Close Shift',
        'add_item' => 'Add Item',
    ],

    // Notes written on auto-created records
    'notes' => [
        'warehouse_expense' => 'Warehouse purchase — :payee',
    ],

    // Messages / notifications
    'messages' => [
        'shift_opened' => 'Shift opened successfully',
        'shift_closed' => 'Shift closed successfully',
        'shift_already_closed' => 'This shift is already closed',
        'center_has_open_shift' => 'This center already has an open shift',
        'not_allowed' => 'You are not allowed to perform this action',
        'close_shift_confirm' => 'Cash income and expenses will be snapshotted and the difference calculated. Closing a shift cannot be undone.',
    ],

    // Validation
    'validation' => [
        'month_locked' => 'Cannot record in a locked month',
        'invalid_item' => 'Invalid item, or the item belongs to another center',
        'invalid_quantity' => 'Quantity must be at least 1',
        'invalid_cost' => 'Cost cannot be negative',
    ],

];
