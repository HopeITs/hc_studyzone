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
        'counted_quantity' => 'الكمية الفعلية بعد الجرد',
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
        'adjustment' => 'تسوية جرد',
        'export' => 'تصدير إلى Excel',
    ],

    'filters' => [
        'all' => 'الكل',
        'active' => 'فعّال',
        'inactive' => 'غير فعّال',
        'category' => 'التصنيف',
        'unit' => 'الوحدة',
        'stock_status' => 'حالة المخزون',
        'below_threshold' => 'تحت الحد الأدنى',
        'in_stock' => 'متوفر',
        'out_of_stock' => 'نفذ من المخزون',
    ],

    'helpers' => [
        'old_currency' => 'بالليرة الجديدة — القيمة بالليرة القديمة = القيمة × 100',
        'counted_quantity' => 'أدخل الكمية الفعلية التي تم عدّها أثناء الجرد، وسيسجّل النظام حركة تسوية بالفرق تلقائياً',
    ],

    'messages' => [
        'purchase_recorded' => 'تم تسجيل الشراء بنجاح',
        'adjustment_recorded' => 'تم تسجيل التسوية بنجاح',
        'adjustment_no_difference' => 'لا يوجد فرق بين الكمية المدخلة والمخزون الحالي — لم تُسجّل أي حركة',
    ],
];
