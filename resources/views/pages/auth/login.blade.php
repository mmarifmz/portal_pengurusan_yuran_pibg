<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $title = 'Log Masuk Guru / Admin | Portal Yuran PIBG SK Sri Petaling';
    @endphp
    @include('partials.head')

    <style>
        :root {
            --brand-forest: #174a34;
            --brand-green: #2f7a55;
            --brand-gold: #e5b338;
            --brand-ink: #1f2a24;
            --brand-soft: #f3f8f3;
        }

        .portal-bg {
            background:
                radial-gradient(70rem 34rem at 0% 0%, rgba(47, 122, 85, 0.14), transparent 58%),
                radial-gradient(70rem 34rem at 100% 0%, rgba(229, 179, 56, 0.17), transparent 55%),
                linear-gradient(160deg, #f5faf6 0%, #ffffff 38%, #fff8e8 100%);
        }

        .hero-strip {
            background: linear-gradient(135deg, rgba(43, 125, 86, 0.16), rgba(102, 140, 230, 0.16));
        }

        .box {
            border: 1px solid #e5e7eb;
            background: rgba(255, 255, 255, 0.94);
            border-radius: 1rem;
            box-shadow: 0 8px 24px rgba(30, 41, 59, 0.07);
        }
    </style>
@PwaHead
</head>
<body class="portal-bg min-h-screen text-[color:var(--brand-ink)] antialiased">
    <header class="border-b border-zinc-200/80 bg-white/85 backdrop-blur-sm">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="{{ \App\Models\SiteSetting::schoolLogoUrl() }}" alt="Logo SK Sri Petaling" class="h-12 w-12 rounded-full border border-zinc-200 bg-white p-1 shadow-sm sm:h-14 sm:w-14" />
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-zinc-500 sm:text-xs">Portal Rasmi</p>
                    <p class="text-sm font-bold text-[color:var(--brand-forest)] sm:text-base">Yuran &amp; Sumbangan PIBG SK Sri Petaling</p>
                </div>
            </a>

            <div class="grid w-full grid-cols-2 gap-2 sm:w-auto sm:flex sm:gap-2">
                <a href="{{ route('home') }}" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-center text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 sm:text-sm">Back to portal</a>
                <a href="{{ route('parent.search') }}" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-center text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 sm:text-sm">Carian Nama Murid</a>
                <a href="{{ route('parent.login.form') }}" class="rounded-lg bg-[color:var(--brand-forest)] px-3 py-2 text-center text-xs font-semibold text-white transition hover:opacity-90 sm:text-sm">Log Masuk Ibu Bapa</a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-7 sm:px-6 lg:px-8 lg:py-10">
        <section class="hero-strip box p-5 sm:p-8">
            <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr] lg:items-start">
                <div>
                    <p class="inline-flex items-center rounded-full bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-[color:var(--brand-forest)] ring-1 ring-[color:var(--brand-gold)]/60 sm:text-xs">
                        Guru / Admin Access
                    </p>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-zinc-900 sm:text-4xl">
                        {{ __('Log Masuk Guru / Admin') }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-zinc-700 sm:text-base">
                        {{ __('Masukkan email dan kata laluan untuk akses pengurusan portal yuran PIBG.') }}
                    </p>
                </div>

                <div class="box p-5 sm:p-6">
                    <x-auth-session-status class="mb-4 text-sm text-center text-emerald-700" :status="session('status')" />

                    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-zinc-800">{{ __('Email address') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="cikgu.azam@sripetaling.edu.my" class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-base text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-[color:var(--brand-green)] focus:ring-4 focus:ring-emerald-100" />
                            @error('email')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <label for="password" class="block text-sm font-semibold text-zinc-800">{{ __('Password') }}</label>
                            </div>
                            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="{{ __('Password') }}" class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-base text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-[color:var(--brand-green)] focus:ring-4 focus:ring-emerald-100" />
                            @error('password')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-zinc-300 text-[color:var(--brand-green)] focus:ring-emerald-300" />
                            <span>{{ __('Remember me') }}</span>
                        </label>

                        <button type="submit" class="mt-1 inline-flex w-full items-center justify-center rounded-xl bg-[color:var(--brand-forest)] px-5 py-3 text-sm font-semibold text-white shadow-[0_12px_24px_rgba(23,74,52,0.18)] transition hover:bg-[color:var(--brand-green)] hover:shadow-[0_16px_30px_rgba(23,74,52,0.22)]" data-test="login-button">
                            {{ __('Log in') }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    @fluxScripts
@RegisterServiceWorkerScript
</body>
</html>
