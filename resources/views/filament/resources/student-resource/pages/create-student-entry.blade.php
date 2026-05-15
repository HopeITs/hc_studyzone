<x-filament-panels::page>
    <div class="flex items-center justify-between mb-3">
        <div>
            <p class="text-sm text-gray-500">
                الطالب: {{ $record->firstname }} {{ $record->father_name }} {{ $record->lastname }}
            </p>
        </div>


    </div>
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
    {{ $this->form }}
    </div>
     <div class="flex gap-2">
             <x-filament::button
                color="primary"
                wire:click="create"
                icon="heroicon-o-check"
            >
                {{ __('general.labels.save_entry') }}
            </x-filament::button>
            <x-filament::button
                color="gray"
                :href="App\Filament\Resources\StudentResource::getUrl('view', ['record' => $record])"
                tag="a"
            >
                إلغاء
            </x-filament::button>


        </div>
</x-filament-panels::page>
