<div class="max-w-4xl p-8 mx-auto mt-4 bg-white rounded-lg shadow-lg bill-container" dir="rtl">

    <!-- Header -->
    <div class="flex items-start justify-between pb-6 mb-8 border-b-2 border-gray-800">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">{{ $entry->center->name ?? 'المركز' }}</h2>
            <p class="mt-1 text-gray-500">فاتورة خدمات طلابية</p>
        </div>
        <div class="text-left">
            <p class="text-sm text-gray-500">رقم الفاتورة</p>
            <p class="text-2xl font-bold text-gray-800">#{{ $entry->id }}</p>
            <p class="mt-2 text-sm text-gray-500">{{ $entry->entry_time->format('Y-m-d H:i') }}</p>
        </div>
    </div>

    <!-- Student & Entry Info -->
    <div class="grid grid-cols-2 gap-8 p-6 mb-8 border border-gray-200 rounded-lg bg-gray-50">
        <div>
            <h3 class="mb-2 text-sm font-semibold text-gray-500">معلومات الطالب</h3>
            <p class="text-lg font-bold text-gray-900">{{ $record->name }}</p>
            {{-- Add other student details like phone or ID if needed --}}
        </div>
        <div class="text-left">
            <h3 class="mb-2 text-sm font-semibold text-gray-500">تفاصيل الدخولية</h3>
            <p class="text-gray-800">النوع: <span class="font-semibold">{{ __('general.entry-types.' . $entry->entry_type) }}</span></p>
            <p class="text-gray-800">المسجل: <span class="font-semibold">{{ $entry->user->name ?? 'نظام' }}</span></p>
        </div>
    </div>

    <!-- Services Table -->
    <table class="w-full mb-8 text-right border-collapse">
        <thead>
            <tr class="text-white bg-gray-800">
                <th class="px-4 py-3 font-semibold rounded-tr-lg">#</th>
                <th class="px-4 py-3 font-semibold">الخدمة</th>
                <th class="px-4 py-3 font-semibold text-center">الكمية</th>
                <th class="px-4 py-3 font-semibold">سعر الوحدة</th>
                <th class="px-4 py-3 font-semibold rounded-tl-lg">المجموع</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entry->services as $index => $service)
            <tr class="border-b border-gray-200 hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-600">{{ $index + 1 }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $service->service->name ?? 'خدمة محذوفة' }}</td>
                <td class="px-4 py-3 text-center text-gray-700">{{ $service->amount }}</td>
                <td class="px-4 py-3 text-gray-700">{{ number_format($service->price_at_time) }} ل.س</td>
                <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format($service->total) }} ل.س</td>
            </tr>
            @endforeach

            @if($entry->services->isEmpty())
            <tr>
                <td colspan="5" class="py-6 text-center text-gray-500">لا توجد خدمات مسجلة في هذه الدخولية</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- Grand Total -->
    <div class="flex justify-start pt-6 border-t-4 border-gray-800 border-double">
        <div class="w-1/3 p-5 text-white bg-gray-800 rounded-lg shadow-md">
            <div class="flex items-center justify-between">
                <span class="text-xl font-bold">الإجمالي</span>
                <span class="text-2xl font-extrabold">{{ number_format($entry->services->sum('total')) }} ل.س</span>
            </div>
        </div>
    </div>

    <!-- Actions (Hidden on Print) -->
    <div class="flex justify-center gap-4 mt-10 no-print">
        <button onclick="window.print()" class="flex items-center gap-2 px-8 py-3 font-semibold text-white transition bg-blue-600 rounded-lg shadow hover:bg-blue-700">
            <x-heroicon-o-printer class="w-5 h-5" /> طباعة الفاتورة
        </button>
        <a href="{{ \App\Filament\Resources\StudentResource::getUrl('view', ['record' => $record]) }}" class="flex items-center gap-2 px-8 py-3 font-semibold text-gray-800 transition bg-gray-200 rounded-lg shadow hover:bg-gray-300">
            <x-heroicon-o-arrow-left class="w-5 h-5" /> العودة للطالب
        </a>
    </div>
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .bill-container, .bill-container * {
            visibility: visible;
        }
        .bill-container {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            box-shadow: none;
            margin: 0;
            padding: 20px;
        }
        .no-print {
            display: none !important;
        }
        .fi-layout > div:not(.bill-container) {
            display: none !important; /* Hide sidebar during print if needed */
        }
    }
</style>
