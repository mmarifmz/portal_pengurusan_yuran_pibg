<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="robots" content="{{ $campaign->allow_public_indexing ? 'index, follow' : 'noindex, nofollow, noarchive' }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:site_name" content="Jogathon Digital SK Sri Petaling">
        <meta name="twitter:card" content="summary">
        <link rel="canonical" href="{{ url()->current() }}">
        <link rel="icon" href="{{ \App\Models\SiteSetting::faviconUrl() }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .jogathon-grid {
                background-color: rgb(2 44 34);
                background-image:
                    radial-gradient(circle at 1px 1px, rgb(209 250 229 / 0.18) 1px, transparent 0),
                    linear-gradient(135deg, rgb(2 44 34) 0%, rgb(6 78 59) 52%, rgb(4 120 87) 100%);
                background-size: 22px 22px;
            }

            .journey-track::before {
                background-image: repeating-linear-gradient(90deg, transparent 0 18px, rgb(255 255 255 / .7) 18px 29px);
            }

            @media (prefers-reduced-motion: no-preference) {
                .journey-runner { animation: jogathon-bob 1.8s ease-in-out infinite; }
                @keyframes jogathon-bob { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-5px) } }
            }
        </style>
    </head>
    <body class="min-h-screen overflow-x-hidden bg-[#f4f8f5] font-sans text-slate-900 antialiased">
        <header class="border-b border-emerald-950/10 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
                <img src="{{ \App\Models\SiteSetting::schoolLogoUrl() }}" alt="Logo SK Sri Petaling" class="size-11 rounded-full object-contain">
                <div class="min-w-0">
                    <a href="{{ route('home') }}" class="block truncate text-sm font-extrabold tracking-wide text-emerald-950 sm:text-base">JOGATHON DIGITAL</a>
                    <p class="truncate text-xs text-slate-600">SK Sri Petaling</p>
                </div>
                <a href="{{ route('home') }}" class="ml-auto rounded-full border border-emerald-700/20 px-3 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-50">Laman kempen</a>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="mt-12 border-t border-emerald-950/10 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-8 text-center text-xs leading-5 text-slate-500 sm:px-6">
                <p class="font-semibold text-slate-700">Jogathon Digital SK Sri Petaling</p>
                <p>RM1 sumbangan bersamaan 10 meter perjalanan maya.</p>
                <p class="mt-2">Halaman ini tidak memaparkan nombor murid, kod keluarga atau maklumat perhubungan penyumbang.</p>
            </div>
        </footer>
    </body>
</html>
