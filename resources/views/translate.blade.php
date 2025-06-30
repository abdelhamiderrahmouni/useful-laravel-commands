<div>
    <div>
        <h1>Translation Manager</h1>
        <p>Source Language: {{ $source }}</p>
    </div>
    
    <div>
        <table>
            <thead>
            <tr>
                <th>Language</th>
                <th>Missing Translations</th>
                <th>Total Translations</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($languages as $lang => $stats)
                <tr>
                    <td>
                        <div>
                            <span>{{ $lang }}</span>
                        </div>
                    </td>
                    <td>
                        <div>
                            <span>{{ $stats['missing'] }}</span>
                        </div>
                    </td>
                    <td>
                        <div>
                            <span>{{ $stats['total'] }}</span>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>