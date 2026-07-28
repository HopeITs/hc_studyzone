


<x-filament-panels::page>
    <div class="flex items-center justify-between">
        <div>
            <!-- Added dark:text-gray-400 -->
            <p class="text-sm font-bold text-gray-500 dark:text-gray-200">
                الطالب: {{ $record->firstname }} {{ $record->father_name }} {{ $record->lastname }}
            </p>
        </div>
    </div>

    <!-- Added dark:bg-gray-800 and dark:border-gray-700 -->
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        {{ $this->form }}
    </div>

    <div class="flex gap-2">
        <!-- The x-filament::button components already handle dark mode automatically! -->
        <x-filament::button
            wire:click="save"
            wire:target="save"
            x-data="{}"
            x-on:click="$el.disabled = true"
            icon="heroicon-o-check"
        >
            {{ $entryInstance ? 'حفظ التعديلات' : 'تسجيل الدخولية' }}
        </x-filament::button>

        <x-filament::button color="gray" :href="App\Filament\Resources\StudentResource::getUrl('index')" tag="a">
            إلغاء
        </x-filament::button>
    </div>
</x-filament-panels::page>
