<!-- resources/views/filament/components/rtl-styles.blade.php -->
@if(app()->getLocale() === 'ar')
<style nonce="{{ app('csp.nonce') }}">
    html { direction: rtl; }
    [dir="ltr"] { direction: ltr; }
</style>
@endif
