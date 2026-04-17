<x-layouts::app :title="__('Family Profile')">
    @php
        $allPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'all']);
        $successfulPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'successful']);
        $pendingPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'pending']);
        $cancelledPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'cancelled']);
        $exportPaymentsUrl = route('teacher.records.family.payments.export', ['familyCode' => $familyCode, 'payment_status' => $paymentFilter]);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Family profile</p>
                <h1 class="text-2xl font-bold text-zinc-900">{{ $familyCode }}</h1>
                <p class="text-sm text-zinc-500">Butiran keluarga, sejarah pembayaran dan log akses TAC.</p>
            </div>
            <a href="{{ route('teacher.records') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50">
                Back to Student &amp; Family Lists
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Students</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ $students->count() }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Total billed (RM)</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($totalBilled, 2) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Total paid (RM)</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ number_format($totalPaid, 2) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Outstanding (RM)</p>
                <p class="mt-2 text-2xl font-semibold {{ $totalOutstanding > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($totalOutstanding, 2) }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Parent Account</h2>
                    <p class="text-xs text-zinc-500">Status onboarding parent berdasarkan user parent dan log TAC berjaya.</p>
                </div>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $isOnboarded ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $isOnboarded ? 'Onboarded' : 'Not onboarded' }}
                </span>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Linked parent users</p>
                    <p class="mt-1 text-xl font-semibold text-zinc-900">{{ $linkedParents->count() }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Successful TAC logins</p>
                    <p class="mt-1 text-xl font-semibold text-zinc-900">{{ $successfulLogins }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Latest access</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900">{{ $latestAccessAt?->format('d M Y H:i') ?: '-' }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <h3 class="mb-2 text-sm font-semibold text-zinc-900">Family Attached Phones</h3>
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Phone Number</th>
                            <th class="px-4 py-3">Last Logged In</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($familyPhoneAccess as $phoneAccess)
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $phoneAccess['phone'] }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $phoneAccess['latest_login_at']?->format('d M Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-4 text-center text-zinc-500">No phone number attached to this family yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($linkedParents as $linkedParent)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $linkedParent->name ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $linkedParent->email ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $linkedParent->phone ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $linkedParent->created_at?->format('d M Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-zinc-500">No linked parent account found for this family yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Family Members</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Student No</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3">Parent Name</th>
                            <th class="px-4 py-3">Parent Contact</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @foreach ($students as $student)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $student->student_no ?: '-' }}</td>
                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $student->full_name }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $student->class_name ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $student->parent_name ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-600">
                                    <div>{{ $student->parent_phone ?: '-' }}</div>
                                    <div class="text-xs">{{ $student->parent_email ?: '-' }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Payment History</h2>
                    <p class="text-xs text-zinc-500">Semua transaksi untuk family code ini.</p>
                </div>
                <a href="{{ $exportPaymentsUrl }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50">
                    Export payment log (CSV)
                </a>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <a href="{{ $allPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'all' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">All</a>
                <a href="{{ $successfulPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'successful' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">Successful</a>
                <a href="{{ $pendingPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'pending' ? 'border-amber-600 bg-amber-500 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">Pending</a>
                <a href="{{ $cancelledPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'cancelled' ? 'border-rose-600 bg-rose-600 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">Cancelled</a>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Order ID</th>
                            <th class="px-4 py-3">Bill Code</th>
                            <th class="px-4 py-3 text-right">Amount (RM)</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Return Status</th>
                            <th class="px-4 py-3">Payer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($paymentHistory as $payment)
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->paid_at_for_display?->format('d M Y H:i') ?? $payment->created_at_for_display?->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $payment->external_order_id }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->provider_bill_code ?: '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-zinc-900">{{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ ucfirst((string) $payment->status) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->return_status ? ucfirst((string) $payment->return_status) : '-' }}</td>
                                <td class="px-4 py-3 text-zinc-600">
                                    <div>{{ $payment->payer_name ?: '-' }}</div>
                                    <div class="text-xs">{{ $payment->payer_email ?: $payment->payer_phone ?: '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-zinc-500">No payment history recorded for the selected filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Historical Paid Records (Imported)</h2>
                    <p class="text-xs text-zinc-500">Past-year paid history imported from legacy CSV.</p>
                </div>
                <div class="text-right text-sm text-zinc-700">
                    <div>Total paid: <span class="font-semibold">RM {{ number_format($legacyPaidTotal, 2) }}</span></div>
                    <div>Total sumbangan: <span class="font-semibold">RM {{ number_format($legacyDonationTotal, 2) }}</span></div>
                </div>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Paid At</th>
                            <th class="px-4 py-3">Ref</th>
                            <th class="px-4 py-3 text-right">Amount (RM)</th>
                            <th class="px-4 py-3 text-right">Sumbangan (RM)</th>
                            <th class="px-4 py-3">Year</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($legacyPayments as $legacy)
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $legacy->paid_at?->format('d M Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $legacy->payment_reference ?: '-' }}</td>
                                <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((float) $legacy->amount_paid, 2) }}</td>
                                <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((float) $legacy->donation_amount, 2) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $legacy->source_year }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-zinc-500">No historical paid record imported yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Access / Login Log</h2>
            <p class="text-xs text-zinc-500">Log permintaan TAC dan status log masuk parent berkaitan keluarga ini.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Requested At</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Linked User</th>
                            <th class="px-4 py-3">Channel</th>
                            <th class="px-4 py-3">Attempts</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Used At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($accessLogs as $log)
                            @php
                                $logStatus = $log->used_at
                                    ? 'Used'
                                    : (($log->expires_at && $log->expires_at->isPast()) ? 'Expired' : 'Pending');
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->created_at?->format('d M Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->phone ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-600">
                                    @if ($log->user_id)
                                        @php $linkedUser = $linkedParents->firstWhere('id', $log->user_id); @endphp
                                        <div>{{ $linkedUser?->name ?: 'Parent User #'.$log->user_id }}</div>
                                        <div class="text-xs">{{ $linkedUser?->email ?: '-' }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ strtoupper((string) $log->channel) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ (int) $log->attempts }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $logStatus === 'Used' ? 'bg-emerald-100 text-emerald-700' : ($logStatus === 'Expired' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $logStatus }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->used_at?->format('d M Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-zinc-500">No TAC access/login log found for this family yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
