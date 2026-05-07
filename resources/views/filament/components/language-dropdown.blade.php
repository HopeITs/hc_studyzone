<!-- resources/views/filament/components/language-dropdown.blade.php -->
@php
    $currentLocale = app()->getLocale();
    $flag = $currentLocale === 'ar' ? '🇸🇦' : '🇬🇧';
    $label = $currentLocale === 'ar' ? 'العربية' : 'English';
@endphp

<div x-data="{ open: false }" class="relative">
    <button
        @click="open = !open"
        @click.outside="open = false"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 transition-colors"
    >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802" />
        </svg>
        <span>{{ $label }}</span>
        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 z-50 mt-2 origin-top-right bg-white rounded-md shadow-lg w-44 ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
    >
        <div class="py-1">
            <a
                href="{{ request()->fullUrlWithQuery(['locale' => 'en']) }}"
                class="flex items-center gap-2 px-4 py-2 text-sm transition-colors
                    {{ $currentLocale === 'en'
                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                    }}"
            >
                {{-- <span>🇬🇧</span> --}}
                <span>English</span>
                @if($currentLocale === 'en')
                    <svg class="w-4 h-4 ml-auto text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                @endif
            </a>
            <a
                href="{{ request()->fullUrlWithQuery(['locale' => 'ar']) }}"
                class="flex items-center gap-2 px-4 py-2 text-sm transition-colors
                    {{ $currentLocale === 'ar'
                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                    }}"
            >
                {{-- <span>🇸🇦</span> --}}
                <span>العربية</span>
                @if($currentLocale === 'ar')
                    <svg class="w-4 h-4 ml-auto text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                @endif
            </a>
        </div>
    </div>
</div>
