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
        'student_search_placeholder' => 'Search by name, surname, or phone…',
        'beneficiary_type' => 'Customer',
        'student' => 'Registered student',
        'walk_in' => 'Walk-in customer',
        'optional_beneficiary_name' => 'Beneficiary name (optional)',
        'additional_details' => 'Additional details',
    ],

    'discount_types' => [
        'amount' => 'Amount',
        'percent' => 'Percent',
    ],

    'summary' => [
        'subtotal' => 'Subtotal before discount',
    ],

    'actions' => [
        'register_visit' => 'Register visit and add services',
        'refresh_visit' => 'Check again',
        'collect_and_issue' => 'Collect & Issue',
        'save_unpaid' => 'Save Unpaid',
        'clear' => 'Clear',
        'unpaid_invoices' => 'Unpaid Invoices',
        'team_allocation' => 'Team Allocations',
        'clear_student' => 'Unlink student',
    ],

    'messages' => [
        'student_has_no_visit' => 'This student has no visit today at this center.',
        'student_has_visit' => 'A visit is registered today at this center.',
        'register_visit_hint' => 'The visit form opens in a new tab. After saving, return here and choose “Check again”.',
        'visit_registration_unavailable' => 'Check that the student profile is complete and you have permission to register a visit.',
        'select_center' => 'Select a center first',
        'cart_empty' => 'The invoice is empty — add at least one line',
        'beneficiary_required' => 'The beneficiary name is required',
        'collected' => 'Invoice #:number collected and issued',
        'saved_unpaid' => 'Invoice #:number saved as unpaid',
        'allocation_recorded' => 'Team allocation recorded and deducted from stock',
        'failed' => 'The operation could not be completed',
        'no_students_found' => 'No matching student. Try the first name and surname, or search by phone.',
        'select_student' => 'Select a student from the search results.',
        'refine_student_search' => 'Add a surname to narrow the results.',
    ],

    'unpaid' => [
        'empty' => 'No unpaid invoices',
    ],

    'errors' => [
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
