<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Student import</h2>
                <p class="text-sm text-gray-500">Paste your kod keluarga, kelas, dan nama murid per baris (CSV style). Use comma or pipe delimiter straight from the spreadsheet export.</p>
            </div>
            <a href="{{ route('teacher.dashboard') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-1 text-sm font-medium text-gray-700 hover:bg-gray-100">
                &larr; Back to dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="rounded-2xl border border-dashed border-gray-200 bg-gradient-to-r from-emerald-50 to-white p-6 shadow-sm">
                <p class="text-sm text-gray-600">Family code is generated automatically by prefixing the provided school code (default SSP) to the kod keluarga from your raw data. The import also records duplicates and marks them so you can review later.</p>
                <p class="mt-2 text-xs text-gray-500">Example row: <span class="font-medium">1,6 ALAMANDA,MUHAMMAD ARJUNA AMANI BIN MOHD HELMI</span></p>
                <p class="mt-1 text-xs text-gray-500">Pick the delimiter that matches your CSV file before hitting Import.</p>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                @if (session('student_import_message'))
                    <div class="rounded-lg border border-emerald-200/70 bg-emerald-50 p-4 text-sm text-emerald-800">
                        {{ session('student_import_message') }}
                    </div>
                @endif

                <form action="{{ route('students.import') }}" method="POST" class="mt-4 space-y-4">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="school_code" class="text-sm font-medium text-gray-700">School code</label>
                            <input id="school_code" name="school_code" type="text" maxlength="6" value="{{ old('school_code', 'SSP') }}" class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="SSP" />
                            <p class="text-xs text-gray-500 mt-1">The prefix that will be added to every family code (e.g. SSP, TKL, etc.).</p>
                        </div>
                        <div>
                            <label for="delimiter" class="text-sm font-medium text-gray-700">Delimiter</label>
                            <select id="delimiter" name="delimiter" class="mt-1 block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900">
                                <option value="comma" {{ old('delimiter') === 'pipe' ? '' : 'selected' }}>Comma (,)</option>
                                <option value="pipe" {{ old('delimiter') === 'pipe' ? 'selected' : '' }}>Pipe (|)</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Choose the character used between the columns in your import.</p>
                        </div>
                    </div>

                    <div>
                        <label for="bulk_rows" class="text-sm font-medium text-gray-700">Kod keluarga / Kelas / Nama murid</label>
                        <textarea id="bulk_rows" name="bulk_rows" rows="8" class="mt-1 block w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="1,6 ALAMANDA,MUHAMMAD ARJUNA AMANI BIN MOHD HELMI">{{ old('bulk_rows') }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">Each line becomes one student record. The first column is kod keluarga (numeric), then the class name, then the student full name.</p>
                        @error('bulk_rows')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center rounded-full bg-emerald-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">Import students</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
