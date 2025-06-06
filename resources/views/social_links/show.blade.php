<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Social Media Links — {{ strtoupper($city->name_en ?? $city->name) }}</title>
    <link rel="icon" href="{{ asset('images/new_logo.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        lato: [ 'Lato', 'sans-serif'],
                    },
                    colors: {
                        primary: '#003c54',
                        accent: '#f59a24',
                    },
                }
            }
        }
    </script>
</head>

<body class="bg-primary text-white font-lato font-bold min-h-screen flex flex-col justify-center items-center m-0 p-0">
<div
    class="container mx-auto flex flex-col items-center justify-center px-4 pt-[8.5rem] pb-4 max-w-[600px] w-full flex-1">
    <header class="w-full flex items-start max-w-96 justify-between px-2 py-4 bg-transparent absolute top-0">
        <!-- Left: Logo -->
        <img src="{{ asset('images/new_logo.png') }}" alt="StudyZone Logo" class=" size-20">

        <!-- Right: Study Zone & City -->
        <div class="flex flex-col justify-end items-end leading-tight">
            <img src="{{ asset('images/sz.png') }}" alt="StudyZone Logo" class="w-44">

            <div class="text-base text-white tracking-wide uppercase">
                {{ strtoupper($city->name_en ?? $city->name) }}
            </div>
        </div>
    </header>

    <div class="flex flex-col items-center gap-4 w-full font-black max-w-72 mt-20 flex-grow">
        @if ($city->facebook)
            <a href="{{ $city->facebook }}" target="_blank"
               class="w-full text-center px-4 py-3 text-primary bg-white border border-white rounded-full text-base uppercase transition-colors duration-200 hover:bg-accent hover:text-white hover:border-accent">Facebook</a>
        @endif
        @if ($city->instagram)
            <a href="{{ $city->instagram }}" target="_blank"
               class="w-full text-center px-4 py-3 text-primary bg-white border border-white rounded-full text-base uppercase transition-colors duration-200 hover:bg-accent hover:text-white hover:border-accent">Instagram</a>
        @endif
        @if ($city->whatsapp)
            <a href="{{ $city->whatsapp }}" target="_blank"
               class="w-full text-center px-4 py-3 text-primary bg-white border border-white rounded-full text-base uppercase transition-colors duration-200 hover:bg-accent hover:text-white hover:border-accent">WhatsApp</a>
        @endif
        @if ($city->telegram)
            <a href="{{ $city->telegram }}" target="_blank"
               class="w-full text-center px-4 py-3 text-primary bg-white border border-white rounded-full text-base uppercase transition-colors duration-200 hover:bg-accent hover:text-white hover:border-accent">Telegram</a>
        @endif
        @if ($city->website)
            <a href="{{ $city->website }}" target="_blank"
               class="w-full text-center px-4 py-3 text-primary bg-white border border-white rounded-full text-base uppercase transition-colors duration-200 hover:bg-accent hover:text-white hover:border-accent">HCSYRIA.ORG</a>
        @endif
    </div>

    <footer class="text-center leading-none  relative pb-4 ">
        <span class="text-white text-xs font-light ">A Project by:</span>
        <p>
            CHRISTIAN HOPE CENTER
        </p>

        <div class="fixed text-sm left-0 right-0 bottom-0 h-2.5 bg-accent"></div>
    </footer>
</div>
</body>

</html>
