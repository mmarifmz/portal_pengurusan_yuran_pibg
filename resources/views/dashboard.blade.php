<x-layouts::app :title="__('Dashboard')" class="space-y-6">
    @if ($role !== 'parent')
        <div class="rounded-3xl border border-zinc-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('dashboard') }}#collection-by-class-section" class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Dashboard filter</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Data year selector</h3>
                </div>
                <div class="flex items-end gap-2">
                    <input type="hidden" name="class_tahun" value="{{ $selectedClassYearFilter ?? 'all' }}">
                    <label class="text-xs font-semibold text-zinc-600">
                        Tahun data
                        <select name="dashboard_year" onchange="this.form.submit()" class="mt-1 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            @foreach ($dashboardYearOptions as $yearOption)
                                <option value="{{ $yearOption }}" @selected((int) $yearOption === (int) $selectedDashboardYear)>{{ $yearOption }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </form>
        </div>

        <div class="grid gap-4">
            @include('partials.parent-calendar', [
                'calendarEvents' => $calendarEvents,
                'paidCountByDate' => $calendarPaidCountByDate,
                'calendarBlockLabel' => 'Takwim sekolah',
                'calendarBlockTitle' => "Aktiviti semasa + bilangan bayaran harian ({$selectedDashboardYear})",
                'calendarBlockDescription' => 'Angka hijau dalam hari menunjukkan jumlah keluarga yang sudah bayar pada tarikh tersebut.',
            ])
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Kutipan yuran</p>
                <h3 class="mt-2 text-3xl font-bold text-emerald-700">RM {{ number_format((float) ($tuitionCollected ?? 0), 2) }}</h3>
                <p class="mt-1 text-xs text-zinc-500">Tahun {{ $selectedDashboardYear }}</p>
            </article>
            <article class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Kutipan sumbangan</p>
                <h3 class="mt-2 text-3xl font-bold text-amber-600">RM {{ number_format((float) ($donationCollected ?? 0), 2) }}</h3>
                <p class="mt-1 text-xs text-zinc-500">Bayaran melebihi RM100 (Tahun {{ $selectedDashboardYear }})</p>
            </article>
            <article class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Jumlah kutipan keseluruhan</p>
                <h3 class="mt-2 text-3xl font-bold text-zinc-900">RM {{ number_format($totalCollected, 2) }}</h3>
                <p class="mt-1 text-xs text-zinc-500">{{ $useLegacyKpiSource ? 'Sumber sejarah 2025' : 'Sumber transaksi portal' }}</p>
            </article>
            <article class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Keluarga berbayar</p>
                <h3 class="mt-2 text-3xl font-bold text-zinc-900">{{ number_format((int) ($familiesPaid ?? 0)) }} / {{ number_format((int) ($totalFamilies ?? 0)) }}</h3>
                <p class="mt-1 text-xs text-zinc-500">{{ (int) ($paymentCompletion ?? 0) }}% · Pelajar {{ number_format($totalStudents) }}</p>
            </article>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div id="collection-by-class-section" class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Collection by class</p>
                        <h3 class="text-lg font-semibold text-zinc-900">Kutipan mengikut kelas (RM)</h3>
                    </div>
                    <div class="flex items-end gap-2">
                        <form method="GET" action="{{ route('dashboard') }}#collection-by-class-section">
                            <input type="hidden" name="dashboard_year" value="{{ $selectedDashboardYear }}">
                            <label class="text-xs font-semibold text-zinc-600">
                                Tahun
                                <select name="class_tahun" onchange="this.form.submit()" class="mt-1 rounded-xl border border-zinc-200 bg-white px-2.5 py-1 text-xs text-zinc-700 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <option value="all" @selected(($selectedClassYearFilter ?? 'all') === 'all')>Semua</option>
                                    @foreach (($classYearOptions ?? []) as $classYearOption)
                                        <option value="{{ $classYearOption }}" @selected((string) ($selectedClassYearFilter ?? 'all') === (string) $classYearOption)>Tahun {{ $classYearOption }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </form>
                        <span class="text-xs text-emerald-600">{{ $selectedDashboardYear }} · RM {{ number_format($totalCollected, 2) }}</span>
                    </div>
                </div>
                <p class="mt-2 text-xs text-zinc-500">Susunan kelas: tertinggi ke terendah.</p>
                <div class="mt-4 h-64">
                    <canvas id="collectionByClassChart" class="h-full w-full"></canvas>
                </div>
            </div>

            <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Family status</p>
                        <h3 class="text-lg font-semibold text-zinc-900">Paid vs unpaid families</h3>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <label class="text-xs font-semibold text-zinc-600">
                            Class
                            <select id="familyStatusClassFilter" class="mt-1 rounded-xl border border-zinc-200 bg-white px-2.5 py-1 text-xs text-zinc-700 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                @foreach ($statusFilterClasses as $classOption)
                                    <option value="{{ $classOption }}">{{ $classOption }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>
                <p id="familyStatusSummary" class="mt-2 text-xs text-zinc-500"></p>
                <div class="mt-4 h-56">
                    <canvas id="familyStatusPieChart" class="h-full w-full"></canvas>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Collection trend</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Trend kutipan bulanan</h3>
                </div>
                <span class="text-xs text-zinc-500">{{ $selectedDashboardYear }} (Jan-Dis)</span>
            </div>
            <div class="mt-5 h-64">
                <canvas id="dailyCollectionChart" class="h-full w-full"></canvas>
            </div>
        </div>
    @elseif ($role === 'parent')
        <div class="grid gap-5 lg:grid-cols-3">
            @include('partials.parent-calendar', ['calendarEvents' => $calendarEvents])

            <div class="rounded-3xl border border-zinc-200 bg-white px-5 pt-10 pb-5 shadow-sm">
                <p class="mt-1 text-xs uppercase tracking-wide text-emerald-500">Recent payments</p>
                <h3 class="text-lg font-semibold text-zinc-900">5 Recent Payment Activity</h3>
                <div class="mt-3 space-y-2.5">
                    @forelse ($transactions as $transaction)
                        <article class="rounded-2xl border border-zinc-100 bg-zinc-50/70 p-2.5 text-sm text-zinc-700">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-zinc-900">RM {{ number_format($transaction->amount, 2) }}</span>
                                <span class="text-xs text-zinc-500">{{ $transaction->paid_at?->format('d M Y') ?? '-' }}</span>
                            </div>
                            <p class="text-xs text-zinc-500 mt-1">{{ $transaction->familyBilling?->family_code ?? '-' }}</p>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-3 text-xs text-zinc-600">
                                <span class="uppercase">{{ $transaction->status === 'superseded' ? 'dibatalkan' : $transaction->status }}</span>
                                @if ($transaction->status === 'success')
                                    <a href="{{ route('parent.payments.receipt', $transaction->external_order_id) }}" class="text-emerald-600 underline">Muat turun resit</a>
                                @else
                                    <span class="text-zinc-400">Resit tersedia selepas berjaya</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="rounded-2xl border border-zinc-100 bg-zinc-50/70 p-3 text-xs text-zinc-500">Belum ada pembayaran dicatatkan.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-5 lg:grid-cols-3">
            <div class="rounded-3xl border border-zinc-200 bg-white p-4 shadow-sm lg:col-span-2">
                <p class="text-xs uppercase tracking-wide text-emerald-500">Receipts</p>
                <h3 class="text-lg font-semibold text-zinc-900">Muat turun resit terdahulu</h3>
                <div class="mt-3 space-y-2.5">
                    @forelse ($transactionsByYear->sortKeysDesc() as $year => $yearTransactions)
                        <div class="rounded-2xl border border-zinc-100 bg-zinc-50/70 p-2.5">
                            <header class="flex items-center justify-between">
                                <span class="font-semibold text-zinc-900">{{ $year }}</span>
                                <span class="text-xs text-zinc-500">{{ $year === now()->year ? 'Tahun semasa' : 'Historikal' }}</span>
                            </header>
                            <div class="mt-2 space-y-2 text-xs text-zinc-600">
                                @foreach ($yearTransactions as $transaction)
                                    <div class="flex items-center justify-between">
                                        <span>RM {{ number_format($transaction->amount, 2) }}</span>
                                        <a href="{{ route('parent.payments.receipt', $transaction->external_order_id) }}" class="text-emerald-600 underline">Download</a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-zinc-100 bg-zinc-50/70 p-3 text-xs text-zinc-500">Tiada resit berjaya untuk dipaparkan.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-zinc-200 bg-white px-5 pt-10 pb-5 shadow-sm">
                <p class="mt-1 text-xs uppercase tracking-wide text-emerald-500">Message treasury</p>
                <h3 class="text-lg font-semibold text-zinc-900">Ada isu pembayaran?</h3>
                @if (session('parent_message_status'))
                    <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                        {{ session('parent_message_status') }}
                    </div>
                @endif
                <form method="POST" action="{{ route('dashboard.parent.message') }}" class="mt-3 space-y-3">
                    @csrf
                    <label class="block text-xs font-semibold text-zinc-600">Ceritakan isu anda</label>
                    <textarea id="parentMessageInput" name="message" rows="4" maxlength="1000" class="block w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">{{ old('message') }}</textarea>
                    <p id="parentMessageCounter" class="text-xs text-zinc-500">1000 aksara lagi (had WhatsApp)</p>
                    <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Hantar melalui WhatsApp bendahari
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="rounded-3xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-600 shadow-sm">
            <p>Dashboard tersedia selepas anda log masuk.</p>
        </div>
    @endif

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const barCanvas = document.getElementById('collectionByClassChart');
                if (barCanvas) {
                    new Chart(barCanvas, {
                        type: 'bar',
                        data: {
                            labels: @json($classChartLabels),
                            datasets: [{
                                label: 'Collected (RM)',
                                data: @json($classChartCollected),
                                backgroundColor: 'rgba(16, 185, 129, 0.65)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { intersect: false, mode: 'index' },
                            scales: {
                                y: { beginAtZero: true, ticks: { callback(value) { return `RM ${Number(value).toFixed(0)}`; } } },
                            },
                        },
                    });
                }

                const pieCanvas = document.getElementById('familyStatusPieChart');
                if (pieCanvas) {
                    const familyStatusClassFilter = document.getElementById('familyStatusClassFilter');
                    const familyStatusSummary = document.getElementById('familyStatusSummary');
                    const familyStatusByYearClass = @json($familyStatusByYearClass);
                    const selectedStatusFilterYear = @json($selectedStatusFilterYear);

                    const pieChart = new Chart(pieCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: ['Paid', 'Unpaid'],
                            datasets: [{
                                data: [0, 0],
                                backgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(244, 114, 182, 0.8)'],
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                        },
                    });

                    const updateFamilyStatusPie = () => {
                        const selectedYear = selectedStatusFilterYear || '';
                        const selectedClass = familyStatusClassFilter?.value || 'All';
                        const yearData = familyStatusByYearClass[selectedYear] || {};
                        const classData = yearData[selectedClass] || { paid: 0, unpaid: 0 };
                        const paid = Number(classData.paid || 0);
                        const unpaid = Number(classData.unpaid || 0);
                        const total = paid + unpaid;

                        pieChart.data.datasets[0].data = [paid, unpaid];
                        pieChart.update();

                        if (familyStatusSummary) {
                            familyStatusSummary.textContent = total > 0
                                ? `${selectedYear} · ${selectedClass} · ${paid} paid / ${unpaid} unpaid families`
                                : `${selectedYear} · ${selectedClass} · Tiada rekod keluarga`;
                        }
                    };

                    familyStatusClassFilter?.addEventListener('change', updateFamilyStatusPie);
                    updateFamilyStatusPie();
                }

                const lineCanvas = document.getElementById('dailyCollectionChart');
                if (lineCanvas) {
                    new Chart(lineCanvas, {
                        type: 'line',
                        data: {
                            labels: @json($dailyTrendLabels),
                            datasets: [{
                                label: 'Daily collection (RM)',
                                data: @json($dailyTrendValues),
                                borderColor: 'rgba(59, 130, 246, 1)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.35,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, ticks: { callback(value) { return `RM ${Number(value).toFixed(0)}`; } } },
                            },
                        },
                    });
                }

                const parentMessageInput = document.getElementById('parentMessageInput');
                const parentMessageCounter = document.getElementById('parentMessageCounter');

                if (parentMessageInput && parentMessageCounter) {
                    const maxLength = Number(parentMessageInput.getAttribute('maxlength') || 1000);
                    const updateParentMessageCounter = () => {
                        const used = parentMessageInput.value.length;
                        const left = Math.max(0, maxLength - used);
                        parentMessageCounter.textContent = `${left} aksara lagi (had WhatsApp)`;
                        parentMessageCounter.classList.toggle('text-rose-600', left <= 50);
                    };

                    parentMessageInput.addEventListener('input', updateParentMessageCounter);
                    updateParentMessageCounter();
                }
            });
        </script>
    @endonce
</x-layouts::app>
