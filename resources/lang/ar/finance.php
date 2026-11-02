<?php

return [

    // Resource names
    'expenses' => 'المصاريف',
    'expense' => 'مصروف',
    'cash_shifts' => 'ورديات الصندوق',
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
        'payee' => 'المستفيد',
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
        'shift_details' => 'تفاصيل الوردية',
        'live_values' => 'القيم الحالية (وردية مفتوحة)',
        'snapshot_values' => 'قيم الإغلاق',
    ],

    // Actions
    'actions' => [
        'open_shift' => 'فتح وردية',
        'close_shift' => 'إغلاق الوردية',
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
    ],

];
