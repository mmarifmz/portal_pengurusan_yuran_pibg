<x-layouts::app :title="__('Teacher User Management')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Super Teacher</p>
                <h1 class="text-2xl font-bold text-zinc-900">Teacher User Management</h1>
                <p class="text-sm text-zinc-500">Create teacher users, assign class, and update profile/password.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Create New Teacher</h2>
            <form method="POST" action="{{ route('super-teacher.teachers.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <label class="text-sm font-medium text-zinc-700">
                    Name
                    <input name="name" type="text" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Email
                    <input name="email" type="email" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Assign Class
                    <select name="class_name" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        <option value="">No class assigned</option>
                        @foreach ($classOptions as $className)
                            <option value="{{ $className }}">{{ $className }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Password
                    <input name="password" type="password" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Confirm Password
                    <input name="password_confirmation" type="password" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Create Teacher
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Existing Teacher Users</h2>
            <div class="mt-4 space-y-4">
                @forelse ($teacherUsers as $teacherUser)
                    <form method="POST" action="{{ route('super-teacher.teachers.update', $teacherUser) }}" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="text-sm font-medium text-zinc-700">
                                Name
                                <input name="name" type="text" value="{{ $teacherUser->name }}" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                            </label>
                            <label class="text-sm font-medium text-zinc-700">
                                Email
                                <input name="email" type="email" value="{{ $teacherUser->email }}" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                            </label>
                            <label class="text-sm font-medium text-zinc-700">
                                Assign Class
                                <select name="class_name" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <option value="">No class assigned</option>
                                    @foreach ($classOptions as $className)
                                        <option value="{{ $className }}" @selected($teacherUser->class_name === $className)>{{ $className }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-sm font-medium text-zinc-700">
                                New Password (optional)
                                <input name="password" type="password" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                            </label>
                            <label class="text-sm font-medium text-zinc-700">
                                Confirm New Password
                                <input name="password_confirmation" type="password" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                            </label>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100">
                                Save Changes
                            </button>
                        </div>
                    </form>
                @empty
                    <p class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-500">No teacher users found.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>
