<div class="p-4 flex justify-between">
    <div class="rtl flex flex-col gap-y-4">
        <h3 class="text-lg font-semibold mb-8">{{ __('general.labels.student_details') }}</h3>
        <p><strong>{{ __('general.labels.uuid') }}:</strong> <br>{{ $uuid }}</p>
        <p>
            <strong>{{ __('general.labels.url') . ' (Online)' }}:</strong>
            <br>
            <a href="{{ $onlineUrl }}" target="_blank" class="text-blue-600 underline">{{ $onlineUrl }}</a>
        </p>
        <p>
            <strong>{{ __('general.labels.url') . ' (Offline)' }}:</strong>
            <br>
            <a href="{{ $offlineUrl }}" target="_blank" class="text-blue-600 underline">{{ $offlineUrl }}</a>
        </p>
    </div>

    <div class="mt-4">
        <strong class="mb-4">{{ __('general.labels.qr_code') }}:</strong><br><br>
        {!! $qrCode !!}
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('download-qr-code', ['uuid' => $uuid]) }}"
       class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">
        {{ __('general.labels.download_qr_code') }}
    </a>
</div>
