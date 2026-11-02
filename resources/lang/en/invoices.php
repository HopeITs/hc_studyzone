<?php

return [

    'labels' => [
        'invoice' => 'Invoice',
        'invoices' => 'Invoices',
        'old_currency_hint' => 'Old currency: :amount SYP',
        'in_stock' => 'in stock',
    ],

    'sections' => [
        'details' => 'Invoice Details',
        'items' => 'Invoice Lines',
    ],

    'fields' => [
        'number' => 'Invoice Number',
        'center' => 'Center',
        'beneficiary_name' => 'Beneficiary Name',
        'student' => 'Student (optional)',
        'student_number' => 'Student Number',
        'payment_method' => 'Payment Method',
        'discount' => 'Discount',
        'note' => 'Note',
        'items' => 'Lines',
        'item' => 'Item',
        'kind' => 'Line Type',
        'inventory_item' => 'Buffet Item',
        'service' => 'Service',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit Price',
        'line_total' => 'Line Total',
        'total' => 'Total',
        'status' => 'Status',
        'items_count' => 'Lines Count',
        'user' => 'User',
        'created_at' => 'Created At',
        'date_from' => 'From Date',
        'date_to' => 'To Date',
    ],

    'kinds' => [
        'inventory' => 'Buffet Item',
        'service' => 'Service',
    ],

    'status' => [
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        'cancelled' => 'Cancelled',
    ],

    'payment_methods' => [
        'cash' => 'Cash',
    ],

    'actions' => [
        'create' => 'New Invoice',
        'collect' => 'Collect',
        'cancel' => 'Cancel',
        'view' => 'View',
        'edit' => 'Edit',
        'add_line' => 'Add Line',
    ],

    'messages' => [
        'collect_confirm' => 'The invoice will be collected and items deducted from stock. Are you sure?',
        'cancel_confirm' => 'The invoice will be cancelled, and items returned to stock if it was paid. Are you sure?',
        'collected' => 'Invoice collected successfully',
        'collect_failed' => 'Could not collect the invoice',
        'cancelled' => 'Invoice cancelled',
        'cancel_failed' => 'Could not cancel the invoice',
        'create_failed' => 'Could not create the invoice',
        'update_failed' => 'Could not update the invoice',
    ],

    'errors' => [
        'month_locked' => 'This month is locked for accounting; the operation is not allowed.',
        'not_unpaid' => 'This operation is only allowed on an unpaid invoice.',
        'already_cancelled' => 'The invoice is already cancelled.',
        'insufficient_stock' => 'Requested quantity of ":item" is not available in stock (available: :available).',
        'no_lines' => 'At least one line is required.',
        'invalid_line' => 'One of the lines is invalid; pick an item or a service and a valid quantity.',
        'item_not_in_center' => 'The selected item is not available or inactive in this center.',
        'no_active_price' => 'This service has no active price in this center.',
        'no_open_shift' => 'This center has no open shift — open one first from the Cash & Shifts page.',
    ],

    'notes' => [
        'sale' => 'Sale - invoice #:number',
        'cancellation' => 'Cancellation of invoice #:number - returned to stock',
    ],

];
