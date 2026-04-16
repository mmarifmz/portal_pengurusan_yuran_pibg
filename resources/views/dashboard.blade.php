<x-layouts::app :title="__('Dashboard')" class="space-y-6">
    @if ($role !== 'parent')
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <x-kpi-card title="Total Students" :value="number_format($totalStudents)" icon="user-group" />
            <x-kpi-card title="Families tracked" :value="number_format($totalFamilies)" icon="building-office" />
            <x-kpi-card title="Collected (RM)" :value="number_format($totalCollected, 2)" icon="banknotes" />
            <x-kpi-card title="Payment completion" :value="$paymentCompletion . '%'" icon="chart-pie" />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Collection by class</p>
                        <h3 class="text-lg font-semibold text-zinc-900">Kod keluarga paling aktif</h3>
                    </div>
                    <span class="text-xs text-emerald-500">RM {{ number_format($totalCollected, 2) }} collected</span>
                </div>
                <div class="mt-4 h-52">
                    <canvas id="collectionByClassChart" class="h-full w-full"></canvas>
                </div>
            </div>

            <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Family status</p>
                        <h3 class="text-lg font-semibold text-zinc-900">Paid vs unpaid families</h3>
                    </div>
                    <span class="text-xs text-zinc-500">{{ $totalFamilies }} families</span>
                </div>
                <div class="mt-5 h-48">
                    <canvas id="familyStatusPieChart" class="h-full w-full"></canvas>
                </div>
            </div>

            <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Daily collection trend</p>
                        <h3 class="text-lg font-semibold text-zinc-900">14-day rhythm</h3>
                    </div>
                    <span class="text-xs text-zinc-500">Last {{ count($dailyTrendLabels) }} days</span>
                </div>
                <div class="mt-5 h-48">
                    <canvas id="dailyCollectionChart" class="h-full w-full"></canvas>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <header class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-500">Family contributions</p>
                            <h2 class="text-xl font-semibold text-zinc-900">Kod keluarga & status pembayaran</h2>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <label class="text-xs font-semibold text-zinc-600">
                                Carian
                                <input id="familySearchInput" type="search" placeholder="Cari kod atau penjaga" class="mt-1 w-full rounded-2xl border border-zinc-200 px-3 py-1 text-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                            </label>
                            <label class="text-xs font-semibold text-zinc-600">
                                Kelas
                                <select id="familyClassFilter" class="mt-1 rounded-2xl border border-zinc-200 bg-white px-3 py-1 text-xs outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <option value="All">Semua kelas</option>
                                    @foreach (collect($classChartLabels)->unique()->values() as $classOption)
                                        @if ($classOption)
                                            <option value="{{ $classOption }}">{{ $classOption }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs font-semibold text-zinc-600">
                                Status
                                <select id="familyStatusFilter" class="mt-1 rounded-2xl border border-zinc-200 bg-white px-3 py-1 text-xs outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <option value="All">Semua status</option>
                                    @foreach (collect($familyContribution)->pluck('status')->unique() as $statusOption)
                                        <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </header>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-zinc-700">
                            <thead class="text-xs uppercase tracking-wider text-zinc-500">
                                <tr>
                                    <th class="px-3 py-2">Kod keluarga</th>
                                    <th class="px-3 py-2">Penjaga</th>
                                    <th class="px-3 py-2 text-center">Bilangan anak</th>
                                    <th class="px-3 py-2 text-right">Jumlah perlu bayar (RM)</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2 hidden md:table-cell">Komen</th>
                                    <th class="px-3 py-2 text-center">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody id="familyContributionBody">
                                @forelse ($familyContribution as $family)
                                    <tr class="border-t border-zinc-100" data-family-row data-family-code="{{ $family['family_code'] }}" data-guardian="{{ $family['guardian'] }}" data-status="{{ $family['status'] }}" data-comment="{{ $family['comment'] }}" data-classes="{{ $family['classes'] }}" data-children="{{ e(json_encode($family['children_list'])) }}">
                                        <td class="px-3 py-3 font-semibold text-zinc-900">{{ $family['family_code'] }}</td>
                                        <td class="px-3 py-3">{{ $family['guardian'] }}</td>
                                        <td class="px-3 py-3 text-center font-semibold text-zinc-900">{{ $family['children'] }}</td>
                                        <td class="px-3 py-3 text-right font-semibold text-emerald-600">RM {{ number_format($family['amount_due'], 2) }}</td>
                                        <td class="px-3 py-3">{{ $family['status'] }}</td>
                                        <td class="px-3 py-3 hidden md:table-cell text-zinc-500">{{ $family['comment'] }}</td>
                                        <td class="px-3 py-3 text-center">
                                            <button type="button" data-details-button class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Senarai anak</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3 py-6 text-center text-xs text-zinc-500">Tiada rekod keluarga buat masa ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <header class="flex items-center justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-500">Quick actions</p>
                            <h3 class="text-lg font-semibold text-zinc-900">Accelerate collection</h3>
                        </div>
                    </header>
                    <div class="mt-4 space-y-3">
                        <a href="{{ route('students.import.form') }}" class="flex items-center justify-between rounded-2xl border border-zinc-200 px-4 py-3 text-sm font-semibold text-zinc-900 hover:border-emerald-500 hover:text-emerald-600">
                            <span>Tambah murid baharu</span>
                            <span>â†—</span>
                        </a>
                        <form method="POST" action="{{ route('billing.setup.current-year') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500">
                                Jana bil keluarga ({{ now()->year }})
                            </button>
                        </form>
                        <a href="{{ route('students.family.list') }}" class="flex items-center justify-between rounded-2xl border border-zinc-200 px-4 py-3 text-sm font-semibold text-zinc-900 hover:border-emerald-500 hover:text-emerald-600">
                            <span>Eksport laporan</span>
                            <span>â¬‡</span>
                        </a>
                    </div>
                </div>

                <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Recent activity</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Transaksi terkini</h3>
                    <div class="mt-4 space-y-3">
                        @forelse ($recentActivities as $activity)
                            <article class="rounded-2xl border border-zinc-100 bg-zinc-50/70 p-3 text-sm text-zinc-700">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-zinc-900">RM {{ number_format($activity->amount, 2) }}</span>
                                    <span class="text-xs text-zinc-500">{{ $activity->paid_at?->format('d M Y') ?? 'â€”' }}</span>
                                </div>
                                <p class="text-xs text-zinc-500 mt-1">{{ $activity->familyBilling?->family_code ?? 'â€”' }}</p>
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <span>{{ ucfirst($activity->status) }}</span>
                                    <a href="{{ route('parent.payments.receipt', $activity->external_order_id) }}" class="text-emerald-600 underline">Resit</a>
                                </div>
                            </article>
                        @empty
                            <p class="text-xs text-zinc-500">Belum ada aktiviti.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Access logs</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Audit ringkas</h3>
                    <div class="mt-3 space-y-2 text-xs text-zinc-600">
                        @forelse ($accessLogs as $log)
                            <p class="rounded-2xl border border-zinc-100 bg-zinc-50 px-3 py-2">{{ $log }}</p>
                        @empty
                            <p class="rounded-2xl border border-zinc-100 bg-zinc-50 px-3 py-2 text-emerald-500">Tiada log terbaru.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div id="familyChildrenModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 px-4 py-6">
            <div class="w-full max-w-xl rounded-3xl border border-zinc-200 bg-white p-5 shadow-2xl">
                <header class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Senarai anak</p>
                        <h3 class="text-lg font-semibold text-zinc-900" id="familyModalTitle"></h3>
                    </div>
                    <button type="button" data-close-modal class="rounded-full border border-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-900">Tutup</button>
                </header>
                <p class="mt-2 text-sm text-zinc-500" id="familyModalComment"></p>
                <div class="mt-4 space-y-3" id="familyModalChildrenList"></div>
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
                                label: 'Outstanding (RM)',
                                data: @json($classChartOutstanding),
                                backgroundColor: 'rgba(16, 185, 129, 0.65)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                            }, {
                                label: 'Collected (RM)',
                                data: @json($classChartCollected),
                                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                                borderColor: 'rgba(59, 130, 246, 1)',
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
                    new Chart(pieCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: @json($pieChartLabels),
                            datasets: [{
                                data: @json($pieChartValues),
                                backgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(244, 114, 182, 0.8)'],
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                        },
                    });
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

                const searchInput = document.getElementById('familySearchInput');
                const classFilter = document.getElementById('familyClassFilter');
                const statusFilter = document.getElementById('familyStatusFilter');
                const rows = document.querySelectorAll('[data-family-row]');

                function applyFamilyFilters() {
                    const search = searchInput?.value.trim().toLowerCase() ?? '';
                    const selectedClass = classFilter?.value;
                    const selectedStatus = statusFilter?.value;

                    rows.forEach((row) => {
                        const code = row.dataset.familyCode?.toLowerCase() ?? '';
                        const guardian = row.dataset.guardian?.toLowerCase() ?? '';
                        const comment = row.dataset.comment?.toLowerCase() ?? '';
                        const classes = row.dataset.classes ? row.dataset.classes.split(',').map((value) => value.trim()) : [];

                        const matchesSearch = !search || code.includes(search) || guardian.includes(search) || comment.includes(search);
                        const matchesClass = !selectedClass || selectedClass === 'All' || classes.includes(selectedClass);
                        const matchesStatus = !selectedStatus || selectedStatus === 'All' || row.dataset.status === selectedStatus;

                        row.style.display = matchesSearch && matchesClass && matchesStatus ? '' : 'none';
                    });
                }

                if (searchInput) searchInput.addEventListener('input', applyFamilyFilters);
                if (classFilter) classFilter.addEventListener('change', applyFamilyFilters);
                if (statusFilter) statusFilter.addEventListener('change', applyFamilyFilters);

                const modal = document.getElementById('familyChildrenModal');
                const modalTitle = document.getElementById('familyModalTitle');
                const modalChildrenList = document.getElementById('familyModalChildrenList');
                const modalComment = document.getElementById('familyModalComment');
                const closeButton = modal?.querySelector('[data-close-modal]');
                const detailButtons = document.querySelectorAll('[data-details-button]');

                function openModal(familyCode, comment, children) {
                    if (!modal) return;

                    modalTitle.textContent = familyCode;
                    modalComment.textContent = comment || 'Tiada komen tambahan.';
                    modalChildrenList.innerHTML = '';

                    if (!children.length) {
                        modalChildrenList.innerHTML = '<p class="text-sm text-zinc-500">Tiada murid didaftarkan.</p>';
                    } else {
                        children.forEach((child) => {
                            const item = document.createElement('div');
                            item.className = 'rounded-2xl border border-zinc-100 bg-zinc-50 px-4 py-3 text-sm text-zinc-700';
                            item.innerHTML = `<p class="font-semibold text-zinc-900">${child.full_name}</p><p class="text-xs text-zinc-500">${child.class_name} Â· ${child.status}</p>`;
                            modalChildrenList.append(item);
                        });
                    }

                    modal.classList.remove('hidden');
                }

                detailButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const row = button.closest('[data-family-row]');
                        if (!row) return;
                        const familyCode = row.dataset.familyCode;
                        const comment = row.dataset.comment;
                        const children = JSON.parse(row.dataset.children || '[]');
                        openModal(familyCode, comment, children);
                    });
                });

                closeButton?.addEventListener('click', () => {
                    modal?.classList.add('hidden');
                });

                modal?.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        modal.classList.add('hidden');
                    }
                });


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

