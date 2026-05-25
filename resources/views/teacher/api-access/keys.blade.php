<x-layouts::app :title="__('API Key Management')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">API Access</p>
                    <h1 class="mt-1 text-2xl font-bold text-zinc-900">API Key Management</h1>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-600">Generate, revoke, or regenerate your API key for PIBG payment-status search.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if (! $apiKey || ! $apiKey->isActive())
                        <form method="POST" action="{{ route('teacher.api-access.generate') }}">
                            @csrf
                            <button class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">Generate Key</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('teacher.api-access.regenerate') }}" onsubmit="return confirm('Regenerate API key? The current key will stop working immediately.');">
                            @csrf
                            <button class="inline-flex items-center rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">Regenerate</button>
                        </form>
                        <form method="POST" action="{{ route('teacher.api-access.revoke') }}" onsubmit="return confirm('Revoke this API key?');">
                            @csrf
                            <button class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100">Revoke</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</p>
                    @php
                        $statusClasses = $apiKey?->isActive()
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-zinc-300 bg-zinc-200 text-zinc-700';
                    @endphp
                    <span class="mt-2 inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                        {{ $apiKey?->isActive() ? 'Active' : 'Revoked / Not Generated' }}
                    </span>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Masked Key</p>
                    <p class="mt-1 break-all font-mono text-sm font-semibold text-zinc-900">{{ $apiKey?->maskedKey() ?? '-' }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Last Used</p>
                    <p class="mt-1 text-lg font-bold text-zinc-900">{{ $apiKey?->last_used_at?->format('d M Y H:i') ?? '-' }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Total Calls</p>
                    <p class="mt-1 text-lg font-bold text-zinc-900">{{ number_format((int) ($apiKey?->total_calls ?? 0)) }}</p>
                </div>
            </div>

            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <span class="font-semibold">API key is shown once only.</span> Please copy and store it safely.
            </div>

            @if ($plainKey)
                <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm font-semibold text-blue-900">Plain key shown once</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input readonly value="{{ $plainKey }}" class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2 font-mono text-sm text-zinc-900" data-copy-source="plain-api-key" />
                        <button type="button" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-800" data-copy-target="plain-api-key">Copy</button>
                    </div>
                </div>
            @elseif ($apiKey)
                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <input readonly value="{{ $apiKey->maskedKey() }}" class="w-full rounded-xl border border-zinc-300 bg-zinc-50 px-3 py-2 font-mono text-sm text-zinc-700" data-copy-source="masked-api-key" />
                    <button type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100" data-copy-target="masked-api-key">Copy</button>
                </div>
            @endif
        </section>
    </div>

    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => {
            button.addEventListener('click', async () => {
                const source = document.querySelector(`[data-copy-source="${button.dataset.copyTarget}"]`);
                if (! source) return;
                await navigator.clipboard.writeText(source.value);
                button.textContent = 'Copied';
                setTimeout(() => button.textContent = 'Copy', 1400);
            });
        });
    </script>
</x-layouts::app>
