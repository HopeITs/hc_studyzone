<!-- resources/views/filament/components/resource-hints.blade.php -->
{{-- Brand logo is the LCP element on the login screen; Inter latin/400 is the
     weight the login form's body text actually renders in. Both are
     same-origin, so no preconnect is needed — only preload. --}}
<link rel="preload" as="image" href="{{ asset('images/logo.png') }}" fetchpriority="high" />
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter/inter-latin-400-normal.woff2') }}" crossorigin />
