@php
use App\Enums\Roles;
@endphp
<x-filament::page>
    <div class="text-center p-6 pb-3">
        <x-filament::button wire:click="syncData" wire:loading.attr="disabled">
            Sync Data between local and online
        </x-filament::button>
        @if(auth()->user()->role === Roles::GeneralAdmin())
        <x-filament::button wire:click="syncOnlineToLocal" wire:loading.attr="disabled">
            Sync Data from Online to local
        </x-filament::button>
        <x-filament::button wire:click="syncLocalToOnline" wire:loading.attr="disabled">
            Sync Data from local to Online
        </x-filament::button>
        @endif
        <p>
            <div wire:loading class="mt-4 text-sm text-gray-500">
                Syncing in progress, please wait...
            </div>
        </p>

    </div>
</x-filament::page>
