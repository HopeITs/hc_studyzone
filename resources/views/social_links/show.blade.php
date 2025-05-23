<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>StudyZone — {{ ucfirst($link->city) }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}" type="image/png">
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: auto; padding: 1rem; }
        header { text-align: center; margin-bottom: 2rem; }
        header img { height: 80px; }
        ul { list-style: none; padding: 0; }
        li + li { margin-top: 0.5rem; }
        a { text-decoration: none; color: #007bff; }
    </style>
</head>
<body>
    <header>
        <img src="{{ asset('images/logo.png') }}" alt="StudyZone Logo">
        <h1>{{ ucfirst($link->city) }} — StudyZone</h1>
    </header>

    <ul>
        @if($link->facebook)
            <li><a href="{{ $link->facebook }}" target="_blank">Facebook</a></li>
        @endif
        @if($link->instagram)
            <li><a href="{{ $link->instagram }}" target="_blank">Instagram</a></li>
        @endif
        @if($link->whatsapp)
            <li><a href="{{ $link->whatsapp }}" target="_blank">WhatsApp</a></li>
        @endif
        @if($link->telegram)
            <li><a href="{{ $link->telegram }}" target="_blank">Telegram</a></li>
        @endif
        @if($link->website)
            <li><a href="{{ $link->website }}" target="_blank">Official Website</a></li>
        @endif
    </ul>
</body>
</html>
