<div class="p-4">
    <h3 class="text-lg font-semibold mb-4">Student Details</h3>
    <p><strong>UUID:</strong> {{ $uuid }}</p>
    <p><strong>URL:</strong> <a href="{{ $url }}" target="_blank" class="text-blue-600 underline">{{ $url }}</a></p>
    <div class="mt-4">
        <strong>QR Code:</strong><br>
        {!! $qrCode !!}
    </div>
</div>
<div class="mt-4">
    <a href="{{ route('download-qr-code', ['uuid' => $uuid]) }}" class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">
        Download QR Code
    </a>
</div>
