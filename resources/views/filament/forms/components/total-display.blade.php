@php
    $total = $getState();
@endphp

@if($total !== null && $total > 0)
    <div class="mt-4 p-4 bg-success-50 dark:bg-success-900/20 rounded-lg border border-success-200 dark:border-success-800">
        <div class="text-center">
            <div class="text-sm text-success-600 dark:text-success-400 mb-1">الإجمالي</div>
            <div class="text-2xl font-bold text-success-700 dark:text-success-300">
                {{ number_format($total, 2) }} SYP
            </div>
        </div>
    </div>
@else
    <div class="text-gray-500 text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        قم بتحديد الخدمات واختيار إغلاق الفاتورة
    </div>
@endif