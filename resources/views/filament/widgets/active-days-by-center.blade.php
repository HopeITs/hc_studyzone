<x-filament-widgets::widget>
    <x-filament::card>
        <div class="flex justify-between mb-4">
            <h1 class="font-bold">Active Days per center</h1>

            <a class="p-2 bg-primary-500 rounded-md dark:text-white font-bold  text-xs"
               href="{{route('test-export',['from' => $from, 'to'=> $to])}}">Export to Excel</a>
        </div>
        <table class="table-auto w-full text-sm">
            <thead>
            <tr>
                <th class="px-2 py-1 text-right">Month</th>
                @foreach($centers as $center)
                    <th class="px-2 py-1 text-xs text-center ">{{ $center }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($data as $row)
                <tr class=" border-b border-gray-400">
                    <td class="px-2 py-1">{{ $row['month'] }}</td>
                    @foreach($centers as $center)

                        <td class="px-2 py-1  text-center">{{ $row[$center] ?? 0 }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::card>
</x-filament-widgets::widget>
