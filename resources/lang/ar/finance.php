<?php

return [

    // Resource names
    'expenses' => 'المصاريف',
    'expense' => 'مصروف',
    'cash_shifts' => 'الصندوق والورديات',
    'cash_shift' => 'وردية صندوق',

    // Expense types
    'expense_types' => [
        'operations' => 'تشغيلية',
        'maintenance' => 'صيانة',
        'warehouse_purchase' => 'شراء مستودع',
        'transport' => 'نقل ومواصلات',
        'hospitality' => 'ضيافة',
        'advance' => 'سلفة',
        'other' => 'أخرى',
    ],

    // Shift status
    'shift_status' => [
        'open' => 'مفتوحة',
        'closed' => 'مغلقة',
    ],

    // Labels
    'labels' => [
        'type' => 'النوع',
        'payee' => 'المدفوع له / المورّد',
        'amount' => 'المبلغ',
        'date' => 'التاريخ',
        'note' => 'ملاحظة',
        'receipt' => 'الإيصال',
        'has_receipt' => 'إيصال مرفق',
        'center' => 'المركز',
        'city' => 'المدينة',
        'user' => 'المستخدم',
        'shift' => 'الوردية',
        'in_shift' => 'ضمن وردية',
        'no_shift' => 'بدون وردية',
        'status' => 'الحالة',
        'opened_at' => 'وقت الفتح',
        'closed_at' => 'وقت الإغلاق',
        'opening_cash' => 'رصيد الافتتاح',
        'cash_income' => 'المقبوضات النقدية',
        'expenses_total' => 'إجمالي المصاريف',
        'expected_cash' => 'النقد المتوقع',
        'counted_cash' => 'النقد المعدود',
        'difference' => 'الفرق',
        'closing_note' => 'ملاحظة الإغلاق',
        'from' => 'من',
        'to' => 'إلى',
        'warehouse_items' => 'مواد المستودع',
        'warehouse_items_hint' => 'عند إضافة مواد هنا تُسجَّل حركات شراء في المستودع مرتبطة بهذا المصروف تلقائيًا.',
        'item' => 'المادة',
        'quantity' => 'الكمية',
        'cost' => 'التكلفة',
        'filtered_total' => 'إجمالي المصاريف المعروضة',
        'filtered_count' => 'عدد السجلات: :count',
        'invoices_count' => 'عدد الفواتير',
        'shift_details' => 'تفاصيل الوردية',
        'live_values' => 'القيم الحالية (وردية مفتوحة)',
        'snapshot_values' => 'قيم الإغلاق',
    ],

    // Actions
    'actions' => [
        'open_shift' => 'فتح وردية',
        'close_shift' => 'إغلاق الوردية',
        'add_item' => 'إضافة مادة',
        'print_report' => 'طباعة تقرير الإغلاق',
    ],

    // Shift closing report (print view)
    'print' => [
        'title' => 'تقرير إغلاق وردية',
        'period' => 'الفترة',
        'center' => 'المركز',
        'user' => 'المستخدم',
        'printed_at' => 'تاريخ الطباعة',
    ],

    // Notes written on auto-created records
    'notes' => [
        'warehouse_expense' => 'شراء مستودع — :payee',
    ],

    // Messages / notifications
    'messages' => [
        'shift_opened' => 'تم فتح الوردية بنجاح',
        'shift_closed' => 'تم إغلاق الوردية بنجاح',
        'shift_already_closed' => 'هذه الوردية مغلقة بالفعل',
        'center_has_open_shift' => 'يوجد وردية مفتوحة بالفعل لهذا المركز',
        'not_allowed' => 'ليست لديك صلاحية لتنفيذ هذا الإجراء',
        'close_shift_confirm' => 'سيتم تثبيت المقبوضات والمصاريف وحساب الفرق. لا يمكن التراجع عن إغلاق الوردية.',
    ],

    // Validation
    'validation' => [
        'month_locked' => 'لا يمكن التسجيل في شهر مقفل',
        'invalid_item' => 'مادة غير صالحة أو لا تتبع هذا المركز',
        'invalid_quantity' => 'الكمية يجب أن تكون 1 على الأقل',
        'invalid_cost' => 'التكلفة لا يمكن أن تكون سالبة',
        'no_open_shift' => 'لا توجد وردية مفتوحة لهذا المركز — افتح وردية أولاً من صفحة الصندوق والورديات.',
        'note_required_on_difference' => 'الفرق لا يساوي صفراً — يجب كتابة ملاحظة توضيحية.',
    ],

];
