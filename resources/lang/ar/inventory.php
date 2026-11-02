<?php

return [
    'item' => 'مادة',
    'items' => 'مخزون البوفيه',

    'fields' => [
        'name' => 'اسم المادة',
        'category' => 'التصنيف',
        'unit' => 'الوحدة',
        'purchase_cost' => 'كلفة الشراء',
        'sale_price' => 'سعر المبيع',
        'min_stock_threshold' => 'حد المخزون الأدنى',
        'supplier' => 'المورّد',
        'last_purchase_date' => 'تاريخ آخر شراء',
        'is_active' => 'فعّال',
        'center' => 'المركز',
        'current_stock' => 'المخزون الحالي',
        'created_at' => 'التاريخ',
        'type' => 'نوع الحركة',
        'quantity' => 'الكمية',
        'signed_quantity' => 'الكمية (+/-)',
        'total_cost' => 'الكلفة الإجمالية',
        'cost' => 'الكلفة',
        'user' => 'المستخدم',
        'invoice' => 'فاتورة',
        'note' => 'ملاحظة',
    ],

    'units' => [
        'piece' => 'قطعة',
        'box' => 'علبة',
        'kg' => 'كيلوغرام',
        'ream' => 'رزمة',
        'meter' => 'متر',
    ],

    'movement_types' => [
        'purchase' => 'شراء',
        'sale' => 'بيع',
        'adjustment' => 'تسوية',
        'team_allocation' => 'مخصصات الفريق',
    ],

    'movements' => [
        'title' => 'حركات المخزون',
    ],

    'actions' => [
        'add_purchase' => 'إضافة شراء',
        'adjustment' => 'تسوية',
    ],

    'filters' => [
        'all' => 'الكل',
        'active' => 'فعّال',
        'inactive' => 'غير فعّال',
        'low_stock' => 'المخزون المنخفض',
        'low_only' => 'منخفض فقط',
        'ok_only' => 'فوق الحد فقط',
    ],

    'helpers' => [
        'old_currency' => 'بالليرة الجديدة — القيمة بالليرة القديمة = القيمة × 100',
        'signed_quantity' => 'قيمة موجبة لزيادة المخزون، سالبة لإنقاصه (لا يمكن أن تكون صفراً)',
    ],

    'messages' => [
        'purchase_recorded' => 'تم تسجيل الشراء بنجاح',
        'adjustment_recorded' => 'تم تسجيل التسوية بنجاح',
    ],
];
