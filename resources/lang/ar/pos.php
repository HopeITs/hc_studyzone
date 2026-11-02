<?php

return [

    'title' => 'نقطة البيع',

    'catalog' => [
        'all' => 'الكل',
        'search_placeholder' => 'ابحث عن مادة أو خدمة…',
        'empty' => 'لا توجد مواد أو خدمات مطابقة',
        'stock' => 'المخزون',
    ],

    'cart' => [
        'title' => 'الفاتورة الحالية',
        'empty' => 'لم تتم إضافة أي بند بعد — اختر من القائمة',
        'remove' => 'حذف البند',
    ],

    'fields' => [
        'center' => 'المركز',
        'discount_type' => 'نوع الحسم',
        'recipient' => 'اسم المستلم',
        'items' => 'المواد',
        'student_search' => 'بحث عن طالب',
        'student_search_placeholder' => 'اكتب اسم الطالب أو رقم هاتفه…',
    ],

    'discount_types' => [
        'amount' => 'مبلغ',
        'percent' => 'نسبة مئوية',
    ],

    'summary' => [
        'subtotal' => 'المجموع قبل الحسم',
    ],

    'actions' => [
        'collect_and_issue' => 'تحصيل وإصدار',
        'save_unpaid' => 'حفظ غير محصلة',
        'clear' => 'تفريغ',
        'unpaid_invoices' => 'الفواتير غير المحصلة',
        'team_allocation' => 'مخصصات الفريق',
        'clear_student' => 'إلغاء ربط الطالب',
    ],

    'messages' => [
        'select_center' => 'اختر المركز أولاً',
        'cart_empty' => 'الفاتورة فارغة — أضف بنداً واحداً على الأقل',
        'beneficiary_required' => 'اسم المستفيد مطلوب',
        'collected' => 'تم تحصيل الفاتورة رقم :number وإصدارها',
        'saved_unpaid' => 'تم حفظ الفاتورة رقم :number غير محصلة',
        'allocation_recorded' => 'تم تسجيل مخصصات الفريق وخصمها من المخزون',
        'failed' => 'تعذر إتمام العملية',
        'no_students_found' => 'لا يوجد طالب مطابق — اكتب الاسم يدويًا بالأسفل',
    ],

    'unpaid' => [
        'empty' => 'لا توجد فواتير غير محصلة',
    ],

    'errors' => [
        'month_locked' => 'الشهر مقفل محاسبياً، لا يمكن تنفيذ عمليات بيع أو تسليم فيه.',
        'empty_lines' => 'لا يمكن إتمام العملية بدون أي بند.',
        'invalid_line' => 'أحد البنود غير صالح — يجب أن يشير كل بند إلى مادة أو خدمة واحدة فقط.',
        'invalid_quantity' => 'الكمية يجب أن تكون 1 على الأقل في كل بند.',
        'beneficiary_required' => 'اسم المستفيد مطلوب.',
        'recipient_required' => 'اسم المستلم مطلوب.',
        'item_unavailable' => 'إحدى المواد غير متاحة في هذا المركز أو غير فعّالة.',
        'no_price' => 'لا يوجد سعر فعّال للخدمة ":service" في هذا المركز.',
        'insufficient_stock' => 'الكمية المطلوبة من ":item" غير متوفرة في المخزون (المتوفر: :available).',
        'student_not_found' => 'الطالب المحدد غير موجود.',
    ],

    'notes' => [
        'team_allocation' => 'مخصصات فريق — المستلم: :recipient',
    ],
];
