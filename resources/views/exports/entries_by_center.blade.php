<!-- resources/views/exports/entries_by_center.blade.php -->
<table>
    <thead>
    <tr>
        <th>Month</th>
        @foreach($centers as $center)
            <th>{{ $center }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($data as $monthKey => $row)
        <tr>
            <td>{{ \Carbon\Carbon::parse($monthKey.'-01')->format('M Y') }}</td>
            @foreach($centers as $center)
                <td>{{ $row[$center] ?? 0 }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
