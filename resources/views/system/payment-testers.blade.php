<x-layouts::app :title="__('Payment Tester Users')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
            <h1 class="text-2xl font-bold text-zinc-900">Payment Tester Users</h1>
            <p class="text-sm text-zinc-500">Manage parent accounts allowed to run RM1 payment testing flow.</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @unless ($hasPaymentTesterColumn)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Payment tester setup is incomplete on this database. Run:
                <code class="rounded bg-amber-100 px-1 py-0.5 text-xs">php artisan migrate --path=database/migrations/2026_04_17_000006_add_is_payment_tester_to_users_table.php</code>
            </div>
        @endunless

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">WhatsApp Test Utility</h2>
            <p class="mt-1 text-sm text-zinc-500">Send a TAC/message test immediately from this panel.</p>

            <form method="POST" action="{{ route('system.payment-testers.whatsapp-test', ['q' => $keyword]) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                <label class="text-sm font-medium text-zinc-700">
                    Phone Number
                    <input
                        name="phone"
                        type="text"
                        value="{{ old('phone', $defaultWhatsappTestPhone) }}"
                        placeholder="60123456789"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Mode
                    <select name="mode" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        <option value="message" @selected(old('mode') === 'message')>Message</option>
                        <option value="tac" @selected(old('mode') === 'tac')>TAC</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-zinc-700 md:col-span-3">
                    Message (used when mode = Message)
                    <input
                        name="message"
                        type="text"
                        value="{{ old('message', $defaultWhatsappTestMessage) }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>
                <div class="md:col-span-3">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Send WhatsApp Test
                    </button>
                </div>
            </form>

            <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-700">
                <p class="font-semibold text-zinc-900">One-liner artisan commands</p>
                <p class="mt-2 font-mono">php artisan whatsapp:test 60123456789 --message="Ini mesej ujian dari super admin"</p>
                <p class="mt-1 font-mono">php artisan whatsapp:test 60123456789 --tac --family-code=TEST-FAMILY</p>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Payment Success WhatsApp Simulator (Test Zone)</h2>
            <p class="mt-1 text-sm text-zinc-500">Pick any successful paid parent record, then simulate the same payment-success WhatsApp message.</p>

            <form method="POST" action="{{ route('system.payment-testers.payment-success-whatsapp-test', ['q' => $keyword]) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                <label class="text-sm font-medium text-zinc-700 md:col-span-2">
                    Successful Payment Record
                    <select name="transaction_id" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" @disabled($successfulPaymentSamples->isEmpty())>
                        <option value="">Select successful parent payment</option>
                        @foreach ($successfulPaymentSamples as $sample)
                            <option value="{{ $sample->id }}" @selected((string) old('transaction_id') === (string) $sample->id)>
                                {{ ($sample->familyBilling?->family_code ?? '-') }} | {{ $sample->payer_phone }} | RM{{ number_format((float) $sample->amount, 2) }} | {{ $sample->paid_at_for_display?->format('d M Y H:i') ?? '-' }} | {{ $sample->external_order_display }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Phone Override (Optional)
                    <input
                        name="phone"
                        type="text"
                        value="{{ old('phone') }}"
                        placeholder="Leave empty to use selected parent phone"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>
                <div class="md:col-span-3">
                    <button
                        type="submit"
                        @disabled($successfulPaymentSamples->isEmpty())
                        class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Send Payment Success WhatsApp (Test)
                    </button>
                </div>
            </form>

            @if ($successfulPaymentSamples->isEmpty())
                <p class="mt-3 text-xs text-amber-700">No successful paid records available yet for simulation.</p>
            @else
                <p class="mt-3 text-xs text-zinc-600">This simulator sends message only for testing and does not update payment totals/statistics.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">WhatsApp Blast Status Test</h2>
                    <p class="mt-1 text-sm text-zinc-500">Select a class, enter the tester phone number, then queue the same class WhatsApp blast content through the real batch processor and monitor its status here.</p>
                </div>
                <a href="{{ route('admin.whatsapp-queue.index') }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                    Open Queue Page
                </a>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Pending</p>
                    <p class="mt-1 text-xl font-bold text-zinc-900">{{ $whatsappQueueDashboard['pending'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Sending</p>
                    <p class="mt-1 text-xl font-bold text-zinc-900">{{ $whatsappQueueDashboard['sending'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Sent Today</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700">{{ $whatsappQueueDashboard['sent_today'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Failed Today</p>
                    <p class="mt-1 text-xl font-bold text-rose-700">{{ $whatsappQueueDashboard['failed_today'] ?? 0 }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('system.payment-testers.whatsapp-blast-status-test', ['q' => $keyword]) }}" class="mt-5 grid gap-3 lg:grid-cols-5">
                @csrf
                <label class="text-sm font-medium text-zinc-700">
                    Billing Year
                    <input
                        name="billing_year"
                        type="number"
                        min="2000"
                        max="2100"
                        value="{{ old('billing_year', now()->year) }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>
                <label class="text-sm font-medium text-zinc-700 lg:col-span-2">
                    Class
                    <select name="class_name" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" @disabled($blastTestClassOptions->isEmpty())>
                        <option value="">Select class for blast status test</option>
                        @foreach ($blastTestClassOptions as $className)
                            <option value="{{ $className }}" @selected(old('class_name') === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Tester Phone Number
                    <input
                        name="tester_phone"
                        type="text"
                        value="{{ old('tester_phone', $defaultWhatsappTestPhone) }}"
                        placeholder="60123456789"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>
                <div class="self-end">
                    <button
                        type="submit"
                        @disabled($blastTestClassOptions->isEmpty())
                        class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Queue Blast Status Test
                    </button>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-zinc-700 lg:col-span-4">
                    <input type="hidden" name="process_now" value="0">
                    <input type="checkbox" name="process_now" value="1" @checked(old('process_now')) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                    Process queued test immediately to update sent/failed status right away
                </label>
                <p class="text-xs text-zinc-500 lg:col-span-5">
                    The selected class controls the message content. The tester phone number is used as the delivery target for this Ujian-only blast test.
                </p>
            </form>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Batch</th>
                            <th class="px-4 py-3">Classes</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Messages</th>
                            <th class="px-4 py-3">Queued By</th>
                            <th class="px-4 py-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($recentWhatsappBlastBatches as $batch)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-800">{{ $batch['batch_id'] }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ implode(', ', $batch['class_names'] ?: ['-']) }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusClasses = match ($batch['status']) {
                                            'sent' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                            'sending' => 'border-sky-200 bg-sky-50 text-sky-700',
                                            default => 'border-zinc-200 bg-zinc-50 text-zinc-700',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $statusClasses }}">{{ strtoupper($batch['status']) }}</span>
                                    <p class="mt-2 text-xs text-zinc-500">
                                        P: {{ $batch['pending_messages_count'] }} |
                                        S: {{ $batch['sending_messages_count'] }} |
                                        Sent: {{ $batch['sent_messages_count'] }} |
                                        Failed: {{ $batch['failed_messages_count'] }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ $batch['total_messages_queued'] }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $batch['queued_by_name'] }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ optional($batch['created_at'])->format('d M Y H:i:s') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No WhatsApp blast test batch found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Parent Phone Repair Utility</h2>
            <p class="mt-1 text-sm text-zinc-500">Reset a phone for fresh parent testing, or correct mistyped parent phone numbers.</p>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('system.payment-testers.parent-phone.reset', ['q' => $keyword]) }}" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-zinc-900">Reset Phone (Fresh Test)</h3>
                    <p class="mt-1 text-xs text-zinc-600">Deletes parent account, registered family phone entries, TAC/login logs for this phone.</p>
                    <label class="mt-3 block text-sm font-medium text-zinc-700">
                        Phone Number
                        <input
                            name="phone"
                            type="text"
                            value="{{ old('phone') }}"
                            placeholder="01140030076"
                            class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-zinc-700">
                        <input type="hidden" name="clear_student_phone" value="0">
                        <input type="checkbox" name="clear_student_phone" value="1" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                        Also clear this phone from students table
                    </label>
                    <div class="mt-3">
                        <button type="submit" class="inline-flex items-center rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                            Reset Phone
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('system.payment-testers.parent-phone.correct', ['q' => $keyword]) }}" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-zinc-900">Correct Mistyped Phone</h3>
                    <p class="mt-1 text-xs text-zinc-600">Use when parent keyed wrong number (e.g. missing one digit). Moves records to corrected phone.</p>
                    <label class="mt-3 block text-sm font-medium text-zinc-700">
                        Wrong Phone
                        <input
                            name="from_phone"
                            type="text"
                            value="{{ old('from_phone') }}"
                            placeholder="0114003007"
                            class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>
                    <label class="mt-3 block text-sm font-medium text-zinc-700">
                        Correct Phone
                        <input
                            name="to_phone"
                            type="text"
                            value="{{ old('to_phone') }}"
                            placeholder="01140030076"
                            class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>
                    <div class="mt-3">
                        <button type="submit" class="inline-flex items-center rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                            Correct Phone
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-700">
                <p class="font-semibold text-zinc-900">One-liner artisan commands</p>
                <p class="mt-2 font-mono">php artisan parent:test-reset 01140030076</p>
                <p class="mt-1 font-mono">php artisan parent:test-reset 01140030076 --clear-student-phone</p>
                <p class="mt-1 font-mono">php artisan parent:phone-correct 0114003007 01140030076</p>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Portal Test Invite Utility</h2>
            <p class="mt-1 text-sm text-zinc-500">Generate a 24-hour parent auto-login link by phone for testing. Uses dummy family code <span class="font-mono">{{ $portalTestFamilyCode }}</span> (billing year 2099), so it does not impact normal statistics.</p>

            <form method="POST" action="{{ route('system.payment-testers.portal-test-invite', ['q' => $keyword]) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                <label class="text-sm font-medium text-zinc-700">
                    Phone Number
                    <input
                        name="phone"
                        type="text"
                        value="{{ old('phone', $defaultWhatsappTestPhone) }}"
                        placeholder="01140030076"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Delivery
                    <select name="send_whatsapp" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        <option value="1" @selected(old('send_whatsapp', '1') === '1')>Generate + Send WhatsApp</option>
                        <option value="0" @selected(old('send_whatsapp') === '0')>Generate Link Only</option>
                    </select>
                </label>
                <div class="self-end">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Generate Test Invite
                    </button>
                </div>
            </form>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Time Left</th>
                            <th class="px-4 py-3">Sent At</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($portalTestInvites as $invite)
                            @php
                                $isUsed = $invite->used_at !== null;
                                $isExpired = ! $isUsed && $invite->expires_at && $invite->expires_at->isPast();
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-zinc-900">{{ $invite->phone }}</td>
                                <td class="px-4 py-3">
                                    @if ($isUsed)
                                        <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">Used</span>
                                    @elseif ($isExpired)
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Expired</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Active</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-700">
                                    @if ($isUsed)
                                        Used
                                    @elseif ($invite->expires_at)
                                        <span class="js-invite-timer" data-expires-at="{{ $invite->expires_at->toIso8601String() }}">-</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ $invite->sent_at?->format('d M Y H:i:s') ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ route('parent.invite.login', ['token' => $invite->token]) }}"
                                        target="_blank"
                                        class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-800 transition hover:bg-zinc-100"
                                    >
                                        Open Link
                                    </a>
                                    <form method="POST" action="{{ route('system.payment-testers.portal-test-invite', ['q' => $keyword]) }}" class="ml-2 inline">
                                        @csrf
                                        <input type="hidden" name="phone" value="{{ $invite->phone }}">
                                        <input type="hidden" name="send_whatsapp" value="1">
                                        <button type="submit" class="inline-flex items-center rounded-xl border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100">
                                            Re-Send Invite
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No portal test invites yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('system.payment-testers.index') }}" class="grid gap-3 sm:grid-cols-[1fr_auto]">
                <label class="text-sm font-medium text-zinc-700">
                    Search Parent User
                    <input
                        name="q"
                        type="text"
                        value="{{ $keyword }}"
                        placeholder="Name, email, or phone"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>
                <div class="self-end">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Search
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Tester Mode</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($parentUsers as $parentUser)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $parentUser->name ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $parentUser->email ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $parentUser->phone ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($parentUser->is_payment_tester)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Enabled</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600">Disabled</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('system.payment-testers.update', $parentUser) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_payment_tester" value="{{ $parentUser->is_payment_tester ? '0' : '1' }}">
                                        <button
                                            type="submit"
                                            @disabled(! $hasPaymentTesterColumn)
                                            class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-semibold transition {{ $parentUser->is_payment_tester ? 'border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}"
                                        >
                                            {{ $parentUser->is_payment_tester ? 'Disable RM1 Tester' : 'Enable RM1 Tester' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-zinc-500">No parent users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $parentUsers->links() }}
            </div>
        </section>
    </div>

    <script>
        (function () {
            const timerNodes = Array.from(document.querySelectorAll('.js-invite-timer'));
            if (!timerNodes.length) {
                return;
            }

            const renderTimers = () => {
                const now = Date.now();

                timerNodes.forEach((node) => {
                    const expiry = Date.parse(node.getAttribute('data-expires-at') || '');
                    if (!expiry) {
                        node.textContent = '-';
                        return;
                    }

                    const diffMs = expiry - now;
                    if (diffMs <= 0) {
                        node.textContent = 'Expired';
                        return;
                    }

                    const totalMinutes = Math.floor(diffMs / 60000);
                    const hours = Math.floor(totalMinutes / 60);
                    const minutes = totalMinutes % 60;
                    node.textContent = `${hours}h ${minutes}m`;
                });
            };

            renderTimers();
            window.setInterval(renderTimers, 30000);
        }());
    </script>
</x-layouts::app>
