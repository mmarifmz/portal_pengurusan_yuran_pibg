<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $title = 'Portal Yuran PIBG SK Sri Petaling';
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
            background: linear-gradient(135deg, rgba(43, 125, 86, 0.16), rgba(102, 140, 230, 0.26));
        }

        .box {
            border: 1px solid #e5e7eb;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 1rem;
            box-shadow: 0 8px 24px rgba(30, 41, 59, 0.07);
        }

        .section-title {
            letter-spacing: -0.01em;
        }

        .hero-title {
            font-size: clamp(1.9rem, 3vw + 0.9rem, 3.7rem);
            line-height: 1.06;
        }

        .search-input {
            border: 0;
            background: linear-gradient(180deg, rgba(244, 247, 244, 0.98), rgba(255, 255, 255, 0.96));
            box-shadow:
                inset 0 2px 8px rgba(15, 23, 42, 0.08),
                inset 0 -1px 0 rgba(255, 255, 255, 0.9);
            padding: 0;
            min-height: 3.5rem;
            height: 3.5rem;
            line-height: 3.5rem;
            text-indent: 1rem;
        }

        .search-input:focus {
            border: 0;
            outline: none;
            box-shadow:
                inset 0 2px 10px rgba(23, 74, 52, 0.12),
                0 0 0 2px rgba(47, 122, 85, 0.12);
        }

        .search-input::placeholder {
            color: #9ca3af;
            line-height: 3.5rem;
        }

        /* Toast polish for public portal: visible but less disruptive. */
        #toaster {
            z-index: 40;
        }

        @media (max-width: 640px) {
            #toaster {
                align-items: center !important;
                padding: 0 0.75rem 0.9rem !important;
            }
        }
    </style>
