<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Student Entry</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
            <h2 class="text-xl font-bold mb-6 text-gray-900">
                Record Entry for: {{ $student->firstname }} {{ $student->lastname }}
            </h2>

            @if (session('success'))
                <div class="mb-6 text-sm font-medium text-green-600">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('student.entry.store', $student->id) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="entry_type" class="block text-sm font-medium text-gray-700 mb-1">
                            Entry Type
                        </label>
                        <select id="entry_type" name="entry_type" required
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50 text-sm hover:border-orange-600">
                            @foreach ($entryTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                            Submit Entry
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>

</html>
