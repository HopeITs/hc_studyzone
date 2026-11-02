<?php

return [

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
    ],

    'notes' => [
        'team_allocation' => 'Team allocation — recipient: :recipient',
    ],
];
