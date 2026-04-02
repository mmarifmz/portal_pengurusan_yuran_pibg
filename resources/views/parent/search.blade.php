<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <title>Parent Search | {{ config('app.name') }}</title>
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Public Parent Search</h1>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Search your child by name/student no/class and contact number.</p>
            </div>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-900">Back to Dashboard</a>
            @else
                <a href="{{ route('parent.login.form') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-900">Parent Login</a>
            @endauth
        </div>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <form method="GET" action="{{ route('parent.search') }}" class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="student_keyword" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Name / Student No / Class</label>
                    <input id="student_keyword" name="student_keyword" type="text" value="{{ old('student_keyword', request('student_keyword')) }}" class="mt-1 w-full rounded-lg border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800" placeholder="Aina / 1A-0023 / Tahun 5">
                    @error('student_keyword')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="contact" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Parent Phone (partial allowed)</label>
                    <input id="contact" name="contact" type="text" value="{{ old('contact', request('contact')) }}" class="mt-1 w-full rounded-lg border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800" placeholder="0123">
                    @error('contact')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2 flex gap-3">
                    <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300">Search Child</button>
                    <a href="{{ route('parent.search') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">Reset</a>
                </div>
            </form>
        </section>

        @if ($hasSearched)
            <section class="mt-6 overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <th class="px-6 py-3">Student No</th>
                            <th class="px-6 py-3">Family Code</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Class</th>
                            <th class="px-6 py-3">Parent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        @forelse ($students as $student)
                            <tr>
                                <td class="px-6 py-4 font-medium">{{ $student->student_no }}</td>
                                <td class="px-6 py-4">{{ $student->family_code ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $student->full_name }}</td>
                                <td class="px-6 py-4">{{ $student->class_name ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <p>{{ $student->parent_name ?? '-' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $student->parent_phone ?? 'No contact' }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-zinc-500">No matching student found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endif
    </main>
    @fluxScripts
</body>
</html>