<x-layouts::app :title="__('Class Payment Progress')">
    @php
        $queueCountCards = [
            ['label' => 'Pending', 'value' => $queueDashboard['pending'] ?? 0],
            ['label' => 'Sending', 'value' => $queueDashboard['sending'] ?? 0],
            ['label' => 'Sent Today', 'value' => $queueDashboard['sent_today'] ?? 0],
            ['label' => 'Failed Today', 'value' => $queueDashboard['failed_today'] ?? 0],
        ];
        $classSections = $canManageWhatsapp
            ? collect([
                [
                    'key' => 'all-classes',
                    'title' => 'Semua Kelas',
                    'description' => 'Semua kelas dipaparkan dengan butiran penuh untuk semakan dan tindakan susulan.',
                    'rows' => $leaderboardRows,
                ],
            ])
            : collect([
                [
                    'key' => 'my-class',
                    'title' => 'Kelas Saya',
                    'description' => 'Kelas tugasan cikgu dipaparkan di bahagian atas dan dibuka terus untuk semakan pantas.',
                    'rows' => $myClassRows,
                ],
                [
                    'key' => 'other-classes',
                    'title' => 'Senarai Kelas Lain',
                    'description' => 'Kelas lain juga boleh dibuka untuk melihat senarai telah bayar dan belum bayar.',
                    'rows' => $otherClassRows,
                ],
            ])->filter(fn (array $section) => $section['rows']->isNotEmpty())->values();
    @endphp

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">{{ $canManageWhatsapp ? 'SUPER ADMIN VIEW' : 'TEACHER DASHBOARD' }}</p>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900">Pemantauan Kutipan Yuran &amp; Sumbangan PIBG Mengikut Kelas</h1>
                    <p class="mt-1 max-w-3xl text-sm text-zinc-600">Pantau status bayaran PIBG, sumbangan tambahan, baki tertunggak dan prestasi kutipan setiap kelas bagi sesi {{ $billingYear }}.</p>
                    <div class="mt-3 space-y-2 text-sm text-zinc-600">
                        <p>📌 Kelas sendiri akan dipaparkan di bahagian atas sebagai <span class="font-semibold italic text-zinc-800">Kelas Saya</span> untuk semakan pantas.</p>
                        <p>📂 Kelas lain juga boleh dibuka untuk melihat senarai telah bayar dan belum bayar.</p>
                        <p>📊 Data dikemaskini secara masa nyata berdasarkan rekod pembayaran semasa.</p>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-end">
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Tapis Tahun
                        <select id="yearLevelFilter" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 sm:w-52">
                            <option value="all">Semua Tahun</option>
                            @foreach ($yearLevelOptions as $yearLevel)
                                <option value="{{ $yearLevel }}">Tahun {{ $yearLevel }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if ($canManageWhatsapp)
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ $queueDashboardUrl }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100">
                                View WhatsApp Queue
                            </a>
                            <button
                                type="button"
                                id="batchWhatsappPreviewButton"
                                class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700"
                            >
                                Blast WhatsApp Report to All Class Teachers
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            @if ($canManageWhatsapp)
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($queueCountCards as $card)
                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $card['label'] }}</p>
                            <p class="mt-1 text-xl font-bold text-zinc-900">{{ $card['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($canManageWhatsapp && (! empty($queueDashboard['pending_warning']) || ! empty($queueDashboard['processor_warning'])))
                <div class="mt-4 space-y-2">
                    @if (! empty($queueDashboard['pending_warning']))
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            {{ $queueDashboard['pending_warning'] }}
                        </div>
                    @endif
                    @if (! empty($queueDashboard['processor_warning']))
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ $queueDashboard['processor_warning'] }}
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <div id="classProgressSections" class="space-y-6">
            @forelse ($classSections as $section)
                <section
                    data-class-section="1"
                    data-section-key="{{ $section['key'] }}"
                    class="space-y-4"
                >
                    <div class="flex flex-col gap-1">
                        <h2 class="text-lg font-bold tracking-tight text-zinc-900">{{ $section['title'] }}</h2>
                        <p class="text-sm text-zinc-500">{{ $section['description'] }}</p>
                    </div>

                    <div class="grid gap-4">
                        @foreach ($section['rows'] as $row)
                            <article
                                data-class-card="1"
                                data-year-level="{{ $row['year_level'] ?? 'other' }}"
                                data-class-name="{{ $row['class_name'] }}"
                                class="overflow-hidden rounded-2xl border bg-white shadow-sm {{ $row['is_my_class'] ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-zinc-200' }}"
                            >
                                <div class="p-5">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="space-y-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-xl font-bold tracking-tight text-zinc-900">{{ $row['class_name'] }}</h3>
                                                @foreach ($row['status_badges'] as $badge)
                                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $badge['classes'] }}">
                                                        {{ $badge['label'] }}
                                                    </span>
                                                @endforeach
                                            </div>
                                            <p class="text-sm text-zinc-500">Guru Kelas: <span class="font-semibold text-zinc-700">{{ $row['teacher_name'] }}</span></p>
                                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Jumlah Keluarga</p>
                                                    <p class="mt-1 text-lg font-bold text-zinc-900">{{ $row['total_families'] }}</p>
                                                </div>
                                                <div class="rounded-2xl border border-zinc-200 bg-emerald-50 px-4 py-3">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Selesai</p>
                                                    <p class="mt-1 text-lg font-bold text-emerald-700">{{ $row['fully_paid_families'] }}</p>
                                                </div>
                                                <div class="rounded-2xl border border-zinc-200 bg-amber-50 px-4 py-3">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Sebahagian</p>
                                                    <p class="mt-1 text-lg font-bold text-amber-700">{{ $row['partial_paid_families'] }}</p>
                                                </div>
                                                <div class="rounded-2xl border border-zinc-200 bg-rose-50 px-4 py-3">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Belum Bayar</p>
                                                    <p class="mt-1 text-lg font-bold text-rose-700">{{ $row['unpaid_families'] }}</p>
                                                </div>
                                                <div class="rounded-2xl border border-zinc-200 bg-sky-50 px-4 py-3">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">Completion</p>
                                                    <p class="mt-1 text-lg font-bold text-sky-700">{{ number_format((float) $row['completion_percent'], 2) }}%</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="w-full max-w-sm space-y-3 lg:ml-6">
                                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Jumlah Kutipan</span>
                                                    <span class="text-lg font-bold text-zinc-900">RM {{ number_format((float) $row['jumlah_kutipan'], 2) }}</span>
                                                </div>
                                                <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                                                    <span class="text-zinc-500">Yuran PIBG</span>
                                                    <span class="font-semibold text-emerald-700">RM {{ number_format((float) $row['yuran_collected'], 2) }}</span>
                                                </div>
                                                <div class="mt-1 flex items-center justify-between gap-3 text-sm">
                                                    <span class="text-zinc-500">Sumbangan Tambahan</span>
                                                    <span class="font-semibold {{ (float) $row['sumbangan_tambahan_collected'] > 0 ? 'text-cyan-700' : 'text-zinc-400' }}">RM {{ number_format((float) $row['sumbangan_tambahan_collected'], 2) }}</span>
                                                </div>
                                                <div class="mt-1 flex items-center justify-between gap-3 text-sm">
                                                    <span class="text-zinc-500">Baki Tertunggak</span>
                                                    <span class="font-semibold {{ (float) $row['baki_tertunggak'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">RM {{ number_format((float) $row['baki_tertunggak'], 2) }}</span>
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    data-toggle-details="{{ $row['class_name'] }}"
                                                    @if (! $canManageWhatsapp && $row['is_my_class']) data-expand-default="1" @endif
                                                    class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100"
                                                >
                                                    View Details
                                                </button>

                                                @if ($canManageWhatsapp)
                                                    <button
                                                        type="button"
                                                        data-preview-class="{{ $row['class_name'] }}"
                                                        class="inline-flex shrink-0 items-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                                    >
                                                        WhatsApp Guru
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="class-detail-{{ \Illuminate\Support\Str::slug($row['class_name']) }}" data-detail-panel="{{ $row['class_name'] }}" class="hidden border-t border-zinc-200 bg-zinc-50/60 px-5 py-5">
                                    <div class="rounded-2xl border border-zinc-200 bg-white px-4 py-4 text-sm text-zinc-500">
                                        Memuatkan butiran kelas...
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 shadow-sm">
                    Tiada data kelas untuk sesi ini.
                </div>
            @endforelse
        </div>

        <div id="classProgressEmpty" class="hidden rounded-2xl border border-zinc-200 bg-white p-6 text-center text-sm text-zinc-500 shadow-sm">
            Tiada kelas untuk tapisan ini.
        </div>
    </div>

    <div id="whatsappToast" class="pointer-events-none fixed right-4 top-4 z-[10001] hidden max-w-sm rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-2xl"></div>

    @if ($canManageWhatsapp)
    <div id="teacherWhatsappPreviewModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/50 px-4 py-6">
        <div class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-zinc-200 px-5 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Class Report Preview</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Preview WhatsApp Message to Guru Kelas</h3>
                </div>
                <button type="button" data-close-preview-modal class="rounded-full border border-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-900">Tutup</button>
            </div>
            <div class="max-h-[calc(90vh-140px)] overflow-y-auto px-5 py-5">
                <div id="teacherWhatsappPreviewLoading" class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-6 text-sm text-zinc-500">
                    Memuatkan preview WhatsApp...
                </div>

                <div id="teacherWhatsappPreviewContent" class="hidden space-y-5">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Kelas</p>
                            <p id="previewClassName" class="mt-1 text-lg font-semibold text-zinc-900"></p>
                        </div>
                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Guru</p>
                            <p id="previewTeacherName" class="mt-1 text-lg font-semibold text-zinc-900"></p>
                        </div>
                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">WhatsApp</p>
                            <p id="previewTeacherPhone" class="mt-1 text-lg font-semibold text-zinc-900"></p>
                        </div>
                    </div>

                    <div id="previewEligibilityWarnings" class="space-y-2"></div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" id="previewQueueCounts"></div>

                    <div class="rounded-2xl border border-zinc-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900">Class Stats</h4>
                                <p class="text-xs text-zinc-500">These values are generated from the same leaderboard reporting dataset.</p>
                            </div>
                            <a id="previewQueuePageLink" href="{{ $queueDashboardUrl }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-600">Open Queue Page</a>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" id="previewStatsGrid"></div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-3" id="previewMessageParts"></div>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-zinc-200 px-5 py-4">
                <p id="teacherWhatsappPreviewFooter" class="text-xs text-zinc-500">Preview the message structure carefully before queueing.</p>
                <button
                    type="button"
                    id="queueTeacherWhatsappButton"
                    class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                    disabled
                >
                    Queue WhatsApp Report
                </button>
            </div>
        </div>
    </div>

    <div id="batchWhatsappPreviewModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/50 px-4 py-6">
        <div class="max-h-[90vh] w-full max-w-6xl overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-zinc-200 px-5 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Batch Blast</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Preview Batch WhatsApp Blast</h3>
                </div>
                <button type="button" data-close-batch-modal class="rounded-full border border-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-900">Tutup</button>
            </div>
            <div class="max-h-[calc(90vh-140px)] overflow-y-auto px-5 py-5">
                <div id="batchWhatsappPreviewLoading" class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-6 text-sm text-zinc-500">
                    Menjana preview batch WhatsApp...
                </div>

                <div id="batchWhatsappPreviewContent" class="hidden space-y-5">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" id="batchSummaryGrid"></div>
                    <div id="batchQueueWarnings" class="space-y-2"></div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" id="batchQueueCounts"></div>

                    <div class="rounded-2xl border border-zinc-200 bg-white p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900">Class Preview Cards</h4>
                                <p class="text-xs text-zinc-500">Select the eligible classes you want to queue in this blast.</p>
                            </div>
                            <a href="{{ $queueDashboardUrl }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-600">Open Queue Page</a>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3" id="batchPreviewCards"></div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-zinc-200 px-5 py-4">
                <p id="batchPreviewFooter" class="text-xs text-zinc-500">Only eligible classes will be queued. Recently queued classes can be forced after confirmation.</p>
                <button
                    type="button"
                    id="queueBatchWhatsappButton"
                    class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                    disabled
                >
                    Queue WhatsApp Blast
                </button>
            </div>
        </div>
    </div>
    @endif

    <script>
        (function () {
            const billingYear = @json($billingYear);
            const canManageWhatsapp = @json($canManageWhatsapp);
            const rowButtons = Array.from(document.querySelectorAll('[data-preview-class]'));
            const batchButton = document.getElementById('batchWhatsappPreviewButton');
            const previewModal = document.getElementById('teacherWhatsappPreviewModal');
            const batchModal = document.getElementById('batchWhatsappPreviewModal');
            const previewLoading = document.getElementById('teacherWhatsappPreviewLoading');
            const previewContent = document.getElementById('teacherWhatsappPreviewContent');
            const batchLoading = document.getElementById('batchWhatsappPreviewLoading');
            const batchContent = document.getElementById('batchWhatsappPreviewContent');
            const queueTeacherButton = document.getElementById('queueTeacherWhatsappButton');
            const queueBatchButton = document.getElementById('queueBatchWhatsappButton');
            const toast = document.getElementById('whatsappToast');
            const filter = document.getElementById('yearLevelFilter');
            const rows = Array.from(document.querySelectorAll('[data-class-card="1"]'));
            const sections = Array.from(document.querySelectorAll('[data-class-section="1"]'));
            const emptyState = document.getElementById('classProgressEmpty');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const detailUrlTemplate = @json(route('teacher.class-progress.details', ['class' => '__CLASS__']));
            const previewUrlTemplate = canManageWhatsapp ? @json(route('admin.classes.whatsapp-preview', ['class' => '__CLASS__'])) : null;
            const queueUrlTemplate = canManageWhatsapp ? @json(route('admin.classes.whatsapp-queue', ['class' => '__CLASS__'])) : null;
            const batchPreviewUrl = canManageWhatsapp ? @json(route('admin.classes.whatsapp-batch-preview')) : null;
            const batchQueueUrl = canManageWhatsapp ? @json(route('admin.classes.whatsapp-batch-queue')) : null;

            let currentPreview = null;
            let currentBatchPreview = null;
            let defaultClassExpanded = false;

            function buildClassRoute(template, className) {
                return template.replace('__CLASS__', encodeURIComponent(className));
            }

            function showToast(message, tone = 'success') {
                if (!toast) {
                    return;
                }

                toast.textContent = message;
                toast.classList.remove('hidden', 'border-emerald-200', 'border-rose-200', 'text-zinc-800');
                toast.classList.add('block');
                toast.classList.add(tone === 'error' ? 'border-rose-200' : 'border-emerald-200');

                window.clearTimeout(showToast.timeoutId);
                showToast.timeoutId = window.setTimeout(() => {
                    toast.classList.add('hidden');
                }, 3200);
            }

            function openModal(modal) {
                modal?.classList.remove('hidden');
                modal?.classList.add('flex');
            }

            function closeModal(modal) {
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
            }

            function setLoadingState(loadingNode, contentNode, isLoading) {
                if (!loadingNode || !contentNode) {
                    return;
                }

                loadingNode.classList.toggle('hidden', !isLoading);
                contentNode.classList.toggle('hidden', isLoading);
            }

            function queueCountCardsHtml(queueDashboard) {
                const cards = [
                    ['Pending', queueDashboard.pending ?? 0],
                    ['Sending', queueDashboard.sending ?? 0],
                    ['Sent Today', queueDashboard.sent_today ?? 0],
                    ['Failed Today', queueDashboard.failed_today ?? 0],
                ];

                return cards.map(([label, value]) => `
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">${label}</p>
                        <p class="mt-1 text-lg font-bold text-zinc-900">${value}</p>
                    </div>
                `).join('');
            }

            function renderWarningBlocks(target, warnings, tone = 'rose') {
                if (!target) {
                    return;
                }

                if (!warnings.length) {
                    target.innerHTML = '';
                    return;
                }

                const toneClasses = tone === 'amber'
                    ? 'border-amber-200 bg-amber-50 text-amber-800'
                    : 'border-rose-200 bg-rose-50 text-rose-700';

                target.innerHTML = warnings.map((warning) => `
                    <div class="rounded-xl border px-4 py-3 text-sm ${toneClasses}">
                        ${warning}
                    </div>
                `).join('');
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderWhatsappText(value) {
                let formatted = escapeHtml(value);

                formatted = formatted.replace(/```([\s\S]*?)```/g, '<code class="rounded bg-black/10 px-1 py-0.5 text-[11px]">$1</code>');
                formatted = formatted.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
                formatted = formatted.replace(/_([^_\n]+)_/g, '<em>$1</em>');
                formatted = formatted.replace(/\n/g, '<br>');

                return formatted;
            }

            function formatCurrency(value) {
                return `RM ${Number(value || 0).toFixed(2)}`;
            }

            function renderPaidEntries(entries) {
                if (!entries.length) {
                    return `
                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4 text-sm text-zinc-500">
                            Tiada rekod bayaran semasa untuk kelas ini.
                        </div>
                    `;
                }

                return entries.map((entry, index) => `
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4 ${entry.is_partial ? 'border-amber-200 bg-amber-50/70' : ''}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-zinc-900">${index + 1}. ${escapeHtml(entry.student_name_display)}</p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">
                                        ${formatCurrency(entry.paid_amount)}
                                    </span>
                                    ${entry.donation_total > 0 ? `
                                        <span class="inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-2.5 py-1 font-semibold text-cyan-700">
                                            Sumbangan ${formatCurrency(entry.donation_total)}
                                        </span>
                                    ` : ''}
                                    ${entry.is_partial ? `
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">
                                            Sebahagian
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                            ${entry.latest_payment_at ? `<span class="text-xs font-medium text-zinc-500">${escapeHtml(entry.latest_payment_at)}</span>` : ''}
                        </div>
                    </div>
                `).join('');
            }

            function renderUnpaidEntries(entries) {
                if (!entries.length) {
                    return `
                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4 text-sm text-zinc-500">
                            Tiada keluarga tertunggak untuk kelas ini.
                        </div>
                    `;
                }

                return entries.map((entry, index) => `
                    <div class="rounded-2xl border px-4 py-4 ${entry.previous_year_paid ? 'border-emerald-200 bg-emerald-50/70' : 'border-zinc-200 bg-zinc-50'}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold ${entry.previous_year_paid ? 'text-emerald-700' : 'text-zinc-900'}">${index + 1}. ${escapeHtml(entry.student_name_display)}</p>
                                    ${entry.previous_year_badge ? `
                                        <span
                                            title="${escapeHtml(entry.previous_year_tooltip || '')}"
                                            class="inline-flex shrink-0 items-center rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium leading-none text-blue-700"
                                        >
                                            ${escapeHtml(entry.previous_year_badge)}
                                        </span>
                                    ` : ''}
                                </div>
                                <div class="mt-2 space-y-1 text-xs text-zinc-600">
                                    ${entry.parent_name ? `<p>Penjaga: <span class="font-medium text-zinc-700">${escapeHtml(entry.parent_name)}</span></p>` : ''}
                                    ${entry.parent_phone ? `<p>Telefon: <span class="font-medium text-zinc-700">${escapeHtml(entry.parent_phone)}</span></p>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            function renderClassDetails(details) {
                const summary = details.summary || {};
                const detailWarnings = details.summary_only
                    ? `
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            ${escapeHtml(details.summary_only_message || 'Maklumat terperinci tidak tersedia.')}
                        </div>
                    `
                    : '';

                return `
                    <div class="space-y-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-zinc-900">Class: ${escapeHtml(summary.class_name || '-')}</p>
                                <p class="mt-1 text-sm text-zinc-500">Teacher: <span class="font-medium text-zinc-700">${escapeHtml(summary.teacher_name || '-')}</span></p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                <span class="inline-flex rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-zinc-700">Completion ${Number(summary.completion_percent || 0).toFixed(2)}%</span>
                                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">Selesai ${summary.fully_paid_families ?? 0}</span>
                                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700">Sebahagian ${summary.partial_paid_families ?? 0}</span>
                                <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-rose-700">Belum ${summary.unpaid_families ?? 0}</span>
                            </div>
                        </div>
                        ${detailWarnings}
                        ${details.summary_only ? '' : `
                            <div class="grid gap-4 xl:grid-cols-2">
                                <section class="rounded-2xl border border-zinc-200 bg-white p-4">
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <h3 class="text-sm font-semibold text-zinc-900">✅ Telah Bayar</h3>
                                        <span class="text-xs font-semibold text-zinc-500">${(details.paid_entries || []).length} keluarga</span>
                                    </div>
                                    <div class="space-y-3">
                                        ${renderPaidEntries(details.paid_entries || [])}
                                    </div>
                                </section>
                                <section class="rounded-2xl border border-zinc-200 bg-white p-4">
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <h3 class="text-sm font-semibold text-zinc-900">⏳ Belum Bayar</h3>
                                        <span class="text-xs font-semibold text-zinc-500">${(details.unpaid_entries || []).length} keluarga</span>
                                    </div>
                                    <div class="space-y-3">
                                        ${renderUnpaidEntries(details.unpaid_entries || [])}
                                    </div>
                                </section>
                            </div>
                        `}
                    </div>
                `;
            }

            async function toggleClassDetails(button) {
                const className = button.getAttribute('data-toggle-details');
                const panel = className ? document.querySelector(`[data-detail-panel="${CSS.escape(className)}"]`) : null;

                if (!className || !panel) {
                    return;
                }

                const isHidden = panel.classList.contains('hidden');
                if (!isHidden) {
                    panel.classList.add('hidden');
                    button.textContent = 'View Details';
                    return;
                }

                panel.classList.remove('hidden');
                button.textContent = 'Hide Details';

                if (panel.dataset.loaded === 'true') {
                    return;
                }

                panel.innerHTML = `
                    <div class="rounded-2xl border border-zinc-200 bg-white px-4 py-4 text-sm text-zinc-500">
                        Memuatkan butiran kelas...
                    </div>
                `;

                try {
                    const details = await fetchJson(`${buildClassRoute(detailUrlTemplate, className)}?billing_year=${billingYear}`);
                    panel.innerHTML = renderClassDetails(details);
                    panel.dataset.loaded = 'true';
                } catch (error) {
                    panel.innerHTML = `
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700">
                            ${escapeHtml(error.data?.message || error.message || 'Unable to load class details.')}
                        </div>
                    `;
                }
            }

            function renderPreview(preview) {
                currentPreview = preview;
                document.getElementById('previewClassName').textContent = preview.class_name || '-';
                document.getElementById('previewTeacherName').textContent = preview.teacher_name || '-';
                document.getElementById('previewTeacherPhone').textContent = preview.teacher_phone || 'Tiada nombor WhatsApp';
                document.getElementById('previewQueuePageLink').setAttribute('href', preview.queue_page_url || '{{ $queueDashboardUrl }}');

                const queueWarnings = [];
                if (preview.queue_dashboard?.pending_warning) {
                    queueWarnings.push(preview.queue_dashboard.pending_warning);
                }
                if (preview.queue_dashboard?.processor_warning) {
                    queueWarnings.push(preview.queue_dashboard.processor_warning);
                }
                if (Array.isArray(preview.queue_eligibility?.errors)) {
                    queueWarnings.push(...preview.queue_eligibility.errors);
                }
                if (preview.queue_eligibility?.duplicate_warning) {
                    queueWarnings.push(preview.queue_eligibility.duplicate_warning);
                }

                renderWarningBlocks(document.getElementById('previewEligibilityWarnings'), queueWarnings, preview.queue_eligibility?.ready ? 'amber' : 'rose');

                const stats = preview.class_stats || {};
                const statsCards = [
                    ['Jumlah Murid', stats.total_students],
                    ['Telah Bayar', stats.paid_count],
                    ['Belum Bayar', stats.unpaid_count],
                    ['Peratus Bayaran', `${Number(stats.payment_percentage || 0).toFixed(2)}%`],
                    ['Yuran PIBG', `RM ${Number(stats.pibg_amount || 0).toFixed(2)}`],
                    ['Sumbangan Tambahan', `RM ${Number(stats.additional_donation || 0).toFixed(2)}`],
                    ['Jumlah Kutipan', `RM ${Number(stats.total_collected || 0).toFixed(2)}`],
                    ['Ranking', `#${stats.current_ranking || '-'}`],
                ];

                document.getElementById('previewStatsGrid').innerHTML = statsCards.map(([label, value]) => `
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">${label}</p>
                        <p class="mt-1 text-sm font-semibold text-zinc-900">${value ?? '-'}</p>
                    </div>
                `).join('');

                document.getElementById('previewQueueCounts').innerHTML = queueCountCardsHtml(preview.queue_dashboard || {});

                document.getElementById('previewMessageParts').innerHTML = (preview.generated_messages || []).map((message) => `
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-zinc-900">${message.part_label}</h4>
                            <span class="inline-flex rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-zinc-600">
                                ${message.segment}/${message.segment_count}
                            </span>
                        </div>
                        <div class="mt-3 rounded-[28px] border border-emerald-200 bg-[#dcf8c6] p-4 text-[13px] leading-6 text-zinc-900 shadow-sm">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-emerald-800">WhatsApp Preview</span>
                                <span class="text-[11px] text-emerald-700">Message ${message.segment}/${message.segment_count}</span>
                            </div>
                            <div class="whitespace-pre-wrap break-words" style="word-break: break-word;">${renderWhatsappText(message.body)}</div>
                        </div>
                    </div>
                `).join('');

                const ready = Boolean(preview.queue_eligibility?.ready);
                queueTeacherButton.disabled = !ready;
                document.getElementById('teacherWhatsappPreviewFooter').textContent = ready
                    ? 'Review the three message parts before queueing them into the WhatsApp processor.'
                    : 'Queueing is disabled until the class has a teacher with a valid WhatsApp number.';
            }

            async function fetchJson(url, options = {}) {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        ...(options.headers || {}),
                    },
                    ...options,
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const error = new Error(data.message || 'Request failed.');
                    error.response = response;
                    error.data = data;
                    throw error;
                }

                return data;
            }

            async function openClassPreview(className) {
                openModal(previewModal);
                setLoadingState(previewLoading, previewContent, true);

                try {
                    const preview = await fetchJson(`${buildClassRoute(previewUrlTemplate, className)}?billing_year=${billingYear}`);
                    renderPreview(preview);
                } catch (error) {
                    renderWarningBlocks(document.getElementById('previewEligibilityWarnings'), [error.data?.message || error.message || 'Unable to load preview.']);
                    queueTeacherButton.disabled = true;
                    previewContent.classList.remove('hidden');
                } finally {
                    setLoadingState(previewLoading, previewContent, false);
                }
            }

            async function queueCurrentPreview(forceDuplicate = false) {
                if (!currentPreview) {
                    return;
                }

                try {
                    const payload = {
                        billing_year: billingYear,
                        preview_token: currentPreview.preview_token,
                        force_duplicate: forceDuplicate ? 1 : 0,
                    };

                    const response = await fetchJson(buildClassRoute(queueUrlTemplate, currentPreview.class_name), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const rowButton = document.querySelector(`[data-preview-class="${CSS.escape(currentPreview.class_name)}"]`);
                    if (rowButton) {
                        rowButton.textContent = response.button_label || 'Queued';
                    }

                    showToast(response.message || `WhatsApp report queued for ${currentPreview.class_name}.`);
                    closeModal(previewModal);
                } catch (error) {
                    if (error.response?.status === 409 && !forceDuplicate) {
                        const confirmed = window.confirm(error.data?.message || 'A recent queue entry already exists. Queue again?');
                        if (confirmed) {
                            await queueCurrentPreview(true);
                        }
                        return;
                    }

                    showToast(error.data?.message || error.message || 'Unable to queue the WhatsApp report.', 'error');
                }
            }

            function renderBatchPreview(preview) {
                currentBatchPreview = preview;
                document.getElementById('batchSummaryGrid').innerHTML = [
                    ['Total Classes', preview.total_classes],
                    ['Assigned Teachers', preview.classes_with_assigned_teachers],
                    ['Missing Teachers', preview.classes_missing_teachers],
                    ['Teachers With WhatsApp', preview.teachers_with_whatsapp_number],
                    ['Missing WhatsApp', preview.teachers_missing_whatsapp_number],
                    ['Estimated Messages', preview.estimated_total_queued_messages],
                ].map(([label, value]) => `
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">${label}</p>
                        <p class="mt-1 text-lg font-bold text-zinc-900">${value ?? 0}</p>
                    </div>
                `).join('');

                const warnings = [];
                if (preview.queue_dashboard?.pending_warning) {
                    warnings.push(preview.queue_dashboard.pending_warning);
                }
                if (preview.queue_dashboard?.processor_warning) {
                    warnings.push(preview.queue_dashboard.processor_warning);
                }
                renderWarningBlocks(document.getElementById('batchQueueWarnings'), warnings, warnings.length ? 'amber' : 'rose');

                document.getElementById('batchQueueCounts').innerHTML = queueCountCardsHtml(preview.queue_dashboard || {});

                document.getElementById('batchPreviewCards').innerHTML = (preview.class_previews || []).map((card, index) => {
                    const eligible = card.status === 'ready' || card.status === 'recently_queued';
                    const statusClasses = card.status === 'ready'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : card.status === 'recently_queued'
                            ? 'border-sky-200 bg-sky-50 text-sky-700'
                            : 'border-rose-200 bg-rose-50 text-rose-700';

                    return `
                        <label class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-zinc-900">${card.class_name}</p>
                                    <p class="mt-1 text-xs text-zinc-500">${card.teacher_name || '-'}</p>
                                    <p class="mt-1 text-xs text-zinc-500">${card.teacher_phone || 'Tiada nombor'}</p>
                                </div>
                                <input
                                    type="checkbox"
                                    class="mt-1 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"
                                    data-batch-class-checkbox
                                    value="${card.class_name}"
                                    ${eligible ? 'checked' : 'disabled'}
                                />
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold ${statusClasses}">${card.status_label}</span>
                                <span class="inline-flex rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-zinc-600">${Number(card.payment_percentage || 0).toFixed(2)}%</span>
                                <span class="inline-flex rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-zinc-600">RM ${Number(card.total_collected || 0).toFixed(2)}</span>
                                <span class="inline-flex rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-zinc-600">${card.estimated_messages} msg</span>
                            </div>
                        </label>
                    `;
                }).join('');

                updateBatchQueueButtonState();
            }

            function selectedBatchClasses() {
                return Array.from(document.querySelectorAll('[data-batch-class-checkbox]:checked')).map((checkbox) => checkbox.value);
            }

            function updateBatchQueueButtonState() {
                queueBatchButton.disabled = selectedBatchClasses().length === 0;
            }

            async function openBatchPreview() {
                openModal(batchModal);
                setLoadingState(batchLoading, batchContent, true);

                try {
                    const preview = await fetchJson(`${batchPreviewUrl}?billing_year=${billingYear}`);
                    renderBatchPreview(preview);
                } catch (error) {
                    renderWarningBlocks(document.getElementById('batchQueueWarnings'), [error.data?.message || error.message || 'Unable to load batch preview.']);
                } finally {
                    setLoadingState(batchLoading, batchContent, false);
                }
            }

            async function queueBatchPreview(forceDuplicate = false) {
                if (!currentBatchPreview) {
                    return;
                }

                const classNames = selectedBatchClasses();
                if (classNames.length === 0) {
                    showToast('Please select at least one eligible class.', 'error');
                    return;
                }

                try {
                    const response = await fetchJson(batchQueueUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            billing_year: billingYear,
                            preview_token: currentBatchPreview.preview_token,
                            class_names: classNames,
                            force_duplicate: forceDuplicate ? 1 : 0,
                        }),
                    });

                    showToast(response.message || 'Batch WhatsApp blast queued.');
                    classNames.forEach((className) => {
                        const rowButton = document.querySelector(`[data-preview-class="${CSS.escape(className)}"]`);
                        if (rowButton) {
                            rowButton.textContent = 'Queued';
                        }
                    });
                    closeModal(batchModal);
                } catch (error) {
                    if (error.response?.status === 409 && !forceDuplicate) {
                        const confirmed = window.confirm(error.data?.message || 'Some classes were queued recently. Queue again?');
                        if (confirmed) {
                            await queueBatchPreview(true);
                        }
                        return;
                    }

                    showToast(error.data?.message || error.message || 'Unable to queue the batch WhatsApp blast.', 'error');
                }
            }

            function applyFilter() {
                const value = filter?.value || 'all';
                let visibleCount = 0;

                rows.forEach((row) => {
                    const level = row.getAttribute('data-year-level') || 'other';
                    const visible = value === 'all' || value === level;
                    row.classList.toggle('hidden', !visible);
                    if (visible) {
                        visibleCount += 1;
                    }
                });

                sections.forEach((section) => {
                    const sectionRows = Array.from(section.querySelectorAll('[data-class-card="1"]'));
                    const visibleRows = sectionRows.filter((row) => !row.classList.contains('hidden'));
                    section.classList.toggle('hidden', visibleRows.length === 0);
                });

                emptyState?.classList.toggle('hidden', visibleCount > 0);

                if (!defaultClassExpanded) {
                    const defaultButton = document.querySelector('[data-expand-default="1"]');
                    const defaultRow = defaultButton?.closest('[data-class-card="1"]');

                    if (defaultButton && defaultRow && !defaultRow.classList.contains('hidden')) {
                        defaultClassExpanded = true;
                        toggleClassDetails(defaultButton);
                    }
                }
            }

            document.querySelectorAll('[data-toggle-details]').forEach((button) => {
                button.addEventListener('click', () => toggleClassDetails(button));
            });

            if (canManageWhatsapp) {
                rowButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const className = button.getAttribute('data-preview-class');
                        if (className) {
                            openClassPreview(className);
                        }
                    });
                });

                batchButton?.addEventListener('click', openBatchPreview);
                queueTeacherButton?.addEventListener('click', () => queueCurrentPreview(false));
                queueBatchButton?.addEventListener('click', () => queueBatchPreview(false));
            }
            filter?.addEventListener('change', applyFilter);

            document.querySelectorAll('[data-close-preview-modal]').forEach((button) => {
                button.addEventListener('click', () => closeModal(previewModal));
            });

            document.querySelectorAll('[data-close-batch-modal]').forEach((button) => {
                button.addEventListener('click', () => closeModal(batchModal));
            });

            previewModal?.addEventListener('click', (event) => {
                if (event.target === previewModal) {
                    closeModal(previewModal);
                }
            });

            batchModal?.addEventListener('click', (event) => {
                if (event.target === batchModal) {
                    closeModal(batchModal);
                }
            });

            document.addEventListener('change', (event) => {
                if (event.target instanceof HTMLInputElement && event.target.matches('[data-batch-class-checkbox]')) {
                    updateBatchQueueButtonState();
                }
            });

            applyFilter();
        }());
    </script>
</x-layouts::app>
