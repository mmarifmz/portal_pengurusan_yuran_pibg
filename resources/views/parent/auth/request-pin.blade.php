<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <title>Parent Login (TAC) | {{ config('app.name') }}</title>

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

</head>
<body class="portal-bg min-h-screen text-[color:var(--brand-ink)] antialiased">
    <header class="border-b border-zinc-200/80 bg-white/85 backdrop-blur-sm">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="{{ \App\Models\SiteSetting::schoolLogoUrl() }}" alt="Logo SK Sri Petaling" class="h-12 w-12 rounded-full border border-zinc-200 bg-white p-1 shadow-sm sm:h-14 sm:w-14" />
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-zinc-500 sm:text-xs">Portal Rasmi</p>
                    <p class="text-sm font-bold text-[color:var(--brand-forest)] sm:text-base">Yuran & Sumbangan PIBG SK Sri Petaling</p>
                </div>
            </a>

            <div class="grid w-full grid-cols-2 gap-2 sm:w-auto sm:flex sm:gap-2">
                <a href="{{ route('home') }}" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-center text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 sm:text-sm">Back to portal</a>
                <a href="{{ route('parent.search') }}" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-center text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 sm:text-sm">Carian Nama Murid</a>
                <a href="{{ route('login') }}" class="rounded-lg bg-[color:var(--brand-forest)] px-3 py-2 text-center text-xs font-semibold text-white transition hover:opacity-90 sm:text-sm">Guru / Admin</a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-7 sm:px-6 lg:px-8 lg:py-10">
        <section class="hero-strip box p-5 sm:p-8">
            <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr] lg:items-start">
                <div>
                    <p class="inline-flex items-center rounded-full bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-[color:var(--brand-forest)] ring-1 ring-[color:var(--brand-gold)]/60 sm:text-xs">
                        Parent Access
                    </p>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-zinc-900 sm:text-4xl">
                        {{ __('Log Masuk Penjaga / Ibu Bapa') }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-zinc-700 sm:text-base">
                        {{ __('Masukkan nombor telefon anda untuk menerima kod TAC melalui WhatsApp.') }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm leading-relaxed text-zinc-600 sm:text-base">
                        <span>{{ __('Nota tambahan: Perlukan bantuan TAC?') }}</span>
                        <a
                            href="https://wa.me/601111055569?text={{ rawurlencode('Assalamualaikum, saya perlukan bantuan log masuk TAC Parent Access PIBG.') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 transition hover:border-emerald-400 hover:bg-emerald-100 sm:text-sm"
                        >
                            WhatsApp Sokongan Teknikal
                        </a>
                    </div>

                    @if ($selectedBilling)
                        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            <p>
                                You are starting payment onboarding for family code <span class="font-semibold">{{ $selectedBilling->family_code }}</span> ({{ $selectedBilling->billing_year }}).
                            </p>
                            <p class="mt-2 text-emerald-800">
                                If you want to register a new parent phone number for this family, type the new number in the <span class="font-semibold">Phone number</span> field and click <span class="font-semibold">Log Masuk Ibu Bapa / Penjaga</span>.
                            </p>
                        </div>
                    @endif

                    @if ($showPaidFamilyPhoneReset)
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                            <p class="font-semibold">Wrong phone number on a paid family record?</p>
                            <p class="mt-1">
                                If this paid family has already changed to <span class="font-semibold">{{ $paidFamilyResetPhone }}</span>,
                                you can reset the saved parent phone and send the TAC there.
                            </p>

                            <form method="POST" action="{{ route('parent.login.request') }}" class="mt-4">
                                @csrf
                                <input type="hidden" name="phone" value="{{ $paidFamilyResetPhone }}">
                                <input type="hidden" name="family_billing_id" value="{{ $selectedBilling->id }}">
                                <input type="hidden" name="confirm_phone_reset" value="1">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(245,158,11,0.18)] transition hover:bg-amber-600"
                                >
                                    Reset phone and send TAC
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="box p-5 sm:p-6">
                    <x-auth-session-status class="mb-4 text-sm text-center text-emerald-700" :status="session('status')" />

                    <form method="POST" action="{{ route('parent.login.request') }}" class="flex flex-col gap-5">
                        @csrf

                        @if ($selectedBilling)
                            <input type="hidden" name="family_billing_id" value="{{ $selectedBilling->id }}">
                        @endif
                        <input type="hidden" name="return_url" value="{{ $returnUrl }}">

                        <div>
                            <label for="phone" class="mb-2 block text-sm font-semibold text-zinc-800">
                                {{ __('Phone number') }}
                            </label>
                            <input
                                id="phone"
                                name="phone"
                                type="text"
                                value="{{ old('phone', $prefillPhone) }}"
                                required
                                autofocus
                                autocomplete="tel"
                                placeholder="0123456789"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-base text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-[color:var(--brand-green)] focus:ring-4 focus:ring-emerald-100"
                            />
                            @error('phone')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="mt-1 inline-flex w-full items-center justify-center rounded-xl bg-[color:var(--brand-forest)] px-5 py-3 text-sm font-semibold text-white shadow-[0_12px_24px_rgba(23,74,52,0.18)] transition hover:bg-[color:var(--brand-green)] hover:shadow-[0_16px_30px_rgba(23,74,52,0.22)]"
                        >
                            {{ __('Log Masuk Ibu Bapa / Penjaga') }}
                        </button>
                    </form>

                    <div class="mt-6 border-t border-zinc-200 pt-5 text-center text-sm text-zinc-600">
                        <span>{{ __('Akses Guru / Admin:') }}</span>
                        <a href="{{ route('login') }}" class="ms-1 font-semibold text-[color:var(--brand-green)] underline decoration-transparent transition hover:decoration-current">
                            {{ __('Log in here') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-200/80 bg-white/80">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-1 px-4 py-4 text-center text-xs text-zinc-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>Portal Yuran & Sumbangan PIBG SK Sri Petaling 2026</p>
            <p>Demi kemudahan semakan keluarga & bayaran yuran tahunan</p>
        </div>
    </footer>

    @fluxScripts

</body>
</html>
