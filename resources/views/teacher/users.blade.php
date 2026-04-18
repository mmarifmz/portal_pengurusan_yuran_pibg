<x-layouts::app :title="__('Teacher User Management')">
    @php
        $formatWhatsapp = static fn (?string $phone): string => filled($phone) ? '+'.ltrim((string) $phone, '+') : '—';
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Super Teacher</p>
                <h1 class="text-2xl font-bold text-zinc-900">Teacher User Management</h1>
                <p class="text-sm text-zinc-500">Create and manage teacher users, classes, and WhatsApp notifications.</p>
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
            <form method="POST" action="{{ route('super-teacher.teachers.store') }}" class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                @csrf
                <label class="text-sm font-medium text-zinc-700">
                    Name
                    <input name="name" type="text" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Email
                    <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    WhatsApp Number (Malaysia)
                    <input name="phone" type="text" value="{{ old('phone') }}" placeholder="60123456789 or 0123456789" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Assign Class
                    <select name="class_name" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        <option value="">No class assigned</option>
                        @foreach ($classOptions as $className)
                            <option value="{{ $className }}" @selected(old('class_name') === $className)>{{ $className }}</option>
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
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-zinc-700">
                        <input type="hidden" name="receive_whatsapp_notifications" value="0">
                        <input type="checkbox" name="receive_whatsapp_notifications" value="1" @checked(old('receive_whatsapp_notifications', true)) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                        Enable WhatsApp notifications when teacher is assigned to a class
                    </label>
                </div>
                <div class="md:col-span-2 lg:col-span-3">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Create Teacher
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Existing Teacher Users</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">WhatsApp</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3">Account</th>
                            <th class="px-4 py-3">WA Notification</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($teacherUsers as $teacherUser)
                            <tr>
                                <td class="px-4 py-3 font-medium text-zinc-900">{{ $teacherUser->name }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $teacherUser->email }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $formatWhatsapp($teacherUser->phone) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $teacherUser->class_name ?: 'No class assigned' }}</td>
                                <td class="px-4 py-3">
                                    @if ($teacherUser->is_active)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Disabled</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (blank($teacherUser->class_name))
                                        <span class="inline-flex rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-600">N/A (no class)</span>
                                    @elseif (blank($teacherUser->phone))
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Missing WhatsApp</span>
                                    @elseif ($teacherUser->receive_whatsapp_notifications)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Enabled</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-700">Disabled</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" data-edit-toggle="edit-row-{{ $teacherUser->id }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                                            Edit
                                        </button>

                                        <form method="POST" action="{{ route('super-teacher.teachers.update-status', $teacherUser) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="enabled" value="{{ $teacherUser->is_active ? 0 : 1 }}">
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                                                {{ $teacherUser->is_active ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('super-teacher.teachers.update-whatsapp-notifications', $teacherUser) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="enabled" value="{{ $teacherUser->receive_whatsapp_notifications ? 0 : 1 }}">
                                            <button type="submit" @disabled(blank($teacherUser->class_name) || blank($teacherUser->phone)) class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50">
                                                {{ $teacherUser->receive_whatsapp_notifications ? 'WA Off' : 'WA On' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('super-teacher.teachers.destroy', $teacherUser) }}" onsubmit="return confirm('Delete this teacher account? This action cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr id="edit-row-{{ $teacherUser->id }}" class="hidden bg-zinc-50">
                                <td colspan="7" class="px-4 py-4">
                                    <form method="POST" action="{{ route('super-teacher.teachers.update', $teacherUser) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                                        @csrf
                                        @method('PATCH')
                                        <label class="text-sm font-medium text-zinc-700">
                                            Name
                                            <input name="name" type="text" value="{{ $teacherUser->name }}" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                                        </label>
                                        <label class="text-sm font-medium text-zinc-700">
                                            Email
                                            <input name="email" type="email" value="{{ $teacherUser->email }}" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                                        </label>
                                        <label class="text-sm font-medium text-zinc-700">
                                            WhatsApp Number (Malaysia)
                                            <input name="phone" type="text" value="{{ $teacherUser->phone }}" placeholder="60123456789 or 0123456789" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
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
                                        <div class="md:col-span-2 lg:col-span-3">
                                            <label class="inline-flex items-center gap-2 text-sm font-medium text-zinc-700">
                                                <input type="hidden" name="receive_whatsapp_notifications" value="0">
                                                <input type="checkbox" name="receive_whatsapp_notifications" value="1" @checked($teacherUser->receive_whatsapp_notifications) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                                                Enable WhatsApp notifications for this class teacher
                                            </label>
                                        </div>
                                        <div class="md:col-span-2 lg:col-span-3">
                                            <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                                                Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-zinc-500">No teacher users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        document.querySelectorAll('[data-edit-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const rowId = button.getAttribute('data-edit-toggle');
                const row = rowId ? document.getElementById(rowId) : null;
                if (!row) {
                    return;
                }

                row.classList.toggle('hidden');
            });
        });
    </script>
</x-layouts::app>
