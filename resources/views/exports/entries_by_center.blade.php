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
    @foreach($data as $row)
        <tr>
            <td>{{ $row['month'] }}</td>
            @foreach($centers as $center)
                <td>{{ $row[$center] ?? 0 }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
