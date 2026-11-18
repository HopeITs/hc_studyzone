<?php

return [

    'title' => 'Point of Sale',

    'catalog' => [
        'all' => 'All',
        'search_placeholder' => 'Search for an item or service…',
        'empty' => 'No matching items or services',
        'stock' => 'Stock',
    ],

    'cart' => [
        'title' => 'Current Invoice',
        'empty' => 'No lines added yet — pick from the catalog',
        'remove' => 'Remove line',
    ],

    'fields' => [
        'center' => 'Center',
        'discount_type' => 'Discount Type',
        'recipient' => 'Recipient Name',
        'items' => 'Items',
        'student_search' => 'Find a Student',
        'student_search_placeholder' => 'Type the student name or phone…',
    ],

    'discount_types' => [
        'amount' => 'Amount',
        'percent' => 'Percent',
    ],

    'summary' => [
        'subtotal' => 'Subtotal before discount',
    ],

    'actions' => [
        'collect_and_issue' => 'Collect & Issue',
        'save_unpaid' => 'Save Unpaid',
        'clear' => 'Clear',
        'unpaid_invoices' => 'Unpaid Invoices',
        'team_allocation' => 'Team Allocations',
        'clear_student' => 'Unlink student',
    ],

    'messages' => [
        'select_center' => 'Select a center first',
        'cart_empty' => 'The invoice is empty — add at least one line',
        'beneficiary_required' => 'The beneficiary name is required',
        'collected' => 'Invoice #:number collected and issued',
        'saved_unpaid' => 'Invoice #:number saved as unpaid',
        'allocation_recorded' => 'Team allocation recorded and deducted from stock',
        'failed' => 'The operation could not be completed',
        'no_students_found' => 'No matching student — type the name manually below',
    ],

    'unpaid' => [
        'empty' => 'No unpaid invoices',
    ],

    'entry' => [
        'drinks' => 'Purchased drinks',
        'has_entry_today' => 'Has an entry today — services are added to it automatically',
        'no_entry_today' => 'No entry today',
        'confirm_heading' => 'Warning: This transaction is not linked to a visit',
        'confirm_no_entry' => 'There is no visit for this customer today at this center. Continue without linking this transaction to a visit?',
        'confirm_submit' => 'Continue without an entry',
    ],

    'errors' => [
        'daily_service_quantity' => 'A daily service is recorded once with a quantity of one.',
        'entry_has_sales' => 'This entry has paid sales. Cancel the linked invoices before deleting it or changing its date or center.',
        'already_on_entry' => '":service" is already on the student\'s entry today — nothing was done.',
        'entry_service_from_pos' => 'This service was paid at the point of sale — cancel its invoice there to change it.',
        'month_locked' => 'This month is locked for accounting; no sales or hand-outs are allowed in it.',
        'empty_lines' => 'The operation cannot be completed without any lines.',
        'invalid_line' => 'One of the lines is invalid — each line must reference exactly one item or service.',
        'invalid_quantity' => 'The quantity must be at least 1 on every line.',
        'beneficiary_required' => 'The beneficiary name is required.',
        'recipient_required' => 'The recipient name is required.',
        'item_unavailable' => 'One of the items is not available in this center or is inactive.',
        'no_price' => 'The service ":service" has no active price in this center.',
        'insufficient_stock' => 'Requested quantity of ":item" is not available in stock (available: :available).',
        'student_not_found' => 'The selected student does not exist.',
        'center_mismatch' => 'You may only perform sales operations for your own center.',
    ],

    'notes' => [
        'team_allocation' => 'Team allocation — recipient: :recipient',
    ],
];
