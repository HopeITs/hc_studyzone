<!-- resources/views/filament/components/language-switcher.blade.php -->
<div class="flex items-center gap-1" x-data>
    <a
        href="{{ request()->fullUrlWithQuery(['locale' => 'en']) }}"
        class="inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors
        {{ app()->getLocale() === 'en'
            ? 'bg-primary-500 text-white shadow-sm'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
        }}"
    >
        EN
    </a>
    <a
        href="{{ request()->fullUrlWithQuery(['locale' => 'ar']) }}"
        class="inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors
        {{ app()->getLocale() === 'ar'
            ? 'bg-primary-500 text-white shadow-sm'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
        }}"
    >
        AR
    </a>
</div>
