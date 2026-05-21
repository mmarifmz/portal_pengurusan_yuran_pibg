<x-layouts::app :title="__('Teacher Payment Notifications')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">System Admin &gt; WhatsApp Queue</p>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900">Teacher Payment Notifications</h1>
                    <p class="mt-1 text-sm text-zinc-600">Audit dan urus penghantaran resit bayaran kepada guru kelas.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.whatsapp-queue.index') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100">
                        Class Report Queue
                    </a>
                </div>
            </div>

            @if (session('status'))
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                @foreach ([
                    ['Total Queued', $kpis['queued'] ?? 0],
                    ['Processing', $kpis['processing'] ?? 0],
                    ['Sent', $kpis['sent'] ?? 0],
                    ['Failed', $kpis['failed'] ?? 0],
                    ['Retrying', $kpis['retrying'] ?? 0],
                    ['Cancelled', $kpis['cancelled'] ?? 0],
                ] as [$label, $value])
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <form method="GET" class="grid gap-3 lg:grid-cols-5">
                <label class="text-xs font-semibold text-zinc-600">
                    Status
                    <select name="status" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900">
                        <option value="">Semua</option>
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Class
                    <select name="class_name" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900">
                        <option value="">Semua</option>
                        @foreach ($classOptions as $classOption)
                            <option value="{{ $classOption }}" @selected(request('class_name') === $classOption)>{{ $classOption }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Teacher
                    <input type="text" name="teacher" value="{{ request('teacher') }}" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm text-zinc-900" placeholder="Nama atau telefon" />
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Date Range
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm text-zinc-900" />
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm text-zinc-900" />
                    </div>
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Order ID
                    <input type="text" name="order_id" value="{{ request('order_id') }}" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm text-zinc-900" placeholder="Cari order ID" />
                </label>

                <div class="lg:col-span-5 flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700">
                        Tapis
                    </button>
                    <a href="{{ route('admin.whatsapp-queue.teacher-payment-notifications.index') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-100">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm text-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">Queued Time</th>
                            <th class="px-5 py-3">Teacher</th>
                            <th class="px-5 py-3">Phone</th>
                            <th class="px-5 py-3">Class</th>
                            <th class="px-5 py-3">Student</th>
                            <th class="px-5 py-3">Order ID</th>
                            <th class="px-5 py-3">PIBG Amount</th>
                            <th class="px-5 py-3">Donation Amount</th>
                            <th class="px-5 py-3">Total Amount</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Attempt Count</th>
                            <th class="px-5 py-3">Sent At</th>
                            <th class="px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($notifications as $notification)
                            @php
                                $statusClasses = match ($notification->status) {
                                    'sent' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'processing' => 'border-sky-200 bg-sky-50 text-sky-700',
                                    'retrying' => 'border-amber-200 bg-amber-50 text-amber-800',
                                    'cancelled' => 'border-zinc-300 bg-zinc-100 text-zinc-700',
                                    default => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                };
                            @endphp
                            <tr>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ optional($notification->queued_at)->format('d M Y H:i') ?: '—' }}</td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-zinc-900">{{ $notification->teacher_name ?: '—' }}</p>
                                </td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ $notification->teacher_phone ?: '—' }}</td>
                                <td class="px-5 py-4 font-semibold text-zinc-900">{{ $notification->class_name ?: '—' }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ $notification->student_name ?: ($notification->student?->full_name ?: '—') }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ $notification->order_id ?: '—' }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">RM {{ number_format((float) $notification->pibg_amount, 2) }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">RM {{ number_format((float) $notification->donation_amount, 2) }}</td>
                                <td class="px-5 py-4 text-xs font-semibold text-zinc-900">RM {{ number_format((float) $notification->total_amount, 2) }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClasses }}">
                                        {{ ucfirst($notification->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ $notification->attempt_count }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ optional($notification->sent_at)->format('d M Y H:i:s') ?: '—' }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" data-message-trigger data-message="{{ e($notification->message_body ?? '') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                                            View Message
                                        </button>

                                        @if ($notification->status === \App\Models\TeacherPaymentNotification::STATUS_FAILED)
                                            <form method="POST" action="{{ route('admin.whatsapp-queue.teacher-payment-notifications.retry', $notification) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                                    Retry Failed
                                                </button>
                                            </form>
                                        @endif

                                        @if (in_array($notification->status, [\App\Models\TeacherPaymentNotification::STATUS_QUEUED, \App\Models\TeacherPaymentNotification::STATUS_RETRYING, \App\Models\TeacherPaymentNotification::STATUS_FAILED], true))
                                            <form method="POST" action="{{ route('admin.whatsapp-queue.teacher-payment-notifications.cancel', $notification) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                    Mark Cancelled
                                                </button>
                                            </form>
                                        @endif

                                        @if ($notification->receipt_url)
                                            <a href="{{ $notification->receipt_url }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                                                View Receipt
                                            </a>
                                        @endif

                                        @if ($notification->family?->family_code)
                                            <a href="{{ route('teacher.records.family', $notification->family->family_code) }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                                                View Related Family
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-5 py-8 text-center text-sm text-zinc-500">Tiada teacher payment notification ditemui.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-200 px-5 py-4">
                {{ $notifications->links() }}
            </div>
        </section>
    </div>

    <div id="teacherPaymentMessageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6">
        <div class="w-full max-w-3xl rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">WhatsApp Message</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Teacher Payment Notification</h3>
                </div>
                <button type="button" data-close-message-modal class="rounded-xl border border-zinc-300 px-3 py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                    Tutup
                </button>
            </div>
            <pre id="teacherPaymentMessageBody" class="mt-4 max-h-[60vh] overflow-auto rounded-2xl bg-zinc-950 p-4 text-xs leading-6 text-emerald-100"></pre>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('teacherPaymentMessageModal');
            const messageBody = document.getElementById('teacherPaymentMessageBody');

            document.querySelectorAll('[data-message-trigger]').forEach((button) => {
                button.addEventListener('click', function () {
                    messageBody.textContent = this.dataset.message || 'Tiada kandungan mesej.';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            document.querySelectorAll('[data-close-message-modal]').forEach((button) => {
                button.addEventListener('click', function () {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                });
            });

            modal?.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            });
        });
    </script>
</x-layouts::app>
