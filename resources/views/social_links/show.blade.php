<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Interactive Links — {{ strtoupper($city->name_en ?? $city->name) }}</title>
    <link rel="icon" href="{{ asset('images/new_logo.png') }}" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@700&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Somar Sons';
            src: url('{{ asset('fonts/SomarSons-Bold.woff2') }}') format('woff2');
            font-weight: bold;
            font-style: normal;
        }

        body {
            font-family: 'Somar Sons', 'Lato', sans-serif;
            font-weight: bold;
            margin: 0;
            padding: 0;
            background-color: #003c54;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1rem 1.25rem;
            box-sizing: border-box;
            flex-wrap: wrap;
        }

        .left-logo {
            height: 100px;
        }

        .right-block {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-right: 1.25rem;
        }

        .right-logo {
            height: 40px;
        }

        .small-logo {
            height: 30px;
            margin-bottom: 0.25rem;
        }

        .city-info {
            text-align: right;
        }

        .city {
            margin-top: 0.5rem;
            font-size: clamp(1.125rem, 3vw, 1.5rem); /* MODIFIED HERE */
            text-transform: uppercase;
        }

        .country {
            font-size: 0.9rem;
            font-weight: normal;
            text-transform: uppercase;
            opacity: 0.85;
            line-height: 1.1;
        }

        .content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 8.5rem 1rem 1rem;
            margin: 0 auto;
            max-width: 600px;
            width: 100%;
            box-sizing: border-box;
            flex: 1;
        }

        .links {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            width: 100%;
            max-width: 300px;
            margin-top: 5rem;
            flex-grow: 1;
        }

        .links a {
            padding: 0.75rem 1rem;
            background: #fff;
            color: #003c54;
            text-align: center;
            text-decoration: none;
            border: 1px solid #fff;
            border-radius: 30px;
            font-size: 1rem;
            transition: background .2s, color .2s;
            width: 100%;
            text-transform: uppercase;
        }

        .links a:hover {
            background: #f59a24;
            color: #fff;
            border-color: #f59a24;
        }

        footer {
            text-align: center;
            font-size: 0.9rem;
            padding-bottom: 1rem;
            position: relative;
            margin-bottom: 1rem;
        }

        footer::after {
            content: '';
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 10px;
            background: #f59a24;
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .right-block {
                align-items: center;
                margin-right: 0;
            }

            .left-logo {
                height: 80px;
            }

            .right-logo {
                height: 30px;
            }

            .links a {
                font-size: 1.1rem;
                padding: 1rem;
            }
        }

        @media (max-width: 350px) {
            .city {
                font-size: 1.5rem; /* This will ensure it's 24px on very small screens */
            }

            .links a {
                font-size: 1rem;
                padding: 0.75rem;
            }

            .content {
                padding-top: 6rem;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <img src="{{ asset('images/new_logo.png') }}" alt="StudyZone Logo" class="left-logo">
        <div class="right-block">
            <img src="{{ asset('images/sz.png') }}" alt="Study Zone" class="right-logo small-logo">
            <div class="city-info">
                <div class="city">{{ strtoupper($city->name_en ?? $city->name) }}</div>
            </div>
        </div>
    </header>

    <div class="content container">
        <div class="links">
            @if ($city->facebook)
                <a href="{{ $city->facebook }}" target="_blank">Facebook</a>
            @endif
            @if ($city->instagram)
                <a href="{{ $city->instagram }}" target="_blank">Instagram</a>
            @endif
            @if ($city->whatsapp)
                <a href="{{ $city->whatsapp }}" target="_blank">WhatsApp</a>
            @endif
            @if ($city->telegram)
                <a href="{{ $city->telegram }}" target="_blank">Telegram</a>
            @endif
            @if ($city->website)
                <a href="{{ $city->website }}" target="_blank">HCSYRIA.ORG</a>
            @endif
        </div>

        <footer>
            A Project by:<br>CHRISTIAN HOPE CENTER
        </footer>
    </div>
</body>

</html>
