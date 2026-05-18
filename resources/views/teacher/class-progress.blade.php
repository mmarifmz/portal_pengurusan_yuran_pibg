<x-layouts::app :title="__('Class Payment Progress')">
    @php
        $queueCountCards = [
            ['label' => 'Pending', 'value' => $queueDashboard['pending'] ?? 0],
            ['label' => 'Sending', 'value' => $queueDashboard['sending'] ?? 0],
            ['label' => 'Sent Today', 'value' => $queueDashboard['sent_today'] ?? 0],
            ['label' => 'Failed Today', 'value' => $queueDashboard['failed_today'] ?? 0],
        ];
    @endphp

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Teacher View</p>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900">Leaderboard Bayaran Mengikut Kelas</h1>
                    <p class="mt-1 text-sm text-zinc-600">Progress Bayaran Yuran Mengikut Kelas bagi sesi {{ $billingYear }}</p>
                    <p class="mt-2 text-xs text-zinc-500">Every WhatsApp report is previewed first, then queued for processing through the app workflow.</p>
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
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($queueCountCards as $card)
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $card['label'] }}</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $card['value'] }}</p>
                    </div>
                @endforeach
            </div>

            @if (! empty($queueDashboard['pending_warning']) || ! empty($queueDashboard['processor_warning']))
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

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm text-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">Kelas</th>
                            <th class="px-5 py-3 text-right">Jumlah Keluarga</th>
                            <th class="px-5 py-3 text-right">Selesai Bayar</th>
                            <th class="px-5 py-3 text-right">Bayaran Sebahagian</th>
                            <th class="px-5 py-3 text-right">Belum Bayar</th>
                            <th class="px-5 py-3 text-right">Kutipan Yuran</th>
                            <th class="px-5 py-3 text-right">Sumbangan Tambahan</th>
                            <th class="px-5 py-3 text-right">Jumlah Kutipan</th>
                            <th class="px-5 py-3 text-right">Baki Tertunggak</th>
                            <th class="px-5 py-3 text-right">Completion %</th>
                            <th class="px-5 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($leaderboardRows as $row)
                            <tr
                                data-class-card="1"
                                data-year-level="{{ $row['year_level'] ?? 'other' }}"
                                data-class-name="{{ $row['class_name'] }}"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-zinc-900">{{ $row['class_name'] }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $row['teacher_name'] }}</p>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($row['status_badges'] as $badge)
                                            <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $badge['classes'] }}">
                                                {{ $badge['label'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right font-semibold text-zinc-900">{{ $row['total_families'] }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-emerald-700">{{ $row['fully_paid_families'] }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-amber-600">{{ $row['partial_paid_families'] }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-rose-600">{{ $row['unpaid_families'] }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-emerald-700">RM {{ number_format((float) $row['yuran_collected'], 2) }}</td>
                                <td class="px-5 py-4 text-right {{ (float) $row['sumbangan_tambahan_collected'] > 0 ? 'font-semibold text-cyan-700' : 'text-zinc-400' }}">RM {{ number_format((float) $row['sumbangan_tambahan_collected'], 2) }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-zinc-900">RM {{ number_format((float) $row['jumlah_kutipan'], 2) }}</td>
                                <td class="px-5 py-4 text-right {{ (float) $row['baki_tertunggak'] > 0 ? 'font-semibold text-amber-700' : 'font-semibold text-emerald-700' }}">RM {{ number_format((float) $row['baki_tertunggak'], 2) }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-zinc-900">{{ number_format((float) $row['completion_percent'], 2) }}%</td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        type="button"
                                        data-preview-class="{{ $row['class_name'] }}"
                                        class="inline-flex shrink-0 items-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                    >
                                        WhatsApp Guru
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-5 py-8 text-center text-sm text-zinc-500">Tiada data kelas untuk sesi ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="classProgressEmpty" class="hidden rounded-2xl border border-zinc-200 bg-white p-6 text-center text-sm text-zinc-500 shadow-sm">
            Tiada kelas untuk tapisan ini.
        </div>
    </div>

    <div id="whatsappToast" class="pointer-events-none fixed right-4 top-4 z-[10001] hidden max-w-sm rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-2xl"></div>

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

    <script>
        (function () {
            const billingYear = @json($billingYear);
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
            const emptyState = document.getElementById('classProgressEmpty');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const previewUrlTemplate = @json(route('admin.classes.whatsapp-preview', ['class' => '__CLASS__']));
            const queueUrlTemplate = @json(route('admin.classes.whatsapp-queue', ['class' => '__CLASS__']));
            const batchPreviewUrl = @json(route('admin.classes.whatsapp-batch-preview'));
            const batchQueueUrl = @json(route('admin.classes.whatsapp-batch-queue'));

            let currentPreview = null;
            let currentBatchPreview = null;

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
                        <textarea readonly rows="16" class="mt-3 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-800">${message.body}</textarea>
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

                emptyState?.classList.toggle('hidden', visibleCount > 0);
            }

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
