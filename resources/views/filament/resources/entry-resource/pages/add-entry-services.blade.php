{{-- filament/resources/entry-resource/pages/add-entry-services.blade.php --}}
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="saveServices">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedHeaderActions()"
            :full-width="false"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
