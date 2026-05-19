<x-layouts::app :title="__('WhatsApp Queue')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">System Queue</p>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900">WhatsApp Queue</h1>
                    <p class="mt-1 text-sm text-zinc-600">Monitor pending, sending, sent, and failed class payment reports.</p>
                </div>
                <a href="{{ route('teacher.class-progress') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100">
                    Back to Class Progress
                </a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ([
                    ['Pending', $queueDashboard['pending'] ?? 0],
                    ['Scheduled', $queueDashboard['scheduled'] ?? 0],
                    ['Sending', $queueDashboard['sending'] ?? 0],
                    ['Sent Today', $queueDashboard['sent_today'] ?? 0],
                    ['Failed Today', $queueDashboard['failed_today'] ?? 0],
                ] as [$label, $value])
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            @if (! empty($queueDashboard['pending_warning']) || ! empty($queueDashboard['processor_warning']) || ! empty($queueDashboard['shared_session_note']))
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
                    @if (! empty($queueDashboard['shared_session_note']))
                        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">
                            {{ $queueDashboard['shared_session_note'] }}
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="flex flex-wrap gap-2 border-b border-zinc-200 px-5 py-4">
                @foreach ([
                    'all' => 'All',
                    'pending' => 'Pending',
                    'scheduled' => 'Scheduled',
                    'sending' => 'Sending',
                    'sent' => 'Sent',
                    'failed' => 'Failed',
                ] as $filterValue => $filterLabel)
                    <a
                        href="{{ route('admin.whatsapp-queue.index', ['status' => $filterValue]) }}"
                        class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusFilter === $filterValue ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-100' }}"
                    >
                        {{ $filterLabel }}
                    </a>
                @endforeach
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm text-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">Queued At</th>
                            <th class="px-5 py-3">Scheduled At</th>
                            <th class="px-5 py-3">Sent At</th>
                            <th class="px-5 py-3">Delay / Waiting</th>
                            <th class="px-5 py-3">Batch ID</th>
                            <th class="px-5 py-3">Part Order</th>
                            <th class="px-5 py-3">Class</th>
                            <th class="px-5 py-3">Teacher</th>
                            <th class="px-5 py-3">Part</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Queued By</th>
                            <th class="px-5 py-3">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($messages as $message)
                            @php
                                $displayStatus = $message->status === 'pending' && $message->scheduled_at?->isFuture() ? 'scheduled' : $message->status;
                                $statusClasses = match ($displayStatus) {
                                    'sent' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'sending' => 'border-sky-200 bg-sky-50 text-sky-700',
                                    'scheduled' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                    default => 'border-amber-200 bg-amber-50 text-amber-800',
                                };
                                $waitingText = '—';
                                if ($message->status === 'sent' && $message->scheduled_at && $message->sent_at) {
                                    $waitingText = $message->scheduled_at->diffForHumans($message->sent_at, true);
                                } elseif ($message->scheduled_at && $message->scheduled_at->isFuture()) {
                                    $waitingText = 'Starts in '.$message->scheduled_at->diffForHumans(now(), true);
                                } elseif ($message->scheduled_at) {
                                    $waitingText = 'Ready since '.$message->scheduled_at->diffForHumans(now(), true);
                                }
                            @endphp
                            <tr>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ optional($message->queued_at)->format('d M Y H:i') ?: '—' }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ optional($message->scheduled_at)->format('d M Y H:i:s') ?: '—' }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ optional($message->sent_at)->format('d M Y H:i:s') ?: '—' }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ $waitingText }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ $message->queue_batch_id ?: '—' }}</td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ $message->part_order ?: '—' }}</td>
                                <td class="px-5 py-4 font-semibold text-zinc-900">{{ $message->class_name }}</td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-zinc-900">{{ $message->recipient_name }}</p>
                                    <p class="text-xs text-zinc-500">{{ $message->recipient_phone }}</p>
                                </td>
                                <td class="px-5 py-4 text-xs text-zinc-600">
                                    {{ $message->message_part }}
                                    <span class="block text-zinc-400">{{ $message->message_segment }}/{{ $message->segment_count }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClasses }}">
                                        {{ ucfirst($displayStatus) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-zinc-600">{{ $message->queuedBy?->name ?: 'System' }}</td>
                                <td class="px-5 py-4 text-xs text-rose-600">{{ $message->error_message ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-5 py-8 text-center text-sm text-zinc-500">No WhatsApp queue messages found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
