<x-layouts::app :title="__('Teacher User Management')">
    @php
        $formatWhatsapp = static fn (?string $phone): string => filled($phone) ? (str_starts_with((string) $phone, '+') ? (string) $phone : '+'.ltrim((string) $phone, '+')) : '—';
        $importSummary = session('teacher_import_summary');
        $manualInvites = session('teacher_manual_invites', []);
        $inviteBadge = static function (?string $status): array {
            return match ($status ?: 'pending') {
                'sent' => ['Sent', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                'failed' => ['Failed', 'border-rose-200 bg-rose-50 text-rose-700'],
                'manual' => ['Manual', 'border-amber-200 bg-amber-50 text-amber-700'],
                default => ['Pending', 'border-zinc-200 bg-zinc-50 text-zinc-700'],
            };
        };
        $roleBadge = static function (string $role): array {
            return match ($role) {
                'parent' => ['Parent', 'border-sky-200 bg-sky-50 text-sky-700'],
                'teacher' => ['Teacher', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                'system_admin' => ['Super Admin', 'border-violet-200 bg-violet-50 text-violet-700'],
                'super_teacher' => ['Super Teacher', 'border-amber-200 bg-amber-50 text-amber-800'],
                default => [str_replace('_', ' ', ucfirst($role)), 'border-zinc-200 bg-zinc-50 text-zinc-700'],
            };
        };
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Super Teacher</p>
                <h1 class="text-2xl font-bold text-zinc-900">Teacher User Management</h1>
                <p class="text-sm text-zinc-500">Create, import, invite, and manage teacher users without disrupting the existing dashboard flow.</p>
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

        @if (is_array($importSummary))
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Latest Import Summary</h2>
                        <p class="text-sm text-zinc-600">File: {{ $importSummary['filename'] ?? 'teacher-import.csv' }}</p>
                    </div>
                    @if (($importSummary['failed_rows_count'] ?? 0) > 0)
                        <a href="{{ route('super-teacher.teachers.import.failed-rows') }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                            Download Failed Rows CSV
                        </a>
                    @endif
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Total Rows</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['total_rows'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Created</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['created'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Updated</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['updated'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Assigned To Class</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['assigned_to_class'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Converted Existing Users</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['converted_existing_users'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Failed Rows</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['failed_rows_count'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">No Class Matched</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['no_class_matched'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Class Skipped</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['class_assignment_skipped'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Duplicate Emails Updated</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['duplicate_emails_updated'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">WhatsApp Enabled</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['whatsapp_enabled'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/70 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Invite Sent</p>
                        <p class="mt-1 text-xl font-bold text-zinc-900">{{ $importSummary['invite_sent'] ?? 0 }}</p>
                    </div>
                </div>

                @if (! empty($importSummary['warnings']))
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-sm font-semibold text-amber-800">Import Warnings</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-700">
                            @foreach ($importSummary['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        @if (is_array($manualInvites) && $manualInvites !== [])
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-zinc-900">Manual Invite Messages</h2>
                <p class="mt-1 text-sm text-zinc-600">WhatsApp auto-send is unavailable for these invite attempts, so the prepared messages are shown here once for manual sending.</p>

                <div class="mt-4 space-y-4">
                    @foreach ($manualInvites as $manualInvite)
                        <div class="rounded-xl border border-white/80 bg-white p-4">
                            <p class="text-sm font-semibold text-zinc-900">{{ $manualInvite['name'] ?? 'Teacher' }}</p>
                            <p class="text-xs text-zinc-500">{{ $manualInvite['phone'] ?? 'No phone' }}</p>
                            <textarea readonly rows="8" class="mt-3 w-full rounded-xl border border-zinc-300 bg-zinc-50 px-3 py-2 text-xs text-zinc-800">{{ $manualInvite['message'] ?? '' }}</textarea>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Import Teacher Users</h2>
                    <p class="text-sm text-zinc-500">Upload a CSV with Name, Phone, Email, Group, and Class columns to create or update teacher accounts in batch.</p>
                </div>
                <a href="{{ route('super-teacher.teachers.import.sample') }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                    Download Sample CSV
                </a>
            </div>

            <form method="POST" action="{{ route('super-teacher.teachers.import') }}" enctype="multipart/form-data" class="mt-4 grid gap-3 lg:grid-cols-3">
                @csrf
                <label class="text-sm font-medium text-zinc-700 lg:col-span-3">
                    Upload CSV File
                    <input name="teachers_csv" type="file" accept=".csv,text/csv" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white" />
                </label>

                <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700">
                    <input type="hidden" name="auto_assign_class" value="0">
                    <input type="checkbox" name="auto_assign_class" value="1" @checked(old('auto_assign_class', true)) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                    Auto assign class based on Class column
                </label>
                <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700">
                    <input type="hidden" name="enable_whatsapp_notifications" value="0">
                    <input type="checkbox" name="enable_whatsapp_notifications" value="1" @checked(old('enable_whatsapp_notifications', true)) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                    Enable WhatsApp notifications after import
                </label>
                <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700">
                    <input type="hidden" name="send_teacher_invites" value="0">
                    <input type="checkbox" name="send_teacher_invites" value="1" @checked(old('send_teacher_invites', true)) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                    Send teacher dashboard invite after import
                </label>

                <div class="lg:col-span-3">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Import Teachers
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Create New Teacher</h2>
            <p class="mt-1 text-sm text-zinc-500">If the email or WhatsApp number already belongs to an existing user, the system upgrades that account with Teacher access instead of creating a duplicate.</p>
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
                    <input name="phone" type="text" value="{{ old('phone') }}" placeholder="+60123456789 or 0123456789" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
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
                    Password (new accounts only)
                    <input name="password" type="password" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <label class="text-sm font-medium text-zinc-700">
                    Confirm Password
                    <input name="password_confirmation" type="password" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
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
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Assign Existing User as Teacher</h2>
                    <p class="text-sm text-zinc-500">Search existing parent/admin accounts by name, email, or phone, then add Teacher access without duplicating the user.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('super-teacher.teachers.index') }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
                <label class="text-sm font-medium text-zinc-700">
                    Search Existing User
                    <input name="existing_user_search" type="text" value="{{ $existingUserSearch ?? '' }}" placeholder="Name, email, or phone" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </label>
                <div class="self-end">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Search User
                    </button>
                </div>
            </form>

            @if (filled($existingUserSearch ?? ''))
                @if (($existingUserMatches ?? collect())->isEmpty())
                    <p class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">No assignable existing user matched that search.</p>
                @else
                    <form method="POST" action="{{ route('super-teacher.teachers.assign-existing') }}" class="mt-4 grid gap-3 lg:grid-cols-3">
                        @csrf
                        <input type="hidden" name="existing_user_search" value="{{ $existingUserSearch }}">
                        <label class="text-sm font-medium text-zinc-700 lg:col-span-3">
                            Matching User
                            <select name="user_id" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="">Select existing user</option>
                                @foreach ($existingUserMatches as $matchedUser)
                                    <option value="{{ $matchedUser->id }}" @selected((string) old('user_id') === (string) $matchedUser->id)>
                                        {{ $matchedUser->name }} | {{ $matchedUser->email ?: 'No email' }} | {{ $matchedUser->phone ?: 'No phone' }} | Roles: {{ implode(', ', array_map(fn ($role) => str_replace('_', ' ', ucfirst($role)), $matchedUser->roleNames())) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium text-zinc-700">
                            Select Class
                            <select name="class_name" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="">Choose class</option>
                                @foreach ($classOptions as $className)
                                    <option value="{{ $className }}" @selected(old('class_name') === $className)>{{ $className }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="space-y-3 lg:col-span-2">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <input type="hidden" name="enable_whatsapp_notifications" value="0">
                                <input type="checkbox" name="enable_whatsapp_notifications" value="1" @checked(old('enable_whatsapp_notifications', true)) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                                Enable WhatsApp notifications for this class teacher
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <input type="hidden" name="send_teacher_invite" value="0">
                                <input type="checkbox" name="send_teacher_invite" value="1" @checked(old('send_teacher_invite', true)) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                                Send teacher access invite using the user’s existing account
                            </label>
                        </div>
                        <div class="lg:col-span-3">
                            <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                                Assign Existing User as Teacher
                            </button>
                        </div>
                    </form>
                @endif
            @endif
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Existing Teacher Users</h2>
                    <p class="text-sm text-zinc-500">Manage class assignments, account status, WhatsApp delivery, and teacher dashboard invites.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('super-teacher.teachers.enable-whatsapp-all') }}" onsubmit="return confirm('Enable WhatsApp notifications for all eligible assigned teachers?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                            Enable WA for All Assigned Teachers
                        </button>
                    </form>
                    <form method="POST" action="{{ route('super-teacher.teachers.disable-whatsapp-all') }}" onsubmit="return confirm('Disable WhatsApp notifications for all teacher users?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                            Disable WA for All
                        </button>
                    </form>
                    <form method="POST" action="{{ route('super-teacher.teachers.send-invite-all') }}" onsubmit="return confirm('Send teacher dashboard invites to all active teachers with WhatsApp numbers?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg bg-zinc-900 px-3 py-2 text-xs font-semibold text-white hover:bg-zinc-700">
                            Send Invite to All Active Teachers
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">WhatsApp</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3">Roles</th>
                            <th class="px-4 py-3">Account</th>
                            <th class="px-4 py-3">WA Notification</th>
                            <th class="px-4 py-3">Invite Status</th>
                            <th class="px-4 py-3">Last Invite Sent</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($teacherUsers as $teacherUser)
                            @php
                                [$inviteStatusLabel, $inviteStatusClasses] = $inviteBadge($teacherUser->invite_status);
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-zinc-900">{{ $teacherUser->name }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $teacherUser->email }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $formatWhatsapp($teacherUser->phone) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $teacherUser->class_name ?: 'No class assigned' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($teacherUser->roleNames() as $roleName)
                                            @php([$roleLabel, $roleClasses] = $roleBadge($roleName))
                                            <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $roleClasses }}">{{ $roleLabel }}</span>
                                        @endforeach
                                    </div>
                                </td>
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
                                    @elseif (! $teacherUser->is_active)
                                        <span class="inline-flex rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-700">Inactive</span>
                                    @elseif ($teacherUser->receive_whatsapp_notifications)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Enabled</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-700">Disabled</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $inviteStatusClasses }}">{{ $inviteStatusLabel }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-zinc-600">
                                    {{ optional($teacherUser->teacher_invite_sent_at)->format('d M Y H:i') ?: '—' }}
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
                                            <button type="submit" @disabled(blank($teacherUser->class_name) || blank($teacherUser->phone) || ! $teacherUser->is_active) class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50">
                                                {{ $teacherUser->receive_whatsapp_notifications ? 'WA Off' : 'WA On' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('super-teacher.teachers.send-invite', $teacherUser) }}">
                                            @csrf
                                            <button type="submit" @disabled(blank($teacherUser->phone) || ! $teacherUser->is_active) class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50">
                                                Send Invite
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
                                <td colspan="10" class="px-4 py-4">
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
                                            <input name="phone" type="text" value="{{ $teacherUser->phone }}" placeholder="+60123456789 or 0123456789" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
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
                                <td colspan="10" class="px-4 py-6 text-center text-sm text-zinc-500">No teacher users found.</td>
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
