<x-layouts::app :title="__('Review Duplicate Record')">
    <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Duplicate Review</p>
                <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-zinc-900">Review student record before deletion</h1>
                <p class="mt-2 max-w-3xl text-sm text-zinc-600">
                    Verify the matching records below first. Only delete the record that is truly duplicated.
                </p>
            </div>

            <a
                href="{{ route('teacher.records') }}"
                class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50"
            >
                Back to records
            </a>
        </div>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-amber-800">Selected duplicate candidate</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-amber-700">Student no</p>
                    <p class="mt-1 font-semibold text-zinc-900">{{ $student->student_no }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-amber-700">Family code</p>
                    <p class="mt-1 font-semibold text-zinc-900">{{ $student->family_code ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-amber-700">Class</p>
                    <p class="mt-1 font-semibold text-zinc-900">{{ $student->class_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-amber-700">Status</p>
                    <p class="mt-1 font-semibold text-zinc-900">{{ ucfirst($student->status) }}</p>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-xs uppercase tracking-wide text-amber-700">Student name</p>
                <p class="mt-1 text-lg font-bold text-zinc-900">{{ $student->full_name }}</p>
            </div>
            @if ($keptFamilyStudents->isNotEmpty())
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Family group to keep</p>
                    <p class="mt-2 text-sm text-zinc-700">
                        These record(s) will remain after the duplicate family group is removed.
                    </p>
                    <div class="mt-3 space-y-2">
                        @foreach ($keptFamilyStudents as $keptStudent)
                            <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm">
                                <div>
                                    <span class="font-semibold text-zinc-900">{{ $keptStudent->full_name }}</span>
                                    <span class="ml-2 text-xs text-zinc-600">({{ $keptStudent->family_code ?? 'No family code' }})</span>
                                </div>
                                <span class="font-mono text-xs text-zinc-600">{{ $keptStudent->student_no }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="mt-4 rounded-2xl border border-amber-200 bg-white/80 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Family group to remove</p>
                <p class="mt-2 text-sm text-zinc-700">
                    Deleting this duplicate will remove family group
                    <span class="font-semibold text-zinc-900">{{ $student->family_code ?? 'without family code' }}</span>
                    and the child record(s) under that same family code.
                </p>
                <div class="mt-3 space-y-2">
                    @foreach ($selectedFamilyStudents as $familyStudent)
                        <div class="flex items-center justify-between rounded-xl border border-amber-100 bg-amber-50/70 px-3 py-2 text-sm">
                            <span class="font-semibold text-zinc-900">{{ $familyStudent->full_name }}</span>
                            <span class="font-mono text-xs text-zinc-600">{{ $familyStudent->student_no }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-5 py-4">
                <h2 class="text-lg font-bold text-zinc-900">Matching records in this duplicate group</h2>
                <p class="mt-1 text-sm text-zinc-600">These records share the same student name and class.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm text-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">Student No</th>
                            <th class="px-5 py-3">Family Code</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Class</th>
                            <th class="px-5 py-3">Parent</th>
                            <th class="px-5 py-3">Duplicate flag</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @foreach ($matchingStudents as $match)
                            <tr class="{{ $match->is($student) ? 'bg-amber-50/70' : '' }}">
                                <td class="px-5 py-4 font-semibold text-zinc-900">{{ $match->student_no }}</td>
                                <td class="px-5 py-4">{{ $match->family_code ?? '—' }}</td>
                                <td class="px-5 py-4 font-medium text-zinc-900">{{ $match->full_name }}</td>
                                <td class="px-5 py-4">{{ $match->class_name ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    <p>{{ $match->parent_name ?? 'No parent on file' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $match->parent_phone ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    @if ($match->is_duplicate)
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700">
                                            Marked duplicate
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                            Keep record
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <h2 class="text-lg font-bold text-rose-800">Delete this duplicate family group</h2>
            <p class="mt-2 text-sm text-rose-700">
                This action permanently deletes the selected family group and its child record(s) in one go. Review the list above carefully before proceeding.
            </p>

            <form method="POST" action="{{ route('teacher.records.duplicates.destroy', $student) }}" class="mt-4">
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="inline-flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none"
                    style="background:#dc2626;color:#ffffff;border:1px solid #b91c1c;box-shadow:0 12px 24px rgba(220,38,38,.22);"
                >
                    Delete this duplicate family group
                </button>
            </form>
        </section>
    </div>
</x-layouts::app>
