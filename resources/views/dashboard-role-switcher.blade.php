<x-layouts::app :title="__('Choose Portal')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">Welcome, Cikgu / Parent</p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-zinc-900">Choose your space for this session</h1>
            <p class="mt-2 max-w-2xl text-sm text-zinc-600">You have more than one role on this account. Open Parent Space to view your own linked children and payments, or Teacher Space to continue with teacher or staff tools.</p>
        </section>

        <div class="grid gap-5 md:grid-cols-2">
            @foreach (($roleCards ?? []) as $card)
                <form method="POST" action="{{ route('portal-space.switch') }}">
                    @csrf
                    <input type="hidden" name="space" value="{{ $card['space'] ?? 'teacher' }}">
                    <button type="submit" class="w-full rounded-3xl border border-zinc-200 bg-white p-6 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Portal Access</p>
                        <h2 class="mt-2 text-2xl font-bold text-zinc-900">{{ $card['title'] }}</h2>
                        <p class="mt-2 text-sm text-zinc-600">{{ $card['description'] }}</p>
                        <p class="mt-4 text-sm font-semibold text-emerald-700">Open {{ $card['title'] }}</p>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</x-layouts::app>
