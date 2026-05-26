{{-- filament/resources/entry-resource/pages/add-entry-services.blade.php --}}
<x-filament-panels::page>
        <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-gray-500">
                الطالب: {{ $record->student->firstname }} {{ $record->student->father_name }} {{ $record->student->lastname }}
            </p>
        </div>


    </div>
    <x-filament-panels::form wire:submit="saveServices">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedHeaderActions()"
            :full-width="false"
        />
    </x-filament-panels::form>

        <div class="flex gap-2">
        <!-- Find your submit button and change it to: -->
        <x-filament::button wire:click="saveServices" icon="heroicon-o-check">
            {{  'حفظ التعديلات'  }}
        </x-filament::button>

        <x-filament::button color="gray" :href="App\Filament\Resources\StudentResource::getUrl('view', ['record' => $record])" tag="a">
            إلغاء
        </x-filament::button>


    </div>
</x-filament-panels::page>
