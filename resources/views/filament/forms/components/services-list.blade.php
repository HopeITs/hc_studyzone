<div class="space-y-2">
    @if(empty($getState()))
    @else
        @foreach($getState() as $service)
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $service['name'] }}</span>
                <span class="font-bold text-primary-600 dark:text-primary-400">{{ number_format($service['price'], 2) }} SYP</span>
            </div>
        @endforeach
    @endif
</div>