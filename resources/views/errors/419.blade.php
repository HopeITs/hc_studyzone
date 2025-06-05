<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>انتهت الجلسة — 419</title>
    <link rel="icon" href="{{ asset('images/new_logo.png') }}" type="image/png">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @font-face {
            font-family: 'Somar Sons';
            src: url('{{ asset('fonts/SomarSons-Bold.woff2') }}') format('woff2');
            font-weight: bold;
            font-style: normal;
        }
        .font-somar {
            font-family: 'Somar Sons', sans-serif;
        }
    </style>
</head>
<body class="font-somar font-bold text-white min-h-screen flex flex-col m-0 p-0">

<header class="absolute top-0 left-0 right-0 flex justify-between items-start bg-[#003c54] p-5 flex-wrap">
    <img src="{{ asset('images/new_logo.png') }}" alt="StudyZone Logo" class="h-[100px]">
    <div class="flex flex-col items-end mr-5 my-auto">
        <img src="{{ asset('images/sz.png') }}" alt="Study Zone" class="h-10">
    </div>
</header>

<div class="max-w-[600px] w-full mx-auto px-4 pt-[8.5rem] pb-4 flex flex-col items-center text-center flex-1">
    <div class="text-[6rem] text-[#f59a24]">419</div>
    <label class="text-[1.8rem] mt-4 text-[#f59a24]">انتهت الجلسة... </label>
    <img src="{{ asset('images/419-error-img.png') }}" alt="419" class="mt-4">

    <a href="{{ url()->previous() }}" class="mt-8 px-6 py-3 bg-white text-[#003c54] border-2 border-[#003c54] rounded-full text-xl transition duration-200 hover:bg-[#f59a24] hover:border-[#f59a24] hover:text-white">
        العودة للعمل
    </a>
</div>

<footer class="bg-[#003c54] text-center text-sm py-4 relative ">
    <span>A Project by:</span><br>
    <a href="https://hcsyria.org">Christian Hope Center</a>
    <div class="fixed left-0 right-0 bottom-0 h-[10px] bg-[#f59a24]"></div>
</footer>

</body>
</html>
