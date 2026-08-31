@props([
    'total' => 0,
    'count' => 0,
])

<div class="p-5 mt-4 border-2 rounded-xl">

    <div class="flex items-center justify-between p-3">
        <!-- Services Count -->
        <div class="flex items-center gap-3 ">
            <div class="flex items-center justify-center w-10 h-10 bg-blue-100 rounded-lg dark:bg-blue-900/30">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">عدد الخدمات المختارة</div>
                <div class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    {{ $count }} {{ $count == 1 ? 'خدمة' : 'خدمات' }}
                </div>
            </div>
        </div>

        <!-- Total Amount -->
        <div class="text-left donor-hide">
            <div class="text-xs text-gray-500 dark:text-gray-400">الإجمالي الكلي</div>
            <div class="flex justify-between gap-x-3">
                <div class="text-2xl font-bold ">
                   {{ number_format($total * 100) }}
                    <span class="mr-1 text-base font-medium text-gray-500 dark:text-gray-400">ل.س.ق</span>

                </div>

                <div class="text-2xl font-bold border-s ps-3">
                    {{ number_format($total) }}
                    <span class="mr-1 text-base font-medium text-gray-500 dark:text-gray-400">ل.س</span>
                </div>
            </div>
        </div>
    </div>
</div>
