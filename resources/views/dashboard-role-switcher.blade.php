<x-layouts::app :title="__('Choose Portal')">
    <div class="grid gap-5 md:grid-cols-2">
        @foreach (($roleCards ?? []) as $card)
            <a href="{{ $card['url'] }}" class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Portal Access</p>
                <h2 class="mt-2 text-2xl font-bold text-zinc-900">{{ $card['title'] }}</h2>
                <p class="mt-2 text-sm text-zinc-600">{{ $card['description'] }}</p>
                <p class="mt-4 text-sm font-semibold text-emerald-700">Open portal</p>
            </a>
        @endforeach
    </div>
</x-layouts::app>
