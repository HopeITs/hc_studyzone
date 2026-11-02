<?php

return [

    // Resource names
    'expenses' => 'Expenses',
    'expense' => 'Expense',
    'cash_shifts' => 'Cash & Shifts',
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
        'payee' => 'Payee / Supplier',
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
        'invoices_count' => 'Invoices Count',
        'shift_details' => 'Shift Details',
        'live_values' => 'Live Values (Open Shift)',
        'snapshot_values' => 'Closing Snapshot',
    ],

    // Actions
    'actions' => [
        'open_shift' => 'Open Shift',
        'close_shift' => 'Close Shift',
        'add_item' => 'Add Item',
        'print_report' => 'Print Closing Report',
        'view_receipt' => 'View Receipt',
    ],

    // Shift closing report (print view)
    'print' => [
        'title' => 'Shift Closing Report',
        'period' => 'Period',
        'center' => 'Center',
        'user' => 'User',
        'printed_at' => 'Printed At',
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

    // Dashboard widgets
    'widgets' => [
        'today_sales' => "Today's Sales",
        'today_expenses' => "Today's Expenses",
        'today_net' => "Today's Net",
        'today_invoices' => "Today's Invoices",
        'low_stock_items' => 'Items Below Min Stock',
        'daily_revenue' => 'Daily Revenue',
        'revenue' => 'Revenue',
        'sales_by_category' => 'Sales by Category',
        'sales' => 'Sales',
        'last_7_days' => 'Last 7 Days',
        'last_30_days' => 'Last 30 Days',
        'dashboard_filters' => 'By Dashboard Filters',
    ],

    // Validation
    'validation' => [
        'month_locked' => 'Cannot record in a locked month',
        'invalid_item' => 'Invalid item, or the item belongs to another center',
        'invalid_quantity' => 'Quantity must be at least 1',
        'invalid_cost' => 'Cost cannot be negative',
        'no_open_shift' => 'This center has no open shift — open one first from the Cash & Shifts page.',
        'note_required_on_difference' => 'The difference is not zero — an explanatory note is required.',
    ],

];
