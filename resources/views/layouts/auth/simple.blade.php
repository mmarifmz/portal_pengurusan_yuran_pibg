<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <div class="flex items-center justify-between gap-4">
                        <img src="{{ asset('images/sksp-logo.png') }}"
                            alt="SK Sri Petaling logo"
                            class="h-12 w-12 rounded-full border border-zinc-200 bg-white p-1 shadow-sm" />
                    </div> 

                    <span class="sr-only">{{ config('app.name', 'Portal PIBG') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
