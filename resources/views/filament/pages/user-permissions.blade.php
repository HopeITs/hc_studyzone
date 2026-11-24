<x-filament-panels::page>
    <div class="flex flex-wrap gap-2">
        <x-filament::button type="button" :color="$targetMode === 'name' ? 'primary' : 'gray'"
            aria-pressed="{{ $targetMode === 'name' ? 'true' : 'false' }}" wire:click="switchTargetMode('name')" wire:loading.attr="disabled">
            {{ __('general.user_permissions.edit_by_name') }}
        </x-filament::button>
        <x-filament::button type="button" :color="$targetMode === 'role' ? 'primary' : 'gray'"
            aria-pressed="{{ $targetMode === 'role' ? 'true' : 'false' }}" wire:click="switchTargetMode('role')" wire:loading.attr="disabled">
            {{ __('general.user_permissions.edit_by_role') }}
        </x-filament::button>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        @if ($this->canEditTarget())
            <div class="fi-input-wrp" style="position: sticky; bottom: 0.5rem; z-index: 10;">
                <div style="padding: 0.75rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; width: 100%;">
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <x-filament::button type="button" color="gray" size="sm" wire:click="setAllPermissions('allow')" wire:loading.attr="disabled">
                            {{ __('general.user_permissions.allow_all') }}
                        </x-filament::button>
                        <x-filament::button type="button" color="gray" size="sm" wire:click="setAllPermissions('deny')" wire:loading.attr="disabled">
                            {{ __('general.user_permissions.deny_all') }}
                        </x-filament::button>
                        <x-filament::button type="button" color="gray" size="sm" wire:click="setAllPermissions('inherit')" wire:loading.attr="disabled">
                            {{ __('general.user_permissions.reset_all') }}
                        </x-filament::button>
                    </div>
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        {{ __('general.user_permissions.save') }}
                    </x-filament::button>
                </div>
            </div>
        @endif
    </form>
</x-filament-panels::page>
