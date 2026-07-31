<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Student import</h2>
                <p class="text-sm text-gray-500">Paste each student name and class on a separate line. Use a comma or pipe between the two columns.</p>
            </div>
            <a href="{{ route('teacher.records') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-1 text-sm font-medium text-gray-700 hover:bg-gray-100">
                &larr; Back to dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="rounded-2xl border border-dashed border-gray-200 bg-gradient-to-r from-emerald-50 to-white p-6 shadow-sm">
                <p class="text-sm text-gray-600">Choose whether these students belong to new families or an existing family. New family codes start with <span class="font-semibold text-emerald-700">{{ $nextFamilyCode }}</span>; siblings reuse the family code you select.</p>
                <p class="mt-2 text-xs text-gray-500">Example row: <span class="font-medium">MUHAMMAD ARJUNA AMANI BIN MOHD HELMI,6 ALAMANDA</span></p>
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

                    @php($selectedImportMode = old('import_mode', 'new'))
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Student family</legend>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-emerald-400">
                                <input type="radio" name="import_mode" value="new" class="mt-1 text-emerald-600 focus:ring-emerald-500" {{ $selectedImportMode === 'new' ? 'checked' : '' }} />
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">New family</span>
                                    <span class="mt-1 block text-xs text-gray-500">Generate one new family code for every student row.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-emerald-400">
                                <input type="radio" name="import_mode" value="existing" class="mt-1 text-emerald-600 focus:ring-emerald-500" {{ $selectedImportMode === 'existing' ? 'checked' : '' }} />
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">Existing family / sibling</span>
                                    <span class="mt-1 block text-xs text-gray-500">Reuse one existing family code for all student rows.</span>
                                </span>
                            </label>
                        </div>
                        @error('import_mode')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div data-existing-family-fields class="{{ $selectedImportMode === 'existing' ? '' : 'hidden' }} rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
                        <label for="existing_family_code" class="text-sm font-medium text-gray-700">Existing family code</label>
                        <input
                            id="existing_family_code"
                            name="existing_family_code"
                            type="text"
                            list="existing-family-options"
                            value="{{ old('existing_family_code') }}"
                            autocomplete="off"
                            class="mt-1 block w-full rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm text-gray-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                            placeholder="Search family code or select from the list"
                        />
                        <datalist id="existing-family-options">
                            @foreach ($existingFamilies as $family)
                                <option value="{{ $family['family_code'] }}">{{ $family['students'] }}</option>
                            @endforeach
                        </datalist>
                        <p class="mt-1 text-xs text-gray-500">Search using the sibling’s family code. The student names shown help you confirm the correct family.</p>
                        @error('existing_family_code')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="max-w-md">
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
                        <label for="bulk_rows" class="text-sm font-medium text-gray-700">Nama murid / Kelas</label>
                        <textarea id="bulk_rows" name="bulk_rows" rows="8" class="mt-1 block w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="MUHAMMAD ARJUNA AMANI BIN MOHD HELMI,6 ALAMANDA">{{ old('bulk_rows') }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">Each line becomes one student record: student full name first, followed by the class name. The family code follows the option selected above.</p>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modeInputs = document.querySelectorAll('input[name="import_mode"]');
            const existingFamilyFields = document.querySelector('[data-existing-family-fields]');
            const existingFamilyInput = document.getElementById('existing_family_code');

            const updateFamilyFields = () => {
                const selectedMode = document.querySelector('input[name="import_mode"]:checked')?.value;
                const usesExistingFamily = selectedMode === 'existing';

                existingFamilyFields?.classList.toggle('hidden', ! usesExistingFamily);

                if (existingFamilyInput) {
                    existingFamilyInput.required = usesExistingFamily;
                }
            };

            modeInputs.forEach((input) => input.addEventListener('change', updateFamilyFields));
            updateFamilyFields();
        });
    </script>
</x-app-layout>
