<x-layouts::app :title="__('Family Profile')">
    @php
        $allPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'all']);
        $successfulPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'successful']);
        $pendingPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'pending']);
        $cancelledPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'cancelled']);
        $exportPaymentsUrl = route('teacher.records.family.payments.export', ['familyCode' => $familyCode, 'payment_status' => $paymentFilter]);
        $updateParentProfileUrl = route('teacher.records.family.parent-profile.update', ['familyCode' => $familyCode]);
    @endphp

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

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
            <div id="update-parent-profile" class="mb-4 rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                <h3 class="text-sm font-semibold text-zinc-900">Update Family Parent Profile</h3>
                <p class="mt-1 text-xs text-zinc-500">Teacher boleh kemas kini nama dan email parent untuk semua murid di family code ini.</p>
                <form method="POST" action="{{ $updateParentProfileUrl }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <label class="text-xs font-semibold text-zinc-600">
                        Parent Name
                        <input
                            type="text"
                            name="parent_name"
                            value="{{ old('parent_name', $parentProfileName ?? '') }}"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                        @error('parent_name')
                            <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <label class="text-xs font-semibold text-zinc-600">
                        Parent Email
                        <input
                            type="email"
                            name="parent_email"
                            value="{{ old('parent_email', $parentProfileEmail ?? '') }}"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                        @error('parent_email')
                            <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <p class="sm:col-span-2 text-[11px] text-zinc-500">Boleh kemas kini satu medan sahaja (nama atau email), atau kedua-duanya sekali.</p>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                            Save Parent Profile
                        </button>
                    </div>
                </form>

                @if (! empty($socialTagLabels))
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-zinc-600">Student Social Tags</h4>
                        <p class="mt-1 text-[11px] text-zinc-500">Super admin boleh ubah label tag dari menu Portal SEO & Branding.</p>
                        @error('tags')
                            <p class="mt-2 text-[11px] font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                        <div class="mt-3 space-y-2">
                            @foreach ($students as $student)
                                <form method="POST" action="{{ route('teacher.records.students.tags.update', ['student' => $student]) }}" class="js-tag-form rounded-lg border border-zinc-200 bg-zinc-50 p-2" data-student-id="{{ $student->id }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-xs font-semibold text-zinc-900">{{ $student->full_name }}</p>
                                            <p class="text-[11px] text-zinc-500">{{ $student->class_name ?: '-' }}{{ $student->student_no ? ' / '.$student->student_no : '' }}</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            @foreach ($socialTagLabels as $tagField => $tagLabel)
                                                <label class="inline-flex items-center gap-1 text-xs font-medium text-zinc-700">
                                                    <input type="checkbox" name="{{ $tagField }}" value="1" @checked((bool) data_get($student, $tagField))>
                                                    {{ $tagLabel }}
                                                </label>
                                            @endforeach
                                            <span class="js-tag-status text-[11px] font-medium text-zinc-500" aria-live="polite"></span>
                                            <button type="submit" class="js-tag-submit inline-flex items-center rounded-lg border border-zinc-300 bg-white px-2 py-1 text-[11px] font-semibold text-zinc-700 transition hover:bg-zinc-100">
                                                Save Tags
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

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
                <a href="{{ $pendingPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'pending' ? 'border-amber-300 bg-amber-100 text-amber-800' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">Pending</a>
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
                            <th class="px-4 py-3 text-right">Sumbangan (RM)</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Return Status</th>
                            <th class="px-4 py-3">Sumbangan Intention</th>
                            <th class="px-4 py-3">Payer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($paymentHistory as $payment)
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->paid_at_for_display?->format('d M Y H:i') ?? $payment->created_at_for_display?->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $payment->external_order_display }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->provider_bill_code ?: '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-zinc-900">{{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((float) ($portalDonationByPaymentId[$payment->id] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ ucfirst((string) $payment->status) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->return_status ? ucfirst((string) $payment->return_status) : '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->donation_intention ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-600">
                                    <div>{{ $payment->payer_name ?: '-' }}</div>
                                    <div class="text-xs">{{ $payment->payer_email ?: $payment->payer_phone ?: '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-6 text-center text-zinc-500">No payment history recorded for the selected filter.</td>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const forms = Array.from(document.querySelectorAll('.js-tag-form'));
    if (!forms.length) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const setStatus = (form, message, tone = 'neutral') => {
        const status = form.querySelector('.js-tag-status');
        if (!status) return;

        status.textContent = message;
        status.classList.remove('text-zinc-500', 'text-emerald-600', 'text-rose-600');
        if (tone === 'success') status.classList.add('text-emerald-600');
        else if (tone === 'error') status.classList.add('text-rose-600');
        else status.classList.add('text-zinc-500');
    };

    const toggleBusy = (form, busy) => {
        const submit = form.querySelector('.js-tag-submit');
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');

        if (submit) {
            submit.disabled = busy;
            submit.textContent = busy ? 'Saving...' : 'Save Tags';
        }

        checkboxes.forEach((checkbox) => {
            checkbox.disabled = busy;
        });
    };

    const saveForm = async (form) => {
        try {
            toggleBusy(form, true);
            setStatus(form, 'Saving...', 'neutral');

            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(payload.message || 'Unable to save tags.');
            }

            setStatus(form, payload.message || 'Saved', 'success');
            window.setTimeout(() => setStatus(form, ''), 1400);
        } catch (error) {
            setStatus(form, error.message || 'Save failed', 'error');
        } finally {
            toggleBusy(form, false);
        }
    };

    forms.forEach((form) => {
        let debounceTimer = null;

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            window.clearTimeout(debounceTimer);
            saveForm(form);
        });

        form.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(() => saveForm(form), 220);
            });
        });
    });
});
</script>
@endpush

</x-layouts::app>
