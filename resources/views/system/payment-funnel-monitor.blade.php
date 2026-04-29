<x-layouts::app :title="__('Payment Funnel Monitor')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900">Payment Funnel Monitor</h1>
                    <p class="mt-1 text-sm text-zinc-600">Track payment progress and gateway outcomes by family.</p>
                </div>
                <p class="text-xs font-medium text-zinc-500">Timezone: GMT+8 (Asia/Kuala_Lumpur)</p>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Families</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($totalFamilies) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Success</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($successCount) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Pending</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($pendingCount) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Failed</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($failedCount) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Not Started</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-700">{{ number_format($notStartedCount) }}</p>
                </div>
            </div>

            <div id="gateway-check-feedback" class="mt-4 hidden rounded-xl border px-4 py-3 text-sm"></div>

            @if (session('status'))
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ session('error') }}
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white/90 shadow-sm">
            <div class="border-b border-zinc-200 bg-zinc-50 px-4 py-4">
                <form method="GET" action="{{ route('system.payment-funnel-monitor.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600 xl:col-span-2">
                        Search
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Family code / parent / phone / status / reason"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Billing Year
                        <select name="billing_year" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            @foreach ($yearOptions as $year)
                                <option value="{{ $year }}" @selected((int) $billingYear === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Gateway Status
                        <select name="gateway_status" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($statusFilter === 'all')>All</option>
                            <option value="not_started" @selected($statusFilter === 'not_started')>Not Started</option>
                            <option value="pending" @selected($statusFilter === 'pending')>Pending</option>
                            <option value="failed" @selected($statusFilter === 'failed')>Failed</option>
                            <option value="success" @selected($statusFilter === 'success')>Success</option>
                        </select>
                    </label>

                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_dir" value="{{ $sortDir }}">

                    <div class="flex items-center gap-2 md:col-span-2 xl:col-span-4">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                            Apply Filters
                        </button>
                        <a href="{{ route('system.payment-funnel-monitor.index', ['billing_year' => $billingYear]) }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">
                            <th class="px-4 py-3">Family Code</th>
                            <th class="px-4 py-3">
                                @php
                                    $nextNameDir = $sortBy === 'parent_name' && $sortDir === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <a href="{{ route('system.payment-funnel-monitor.index', array_merge(request()->query(), ['sort_by' => 'parent_name', 'sort_dir' => $nextNameDir])) }}" class="inline-flex items-center gap-1 hover:text-zinc-900">
                                    Parent Name
                                    @if ($sortBy === 'parent_name')
                                        <span>{{ $sortDir === 'asc' ? '?' : '?' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th class="px-4 py-3">Phone Number</th>
                            <th class="px-4 py-3">Billing Year</th>
                            <th class="px-4 py-3">Payment Gateway Status</th>
                            <th class="px-4 py-3">Gateway Return Status</th>
                            <th class="px-4 py-3">Gateway Reason</th>
                            <th class="px-4 py-3">Latest Bill Code</th>
                            <th class="px-4 py-3">
                                @php
                                    $nextTsDir = $sortBy === 'timestamp' && $sortDir === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <a href="{{ route('system.payment-funnel-monitor.index', array_merge(request()->query(), ['sort_by' => 'timestamp', 'sort_dir' => $nextTsDir])) }}" class="inline-flex items-center gap-1 hover:text-zinc-900">
                                    Timestamp (GMT+8)
                                    @if ($sortBy === 'timestamp')
                                        <span>{{ $sortDir === 'asc' ? '?' : '?' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800" id="gateway-monitor-table-body">
                        @forelse ($rows as $row)
                            <tr data-row-transaction-id="{{ $row['latest_transaction_id'] ?? '' }}" data-row-gateway-status="{{ $row['gateway_status'] }}">
                                <td class="px-4 py-3 font-semibold text-zinc-900">
                                    <a
                                        href="{{ route('teacher.records.family', ['familyCode' => $row['family_code'], 'payment_status' => 'all']) }}"
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-emerald-700 underline decoration-emerald-300 underline-offset-2 transition hover:text-emerald-800 hover:decoration-emerald-500"
                                    >
                                        {{ $row['family_code'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">{{ $row['parent_name'] }}</td>
                                <td class="px-4 py-3">{{ $row['phone_number'] }}</td>
                                <td class="px-4 py-3">{{ $row['billing_year'] }}</td>
                                <td class="px-4 py-3 js-gateway-status-cell">
                                    @php
                                        $status = $row['gateway_status'];
                                    @endphp
                                    @if ($status === 'success')
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Success</span>
                                    @elseif ($status === 'failed')
                                        <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">Failed</span>
                                    @elseif ($status === 'pending')
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Pending</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">Not Started</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 js-return-status-cell">{{ $row['return_status'] }}</td>
                                <td class="px-4 py-3 js-gateway-reason-cell">{{ $row['gateway_reason'] }}</td>
                                <td class="px-4 py-3 js-latest-bill-code-cell font-mono text-xs">{{ $row['latest_bill_code'] }}</td>
                                <td class="px-4 py-3 js-timestamp-cell">{{ $row['timestamp'] ? $row['timestamp']->format('d M Y H:i:s') : '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($row['can_check_gateway'] && $row['latest_transaction_id'])
                                        <div class="inline-flex items-center gap-2">
                                        <form method="POST" action="{{ route('system.payment-funnel-monitor.check-gateway') }}" class="js-gateway-check-form inline-flex items-center">
                                            @csrf
                                            <input type="hidden" name="transaction_id" value="{{ $row['latest_transaction_id'] }}">
                                            <input type="hidden" name="q" value="{{ $search }}">
                                            <input type="hidden" name="billing_year" value="{{ $billingYear }}">
                                            <input type="hidden" name="gateway_status" value="{{ $statusFilter }}">
                                            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                                            <input type="hidden" name="sort_dir" value="{{ $sortDir }}">
                                            <button type="submit" class="inline-flex items-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                                Check with Gateway
                                            </button>
                                        </form>
                                        @if (!empty($row['can_deactivate_bill']) && $row['can_deactivate_bill'] && auth()->user()?->isSystemAdmin())
                                            <form method="POST" action="{{ route('system.payment-funnel-monitor.deactivate-bill') }}" class="inline-flex items-center" onsubmit="return confirm('Deactivate this bill in ToyyibPay? This action cannot be undone.');">
                                                @csrf
                                                <input type="hidden" name="transaction_id" value="{{ $row['latest_transaction_id'] }}">
                                                <input type="hidden" name="q" value="{{ $search }}">
                                                <input type="hidden" name="billing_year" value="{{ $billingYear }}">
                                                <input type="hidden" name="gateway_status" value="{{ $statusFilter }}">
                                                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                                                <input type="hidden" name="sort_dir" value="{{ $sortDir }}">
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                                    Deactivate Bill
                                                </button>
                                            </form>
                                        @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr id="gateway-empty-row">
                                <td colspan="10" class="px-4 py-10 text-center text-zinc-500">No records found for this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const forms = Array.from(document.querySelectorAll('.js-gateway-check-form'));
            if (!forms.length) {
                return;
            }

            const feedback = document.getElementById('gateway-check-feedback');
            const tableBody = document.getElementById('gateway-monitor-table-body');
            const currentFilter = @json($statusFilter);

            const badgeHtml = (status) => {
                if (status === 'success') {
                    return '<span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Success</span>';
                }
                if (status === 'failed') {
                    return '<span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">Failed</span>';
                }
                if (status === 'pending') {
                    return '<span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Pending</span>';
                }

                return '<span class="inline-flex rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">Not Started</span>';
            };

            const showMessage = (ok, message) => {
                if (!feedback) {
                    return;
                }

                feedback.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-700', 'border-rose-200', 'bg-rose-50', 'text-rose-700');
                feedback.classList.add(ok ? 'border-emerald-200' : 'border-rose-200');
                feedback.classList.add(ok ? 'bg-emerald-50' : 'bg-rose-50');
                feedback.classList.add(ok ? 'text-emerald-700' : 'text-rose-700');
                feedback.textContent = message;
            };

            const ensureEmptyRow = () => {
                if (!tableBody) {
                    return;
                }

                const hasDataRows = Array.from(tableBody.querySelectorAll('tr')).some((row) => !row.id || row.id !== 'gateway-empty-row');
                if (hasDataRows) {
                    return;
                }

                const emptyRow = document.createElement('tr');
                emptyRow.id = 'gateway-empty-row';
                emptyRow.innerHTML = '<td colspan="10" class="px-4 py-10 text-center text-zinc-500">No records found for this filter.</td>';
                tableBody.appendChild(emptyRow);
            };

            forms.forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const button = form.querySelector('button[type="submit"]');
                    if (!button) {
                        return;
                    }

                    const originalLabel = button.textContent;
                    button.disabled = true;
                    button.textContent = 'Checking...';

                    const row = form.closest('tr');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            },
                            body: new FormData(form),
                        });

                        const data = await response.json();
                        const ok = Boolean(data?.ok);
                        const message = String(data?.message || 'Gateway check completed.');
                        const payload = data?.payload || null;

                        showMessage(ok, message);

                        if (ok && payload && row) {
                            row.dataset.rowGatewayStatus = String(payload.gateway_status || 'pending');

                            const statusCell = row.querySelector('.js-gateway-status-cell');
                            if (statusCell) {
                                statusCell.innerHTML = badgeHtml(String(payload.gateway_status || 'pending'));
                            }

                            const returnStatusCell = row.querySelector('.js-return-status-cell');
                            if (returnStatusCell) {
                                returnStatusCell.textContent = String(payload.return_status || '-');
                            }

                            const reasonCell = row.querySelector('.js-gateway-reason-cell');
                            if (reasonCell) {
                                reasonCell.textContent = String(payload.gateway_reason || '-');
                            }

                            const billCodeCell = row.querySelector('.js-latest-bill-code-cell');
                            if (billCodeCell) {
                                billCodeCell.textContent = String(payload.latest_bill_code || '-');
                            }

                            const timestampCell = row.querySelector('.js-timestamp-cell');
                            if (timestampCell) {
                                timestampCell.textContent = String(payload.timestamp || '-');
                            }

                            if (currentFilter === 'pending' && String(payload.gateway_status || '') !== 'pending') {
                                row.remove();
                                ensureEmptyRow();
                            }
                        }
                    } catch (error) {
                        showMessage(false, 'Gateway check failed due to network/server error.');
                    } finally {
                        button.disabled = false;
                        button.textContent = originalLabel;
                    }
                });
            });
        }());
    </script>
</x-layouts::app>