<table>
    <thead>
        <tr>
            <th>Education</th>
            <th>Total</th>
            <th>Males</th>
            <th>Females</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($educations as $education)
            <tr>
                <td>{{ $education->name }}</td>
                <td>{{ $education->total }}</td>
                <td>{{ $education->males }}</td>
                <td>{{ $education->females }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
