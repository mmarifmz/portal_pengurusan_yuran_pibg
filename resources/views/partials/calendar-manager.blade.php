<div class="space-y-4">
    <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-emerald-500">Takwim sekolah</p>
                <h3 class="text-lg font-semibold text-zinc-900">Urus aktiviti & program</h3>
                <p class="mt-1 text-sm text-zinc-500">Tambah, kemas kini, atau padam aktiviti dalam takwim 2026.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('calendar-events.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
            @csrf
            <label class="text-xs font-semibold text-zinc-600">
                Tajuk aktiviti
                <input name="title" type="text" required class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
            </label>
            <label class="text-xs font-semibold text-zinc-600">
                Label hari
                <input name="day_label" type="text" placeholder="Jumaat / Sabtu / -" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
            </label>
            <label class="text-xs font-semibold text-zinc-600">
                Tarikh mula
                <input name="start_date" type="date" required class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
            </label>
            <label class="text-xs font-semibold text-zinc-600">
                Tarikh akhir
                <input name="end_date" type="date" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
            </label>
            <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                Perkara
                <input name="description" type="text" required class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
            </label>
            <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                Catatan
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"></textarea>
            </label>
            <div class="md:col-span-2">
                <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                    Tambah aktiviti
                </button>
            </div>
        </form>
    </div>

    <div class="space-y-3">
        @forelse ($calendarEvents as $event)
            <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h4 class="text-base font-semibold text-zinc-900">{{ $event->title }}</h4>
                        <p class="mt-1 text-sm text-zinc-500">
                            {{ $event->start_date->format('d/m/Y') }}
                            @if ($event->end_date && ! $event->end_date->isSameDay($event->start_date))
                                – {{ $event->end_date->format('d/m/Y') }}
                            @endif
                            @if ($event->day_label)
                                <span class="ml-2">{{ $event->day_label }}</span>
                            @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('calendar-events.destroy', $event) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                            Padam
                        </button>
                    </form>
                </div>

                <form method="POST" action="{{ route('calendar-events.update', $event) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <label class="text-xs font-semibold text-zinc-600">
                        Tajuk aktiviti
                        <input name="title" type="text" required value="{{ $event->title }}" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
                    </label>
                    <label class="text-xs font-semibold text-zinc-600">
                        Label hari
                        <input name="day_label" type="text" value="{{ $event->day_label }}" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
                    </label>
                    <label class="text-xs font-semibold text-zinc-600">
                        Tarikh mula
                        <input name="start_date" type="date" required value="{{ $event->start_date->format('Y-m-d') }}" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
                    </label>
                    <label class="text-xs font-semibold text-zinc-600">
                        Tarikh akhir
                        <input name="end_date" type="date" value="{{ $event->end_date?->format('Y-m-d') }}" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
                    </label>
                    <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                        Perkara
                        <input name="description" type="text" required value="{{ $event->description }}" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" />
                    </label>
                    <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                        Catatan
                        <textarea name="notes" rows="2" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">{{ $event->notes }}</textarea>
                    </label>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-2xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                            Simpan perubahan
                        </button>
                    </div>
                </form>
            </div>
        @empty
            <div class="rounded-3xl border border-zinc-200 bg-white p-5 text-sm text-zinc-500 shadow-sm">
                Tiada aktiviti kalendar lagi.
            </div>
        @endforelse
    </div>
</div>
