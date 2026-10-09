<!-- resources/views/filament/components/donor-mode-toggle.blade.php -->
<div
    x-data="{
        donorMode: localStorage.getItem('donorMode') === 'true',
        toggle() {
            this.donorMode = !this.donorMode;
            localStorage.setItem('donorMode', this.donorMode);
            document.documentElement.classList.toggle('donor-mode', this.donorMode);
        }
    }"
    x-init="document.documentElement.classList.toggle('donor-mode', donorMode)"
>
    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            tag="button"
            icon="heroicon-m-eye-slash"
            x-on:click="toggle()"
            x-bind:class="donorMode ? 'fi-active bg-primary-50 dark:bg-primary-400/10' : ''"
        >
            <span x-text="donorMode ? '{{ __('general.labels.donor_mode_on') }}' : '{{ __('general.labels.donor_mode') }}'"></span>
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
</div>

<style>
    html.donor-mode .donor-hide,
    html.donor-mode .fi-grid-col:has(> .fi-sc-component > .donor-hide) {
        display: none !important;
    }
</style>
