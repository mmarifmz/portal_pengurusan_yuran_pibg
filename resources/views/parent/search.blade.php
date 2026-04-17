<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $title = 'Carian Murid | Portal Yuran PIBG SK Sri Petaling';
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
            background: linear-gradient(135deg, rgba(43, 125, 86, 0.12), rgba(229, 179, 56, 0.12));
        }

        .box {
            border: 1px solid #e5e7eb;
            background: rgba(255, 255, 255, 0.94);
            border-radius: 1rem;
            box-shadow: 0 8px 24px rgba(30, 41, 59, 0.07);
        }

        .section-title {
            letter-spacing: -0.01em;
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

        .masked-name {
            position: relative;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;
            letter-spacing: 0.03em;
        }

        .masked-name::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0) 0%, rgba(24, 74, 52, 0.05) 100%),
                repeating-linear-gradient(
                    90deg,
                    rgba(31, 42, 36, 0.08) 0,
                    rgba(31, 42, 36, 0.08) 4px,
                    rgba(31, 42, 36, 0.16) 4px,
                    rgba(31, 42, 36, 0.16) 8px
                );
            mix-blend-mode: multiply;
            opacity: 0.35;
            pointer-events: none;
            border-radius: 0.35rem;
        }
    </style>
</head>
<body class="portal-bg min-h-screen text-[color:var(--brand-ink)] antialiased">
    @php
        $classPillStyle = function (?string $className): string {
            $label = strtoupper(trim((string) $className));
            $theme = preg_replace('/^[0-9]+\s*/', '', $label) ?: $label;

            return match ($theme) {
                'AKASIA' => 'background:#A8E6CF;border-color:#A8E6CF;color:#25523d;',
                'ALAMANDA' => 'background:#FFD3B6;border-color:#FFD3B6;color:#7f4725;',
                'ANGGERIK' => 'background:#D5AAFF;border-color:#D5AAFF;color:#5a3388;',
                'ANGSANA' => 'background:#A0E7E5;border-color:#A0E7E5;color:#1f5c66;',
                'AZALEA' => 'background:#FFB7C5;border-color:#FFB7C5;color:#7a3e4d;',
                default => 'background:#f4f4f5;border-color:#e4e4e7;color:#52525b;',
            };
        };
    @endphp

    <header class="border-b border-zinc-200/80 bg-white/85 backdrop-blur-sm">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/sksp-logo.png') }}" alt="Logo SK Sri Petaling" class="h-12 w-12 rounded-full border border-zinc-200 bg-white p-1 shadow-sm sm:h-14 sm:w-14" />
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-zinc-500 sm:text-xs">Portal Rasmi</p>
                    <p class="text-sm font-bold text-[color:var(--brand-forest)] sm:text-base">Yuran &amp; Sumbangan PIBG SK Sri Petaling</p>
                </div>
            </a>

            <div class="grid w-full grid-cols-2 gap-2 sm:w-auto sm:flex sm:gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg border border-[color:var(--brand-green)] px-3 py-2 text-center text-xs font-semibold text-[color:var(--brand-green)] transition hover:bg-[color:var(--brand-green)] hover:text-white sm:text-sm">Back to Dashboard</a>
                @else
                    <a href="{{ route('parent.login.form') }}" class="rounded-lg border border-[color:var(--brand-green)] px-3 py-2 text-center text-xs font-semibold text-[color:var(--brand-green)] transition hover:bg-[color:var(--brand-green)] hover:text-white sm:text-sm">Log Masuk Penjaga</a>
                @endauth
                <a href="{{ route('login') }}" class="rounded-lg bg-[color:var(--brand-forest)] px-3 py-2 text-center text-xs font-semibold text-white transition hover:opacity-90 sm:text-sm">Guru / Admin</a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <section class="hero-strip box p-4 sm:p-5">
            <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="inline-flex items-center rounded-full bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-[color:var(--brand-forest)] ring-1 ring-[color:var(--brand-gold)]/60 sm:text-xs">
                            Carian Awam Parent
                        </p>
                        <a href="{{ route('home') }}" class="inline-flex items-center rounded-full border border-zinc-300 bg-white px-3 py-1 text-[11px] font-semibold text-zinc-700 transition hover:bg-zinc-50 sm:text-xs">
                            Portal Utama
                        </a>
                        <a href="{{ route('parent.login.form') }}" class="inline-flex items-center rounded-full bg-[color:var(--brand-green)] px-3 py-1 text-[11px] font-semibold text-white transition hover:bg-[color:var(--brand-forest)] sm:text-xs">
                            Login TAC
                        </a>
                    </div>
                    <h1 class="section-title mt-3 text-2xl font-extrabold tracking-tight text-zinc-900 sm:text-3xl">
                        Search Rekod Anak
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-zinc-700">
                        Cari ikut nama murid, nombor murid, kelas, atau nombor telefon penjaga. Hasil carian dipaparkan ikut kumpulan keluarga untuk semakan yang lebih ringkas.
                    </p>
                </div>

                <form method="GET" action="{{ route('parent.search') }}" class="box p-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="student_keyword" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Nama</label>
                            <input id="student_keyword" name="student_keyword" type="text" value="{{ old('student_keyword', request('student_keyword')) }}" class="search-input w-full rounded-xl text-sm focus:ring-0" placeholder="Contoh: NUR AISHA">
                            @error('student_keyword')
                                <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="class_name" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Kelas</label>
                            <select id="class_name" name="class_name" class="search-input w-full rounded-xl pr-9 text-sm focus:ring-0">
                                <option value="">Semua kelas</option>
                                @foreach (($availableClasses ?? collect()) as $className)
                                    <option value="{{ $className }}" @selected(request('class_name') === $className)>{{ $className }}</option>
                                @endforeach
                            </select>
                            @error('class_name')
                                <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="contact" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">No Telefon Ibu Bapa</label>
                            <input id="contact" name="contact" type="text" value="{{ old('contact', request('contact')) }}" class="search-input w-full rounded-xl text-sm focus:ring-0" placeholder="Masukkan nombor penuh">
                            @error('contact')
                                <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <input type="hidden" name="visible_limit" value="{{ $visibleLimit }}">

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="submit" class="rounded-lg bg-[color:var(--brand-forest)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[color:var(--brand-green)]">Search</button>
                        <a href="{{ route('parent.search') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50">Reset</a>
                    </div>
                </form>
            </div>
        </section>

        @if ($hasSearched)
            <section class="mt-5 box overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-zinc-200 bg-zinc-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <h2 class="section-title text-lg font-bold text-[color:var(--brand-forest)]">Hasil Carian</h2>
                        <p class="mt-1 text-sm text-zinc-600">
                            {{ number_format($totalFamilyResults) }} kumpulan keluarga ditemui. Paparan bermula dengan 20 kumpulan pertama.
                        </p>
                    </div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        Dipaparkan {{ number_format($familyResults->count()) }} / {{ number_format($totalFamilyResults) }}
                    </div>
                </div>

                <div class="divide-y divide-zinc-200">
                    @forelse ($familyResults as $family)
                        <article class="p-4 sm:p-5" loading="lazy">
                            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white">
                                <div class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_220px]">
                                    <div class="p-4 sm:p-5">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-full bg-[color:var(--brand-soft)] px-3 py-1 text-xs font-semibold text-[color:var(--brand-forest)]">
                                            {{ $family['family_code'] ?? 'Belum ada family code' }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-600">
                                            {{ $family['student_count'] }} anak
                                        </span>
                                        @foreach ($family['classes'] as $className)
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold" style="{{ $classPillStyle($className) }}">
                                                {{ $className }}
                                            </span>
                                        @endforeach
                                    </div>

                                            @if ($family['masked_parent_phone'])
                                                <div class="text-right">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Phone berdaftar</p>
                                                    <p class="mt-1 text-sm font-bold text-[color:var(--brand-forest)]">{{ $family['masked_parent_phone'] }}</p>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mt-4 grid gap-3">
                                            @foreach ($family['students'] as $student)
                                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-3 py-3 text-sm">
                                                    <div class="min-w-0">
                                                        <div class="masked-name inline-flex max-w-full items-center rounded-md bg-zinc-100 px-2.5 py-1 font-semibold text-zinc-900">
                                                            <span class="truncate">{{ $student['masked_name'] }}</span>
                                                        </div>
                                                    </div>
                                                    <p class="mt-2 text-sm font-medium text-zinc-900">
                                                        {{ $student['class_name'] ?: '-' }} / <span class="font-mono">{{ $student['student_no'] }}</span>
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="border-t border-zinc-200 lg:border-t-0 lg:border-l lg:border-zinc-200">
                                        @if ($family['billing'])
                                            @if (auth()->user()?->isParent())
                                                @php
                                                    $buttonLabel = $family['billing']->outstanding_amount <= 0
                                                        ? 'Log Masuk Portal Ibu Bapa'
                                                        : ($family['has_registered_phone'] ? 'Log Masuk & Bayar Yuran' : 'Daftar Masuk & Bayar Yuran');
                                                    $buttonClasses = $family['billing']->outstanding_amount <= 0
                                                        ? 'bg-sky-600 hover:bg-sky-700'
                                                        : ($family['has_registered_phone'] ? 'bg-[color:var(--brand-green)] hover:bg-[color:var(--brand-forest)]' : 'bg-orange-500 hover:bg-orange-600');
                                                @endphp
                                                <a
                                                    href="{{ route('parent.payments.checkout', $family['billing']) }}"
                                                    class="flex h-full min-h-32 w-full items-center justify-center px-5 py-6 text-center text-base font-bold text-white transition {{ $buttonClasses }}"
                                                >
                                                    {{ $buttonLabel }}
                                                </a>
                                            @else
                                                @php
                                                    $buttonLabel = $family['billing']->outstanding_amount <= 0
                                                        ? 'Log Masuk Portal Ibu Bapa'
                                                        : ($family['has_registered_phone'] ? 'Log Masuk & Bayar Yuran' : 'Daftar Masuk & Bayar Yuran');
                                                    $buttonClasses = $family['billing']->outstanding_amount <= 0
                                                        ? 'bg-sky-600 hover:bg-sky-700'
                                                        : ($family['has_registered_phone'] ? 'bg-[color:var(--brand-green)] hover:bg-[color:var(--brand-forest)]' : 'bg-orange-500 hover:bg-orange-600');
                                                @endphp
                                                <a
                                                    href="{{ route('parent.login.form', ['billing' => $family['billing']->id, 'phone' => $family['parent_phone']]) }}"
                                                    class="flex h-full min-h-32 w-full items-center justify-center px-5 py-6 text-center text-base font-bold text-white transition {{ $buttonClasses }}"
                                                >
                                                    {{ $buttonLabel }}
                                                </a>
                                            @endif
                                        @else
                                            <div class="flex h-full min-h-32 items-center justify-center bg-zinc-50 px-5 py-6 text-center text-sm font-semibold text-zinc-500">
                                                Tiada tindakan
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-10 text-center text-zinc-500">No matching student found.</div>
                    @endforelse
                </div>

                @if ($totalFamilyResults > $familyResults->count())
                    <div class="border-t border-zinc-200 bg-zinc-50 px-4 py-4 text-center sm:px-5">
                        <a
                            href="{{ route('parent.search', array_filter([
                                'student_keyword' => request('student_keyword'),
                                'class_name' => request('class_name'),
                                'contact' => request('contact'),
                                'visible_limit' => min($visibleLimit + 20, 200),
                            ], fn ($value) => $value !== null && $value !== '')) }}"
                            class="inline-flex rounded-lg border border-[color:var(--brand-green)] bg-white px-4 py-2 text-sm font-semibold text-[color:var(--brand-green)] transition hover:bg-[color:var(--brand-green)] hover:text-white"
                        >
                            Load more ({{ min(20, $totalFamilyResults - $familyResults->count()) }} lagi)
                        </a>
                    </div>
                @endif
            </section>
        @endif
    </main>

    <footer class="border-t border-zinc-200/80 bg-white/80">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-1 px-4 py-4 text-center text-xs text-zinc-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>Portal Yuran &amp; Sumbangan PIBG SK Sri Petaling 2026</p>
            <p>Demi kemudahan semakan keluarga &amp; bayaran yuran tahunan</p>
        </div>
    </footer>

    @fluxScripts
</body>
</html>
