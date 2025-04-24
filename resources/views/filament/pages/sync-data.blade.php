<x-filament::page>
    <div class="text-center p-6 pb-3">
        <x-filament::button wire:click="syncData" wire:loading.attr="disabled">
            Sync Data to Online Server
        </x-filament::button>
        <p>
            <div wire:loading class="mt-4 text-sm text-gray-500">
                Syncing in progress, please wait...
            </div>
        </p>

    </div>
</x-filament::page>
