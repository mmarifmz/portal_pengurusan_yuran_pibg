<x-layouts::app :title="__('API Key Registry')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
            <h1 class="mt-1 text-2xl font-bold text-zinc-900">API Key Registry</h1>
            <p class="mt-2 max-w-2xl text-sm text-zinc-600">Review all teacher API keys and revoke active keys when needed.</p>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Teacher</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Key</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3">Last Used</th>
                            <th class="px-4 py-3">Calls</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($apiKeys as $apiKey)
                            @php
                                $keyStatusClass = $apiKey->isActive()
                                    ? 'border-blue-200 bg-blue-50 text-blue-700'
                                    : 'border-zinc-300 bg-zinc-200 text-zinc-700';
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $apiKey->teacher?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $apiKey->teacher?->email ?? '-' }}</td>
                                <td class="px-4 py-3 font-mono text-zinc-700">{{ $apiKey->maskedKey() }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $keyStatusClass }}">{{ $apiKey->isActive() ? 'Active' : 'Revoked' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700">{{ $apiKey->created_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700">{{ $apiKey->last_used_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ number_format($apiKey->total_calls) }}</td>
                                <td class="px-4 py-3">
                                    @if ($apiKey->isActive())
                                        <form method="POST" action="{{ route('admin.api-monitor.keys.revoke', $apiKey) }}" onsubmit="return confirm('Revoke this teacher API key?');">
                                            @csrf
                                            <button class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">Revoke</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-zinc-500">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-zinc-500">No teacher API keys found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $apiKeys->links() }}</div>
        </section>
    </div>
</x-layouts::app>
