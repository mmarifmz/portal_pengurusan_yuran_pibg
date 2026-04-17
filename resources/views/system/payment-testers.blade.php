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
</x-layouts::app>
