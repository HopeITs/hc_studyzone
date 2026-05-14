<!-- resources/views/filament/forms/components/total-display.blade.php -->
<div class="fi-form-field-wrapper">
    <div class="p-4 mt-2 bg-gray-100 rounded-lg dark:bg-gray-800">
        <div class="flex items-center justify-between">
            <span class="text-lg font-medium text-gray-700 dark:text-gray-300">
                الإجمالي الكلي:
            </span>
            <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                {{ number_format($getState() ?? 0) }} SYP
            </span>
        </div>
    </div>
</div>
