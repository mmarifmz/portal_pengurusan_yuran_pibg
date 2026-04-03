<div class="rounded-3xl border border-zinc-100 bg-white p-6 shadow-md dark:border-zinc-800 dark:bg-neutral-900/70">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wide text-emerald-500">Admin import</p>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Load kod keluarga</h2>
        </div>
        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200">
            {{ now()->format('d M Y') }}
        </span>
    </div>

    @if (session('student_import_message'))
        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700 dark:border-emerald-600/60 dark:bg-emerald-950/60 dark:text-emerald-200">
            {{ session('student_import_message') }}
        </div>
    @endif

    <form action="{{ route('students.import') }}" method="POST" class="mt-5 space-y-4">
        @csrf

        <div class="grid gap-3 md:grid-cols-2">
            <label for="school_code" class="flex flex-col text-sm font-medium text-zinc-700 dark:text-zinc-200">
                School code
                <input
                    id="school_code"
                    name="school_code"
                    type="text"
                    maxlength="6"
                    value="{{ old('school_code', $schoolCode ?? config('pibg.school_code', 'SSP')) }}"
                    class="mt-1 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-zinc-700 dark:bg-neutral-950 dark:text-white"
                    placeholder="SSP"
                />
            </label>
            <label for="bulk_rows_info" class="flex flex-col text-sm font-medium text-zinc-700 dark:text-zinc-200">
                Format
                <span id="bulk_rows_info" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    kod keluarga | kelas | nama murid | student_no (optional)
                </span>
            </label>
        </div>

        <label for="bulk_rows" class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
            Paste rows
        </label>
        <textarea
            id="bulk_rows"
            name="bulk_rows"
            rows="6"
            class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-4 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-zinc-700 dark:bg-neutral-950 dark:text-white dark:placeholder:text-zinc-500"
            placeholder="1 | 6 ALAMANDA | Muhammad Ali | 4A-0001"
        >{{ old('bulk_rows') }}</textarea>
        @error('bulk_rows')
            <p class="text-xs text-rose-600">{{ $message }}</p>
        @enderror

        <div class="flex gap-3">
            <button
                type="submit"
                class="flex-1 rounded-2xl bg-zinc-900 px-4 py-3 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-zinc-800 dark:bg-emerald-500 dark:text-zinc-950"
            >
                Simpan data murid
            </button>
            <button
                type="reset"
                class="rounded-2xl border border-zinc-200 px-4 py-3 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-200"
            >
                Kosongkan
            </button>
        </div>
    </form>
</div>
