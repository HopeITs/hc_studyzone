<!-- resources/views/exports/entries_by_center_daily.blade.php -->
<table>
    <thead>
    <tr>
        <th>Day</th>
        @foreach($centers as $center)
            <th>{{ $center }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($data as $row)
        <tr>
            <td>{{ $row['day'] }}</td>
            @foreach($centers as $center)
                <td>{{ $row[$center] ?? 0 }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
