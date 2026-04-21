<x-layouts::app :title="__('School Calendar')" class="space-y-6">
    @php
        $isParent = auth()->user()?->isParent();
        $calendarTitle = $isParent
            ? 'Aktiviti & program semasa'
            : "Aktiviti semasa + bilangan bayaran harian ({$selectedDashboardYear})";
        $calendarDescription = $isParent
            ? 'Panduan aktiviti sekolah, kurikulum, dan kokurikulum sepanjang tahun'
            : 'Angka hijau dalam hari menunjukkan jumlah keluarga yang sudah bayar pada tarikh tersebut.';
        $calendarPaidCounts = $isParent ? [] : $calendarPaidCountByDate;
    @endphp

    <div class="rounded-3xl border border-zinc-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('school-calendar') }}" class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">Dashboard filter</p>
                <h3 class="text-lg font-semibold text-zinc-900">Data year selector</h3>
            </div>
            <div class="flex items-end gap-2">
                <label class="text-xs font-semibold text-zinc-600">
                    Tahun data
                    <select name="dashboard_year" onchange="this.form.submit()" class="mt-1 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        @foreach ($dashboardYearOptions as $yearOption)
                            <option value="{{ $yearOption }}" @selected((int) $yearOption === (int) $selectedDashboardYear)>{{ $yearOption }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </form>
    </div>

    <div class="grid gap-4">
        @include('partials.parent-calendar', [
            'calendarEvents' => $calendarEvents,
            'paidCountByDate' => $calendarPaidCounts,
            'calendarBlockLabel' => 'Takwim sekolah',
            'calendarBlockTitle' => $calendarTitle,
            'calendarBlockDescription' => $calendarDescription,
        ])
    </div>
</x-layouts::app>
