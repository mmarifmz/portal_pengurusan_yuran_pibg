<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
@PwaHead
</head>
    <body class="min-h-screen bg-white antialiased">
        {{ $slot }}
        @fluxScripts
@RegisterServiceWorkerScript
</body>
</html>
