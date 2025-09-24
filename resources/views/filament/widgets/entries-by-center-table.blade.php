<x-filament-widgets::widget>
    <x-filament::card>
        <table class="table-auto w-full text-sm">
            <thead>
            <tr>
                <th class="px-2 py-1 text-left">Month</th>
                @foreach($centers as $center)
                    <th class="px-2 py-1 text-xs text-left ">{{ $center }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($data as $row)
                <tr class="border-b-1">
                    <td class="px-2 py-1">{{ $row['month'] }}</td>
                    @foreach($centers as $center)
                        <td class="px-2 py-1">{{ $row[$center] ?? 0 }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::card>
</x-filament-widgets::widget>