@PwaHead
</head>
<body class="portal-bg min-h-screen text-[color:var(--brand-ink)] antialiased">
    <header class="border-b border-zinc-200/80 bg-white/85 backdrop-blur-sm">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <img src="{{ \App\Models\SiteSetting::schoolLogoUrl() }}" alt="Logo SK Sri Petaling" class="h-12 w-12 rounded-full border border-zinc-200 bg-white p-1 shadow-sm sm:h-14 sm:w-14" />
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-zinc-500 sm:text-xs">Portal Rasmi</p>
                    <p class="text-sm font-bold text-[color:var(--brand-forest)] sm:text-base">Yuran & Sumbangan PIBG SK Sri Petaling</p>
                </div>
            </div>

            <div class="grid w-full grid-cols-2 gap-2 sm:w-auto sm:flex sm:gap-2">
                <a href="{{ route('parent.login.form') }}" class="rounded-lg border border-[color:var(--brand-green)] px-3 py-2 text-center text-xs font-semibold text-[color:var(--brand-green)] transition hover:bg-[color:var(--brand-green)] hover:text-white sm:text-sm">Log Masuk Ibu Bapa</a>
                <a href="{{ route('login') }}" class="rounded-lg bg-[color:var(--brand-forest)] px-3 py-2 text-center text-xs font-semibold text-white transition hover:opacity-90 sm:text-sm">Guru / Admin</a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-7 sm:px-6 lg:px-8 lg:py-10">
        <section class="hero-strip box p-5 sm:p-8">
            <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr] lg:items-end">
                <div>
                    <p class="inline-flex items-center rounded-full bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-[color:var(--brand-forest)] ring-1 ring-[color:var(--brand-gold)]/60 sm:text-xs">
                        Sesi 2026 / 2027
                    </p>
                    <h1 class="hero-title mt-4 font-extrabold text-zinc-900">
                        Semakan & Bayaran
                        <span class="text-[color:var(--brand-green)]">Yuran PIBG</span>
                    </h1>
                    <p class="mt-4 max-w-3xl text-sm leading-relaxed text-zinc-700 sm:text-base">
                        Portal ini digunakan untuk semakan murid, semakan status yuran, dan rujukan pembayaran sumbangan PIBG.
                        Kadar asas ialah <strong>RM100 setahun bagi setiap keluarga</strong> berdasarkan kod keluarga.
                    </p>
                    <form action="{{ route('parent.search') }}" method="GET" class="mt-5 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                        <div>
                            <label for="hero_student_keyword" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-600">Carian Murid Seluruh Portal</label>
                            <input id="hero_student_keyword" name="student_keyword" type="text" class="search-input w-full rounded-xl text-sm focus:ring-0" placeholder="Contoh : Muhammad">
                        </div>
                        <button type="submit" class="rounded-xl bg-[color:var(--brand-green)] px-5 py-3 text-center text-sm font-semibold text-white transition hover:bg-[color:var(--brand-forest)]">Semak Nama Murid</button>
                    </form>
                </div>

                <div class="box p-4 sm:p-5">
                    <h2 class="section-title text-base font-bold text-[color:var(--brand-forest)] sm:text-lg">Ringkasan Portal</h2>
                    <div class="mt-3 rounded-xl border border-zinc-200 bg-white p-4">
                        <p class="text-base font-semibold text-[color:var(--brand-forest)]">Kemudahan untuk Ibu Bapa</p>
                        <p class="mt-2 text-sm text-zinc-700">Nikmati akses mudah kepada:</p>
                        <div class="mt-3 space-y-2 text-sm text-zinc-700">
                            <p>&#x1F4C5; Takwim sekolah untuk perancangan aktiviti anak</p>
                            <p>&#x1F9FE; Rekod dan resit pembayaran bermula 2025</p>
                            <p>&#x1F4B3; Pembayaran yuran terkini secara online</p>
                            <p>&#x1F510; Akses selamat tanpa kata laluan (TAC OTP)</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="box p-5">
                <h3 class="text-sm font-bold uppercase tracking-wide text-zinc-600">Langkah 1</h3>
                <p class="mt-2 text-base font-semibold text-[color:var(--brand-forest)]">Semak Rekod Anak</p>
                <p class="mt-2 text-sm text-zinc-600">Gunakan carian awam untuk semak nama murid dan kod keluarga.</p>
            </article>
            <article class="box p-5">
                <h3 class="text-sm font-bold uppercase tracking-wide text-zinc-600">Langkah 2</h3>
                <p class="mt-2 text-base font-semibold text-[color:var(--brand-forest)]">Login Parent Dengan TAC</p>
                <p class="mt-2 text-sm text-zinc-600">Masukkan nombor telefon, terima TAC, dan terus masuk ke dashboard.</p>
            </article>
            <article class="box p-5">
                <h3 class="text-sm font-bold uppercase tracking-wide text-zinc-600">Langkah 3</h3>
                <p class="mt-2 text-base font-semibold text-[color:var(--brand-forest)]">Semak Bil Keluarga</p>
                <p class="mt-2 text-sm text-zinc-600">Semua anak dengan kod keluarga sama dicaj sekali sahaja setahun.</p>
            </article>
            <article class="box p-5">
                <h3 class="text-sm font-bold uppercase tracking-wide text-zinc-600">Langkah 4</h3>
                <p class="mt-2 text-base font-semibold text-[color:var(--brand-forest)]">Simpan Rekod Bayaran</p>
                <p class="mt-2 text-sm text-zinc-600">Status bayaran boleh dirujuk semula pada bila-bila masa.</p>
            </article>
        </section>

        <section class="mt-5 grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="box p-5 sm:p-6">
                <h2 class="section-title text-lg font-bold text-[color:var(--brand-forest)]">Carian Terperinci</h2>
                <p class="mt-1 text-sm text-zinc-600">Carian menyeluruh berdasarkan nama, kelas, dan telefon ibu bapa.</p>

                <form action="{{ route('parent.search') }}" method="GET" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="student_keyword" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">NAMA</label>
                        <input id="student_keyword" name="student_keyword" type="text" class="search-input w-full rounded-xl text-sm focus:ring-0" placeholder="Contoh : Muhammad">
                    </div>
                    <div>
                        <label for="class_name" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">KELAS</label>
                        <select id="class_name" name="class_name" class="search-input w-full rounded-xl pr-9 text-sm focus:ring-0">
                            <option value="">Semua kelas</option>
                            @foreach (($classOptions ?? collect()) as $className)
                                <option value="{{ $className }}">{{ $className }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="contact" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">No Telefon Ibu Bapa</label>
                        <input id="contact" name="contact" type="text" class="search-input w-full rounded-xl text-sm focus:ring-0" placeholder="Contoh: 0123">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-700">Cari Sekarang</button>
                    </div>
                </form>
            </div>

            <div class="box p-5 sm:p-6">
                <h2 class="section-title text-lg font-bold text-[color:var(--brand-forest)]">Maklumat & Bantuan</h2>

                <div class="mt-4 space-y-3">
                    <div class="rounded-lg border border-zinc-200 bg-[color:var(--brand-soft)] p-3">
                        <p class="text-sm text-zinc-700">Sumbangan RM100 setahun bagi setiap keluarga amat membantu PIBG dalam menjayakan pelbagai inisiatif untuk kebaikan anak-anak kita.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-2 text-sm">
                    <a href="https://wa.me/60136454001" target="_blank" class="block rounded-lg border border-zinc-200 bg-white px-3 py-2 font-medium text-zinc-800 transition hover:bg-zinc-50">Pn. Mariam : 013 6454 001</a>
                    <a href="https://wa.me/60123103205" target="_blank" class="block rounded-lg border border-zinc-200 bg-white px-3 py-2 font-medium text-zinc-800 transition hover:bg-zinc-50">En. Haron : 012 310 3205</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-200/80 bg-white/80">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-1 px-4 py-4 text-center text-xs text-zinc-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>Portal Yuran & Sumbangan PIBG SK Sri Petaling 2026</p>
            <p>Demi kemudahan Semakan keluarga & bayaran yuran tahunan</p>
        </div>
    </footer>

    @fluxScripts
    <x-toaster-hub />
    @if (($recentPaymentToasts ?? collect())->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const desktopMessages = @js(($recentPaymentToasts ?? collect())->values()->all());
                const mobileMessages = desktopMessages.map((message) =>
                    String(message).replace('Yuran + Sumbangan Tambahan', 'Yuran + Sumbangan')
                );

                const isMobile = window.matchMedia('(max-width: 640px)').matches;
                const messages = isMobile ? mobileMessages : desktopMessages;
                if (!Array.isArray(messages) || messages.length === 0) {
                    return;
                }

                const pushToast = (message) => {
                    if (window.Toaster && typeof window.Toaster.success === 'function') {
                        window.Toaster.success(message);
                    }
                };

                const shouldPause = () => {
                    if (document.hidden) {
                        return true;
                    }

                    const active = document.activeElement;
                    if (!active) {
                        return false;
                    }

                    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName);
                };

                let index = 0;
                const intervalMs = isMobile ? 9000 : 5500;
                const initialDelayMs = isMobile ? 1200 : 500;

                const cycle = () => {
                    if (shouldPause()) {
                        return;
                    }

                    pushToast(messages[index]);
                    index = (index + 1) % messages.length;
                };

                window.setTimeout(cycle, initialDelayMs);
                window.setInterval(cycle, intervalMs);
            });
        </script>
    @endif
@RegisterServiceWorkerScript
</body>
</html>
