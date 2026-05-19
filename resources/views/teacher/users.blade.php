<x-layouts::app :title="__('Teacher User Management')">
    @php
        $formatWhatsapp = static fn (?string $phone): string => filled($phone) ? (str_starts_with((string) $phone, '+') ? (string) $phone : '+'.ltrim((string) $phone, '+')) : '—';
        $importSummary = session('teacher_import_summary');
        $inviteBadge = static function (?string $status): array {
            return match ($status ?: 'not_generated') {
                'sent_manual' => ['Sent Manually', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                'generated' => ['Generated', 'border-sky-200 bg-sky-50 text-sky-700'],
                default => ['Not Generated', 'border-zinc-200 bg-zinc-50 text-zinc-700'],
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
        $invitePreviewByTeacherId = collect($onboardingInvitePreviews ?? [])->keyBy(fn ($invite, $teacherId) => (int) $teacherId);
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
                    <input type="checkbox" name="send_teacher_invites" value="1" @checked(old('send_teacher_invites', true) && $canManageOnboardingInvites) @disabled(! $canManageOnboardingInvites) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed" />
                    Prepare manual WhatsApp onboarding invite after import
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
            <p class="mt-1 text-sm text-zinc-500">If the email or WhatsApp number already belongs to an existing user, the system upgrades that account with Teacher access instead of creating a duplicate. New teacher accounts will use the configured default onboarding password.</p>
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
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 md:col-span-2 lg:col-span-2">
                    Password sementara untuk akaun guru baharu akan menggunakan tetapan semasa dalam <code>TEACHER_DEFAULT_PASSWORD</code>.
                </div>
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
                                <input type="checkbox" name="send_teacher_invite" value="1" @checked(old('send_teacher_invite', true) && $canManageOnboardingInvites) @disabled(! $canManageOnboardingInvites) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed" />
                                Prepare manual WhatsApp onboarding invite using the user’s existing account
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

        @if ($canManageOnboardingInvites)
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Teacher Onboarding Invite</h2>
                        <p class="text-sm text-zinc-500">Generate WhatsApp Web invite messages for teachers to access the Teacher Dashboard.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('super-teacher.teachers.onboarding-invites.generate') }}" class="mt-4 grid gap-4 lg:grid-cols-2">
                    @csrf
                    @foreach ($inviteEligibleTeachers as $eligibleTeacher)
                        <input type="hidden" name="teacher_ids[]" value="{{ $eligibleTeacher->id }}" />
                    @endforeach

                    <label class="text-sm font-medium text-zinc-700">
                        Basic Temporary Password
                        <input id="onboardingTemporaryPassword" name="temporary_password" type="text" value="{{ $onboardingDefaultPassword }}" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </label>

                    <label class="text-sm font-medium text-zinc-700">
                        Teacher Dashboard URL
                        <input id="onboardingDashboardUrl" name="dashboard_url" type="url" value="{{ $onboardingDashboardUrl }}" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </label>

                    <div class="lg:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Gunakan password sementara yang mudah dikongsi tetapi minta guru tukar password selepas login pertama.
                    </div>

                    <label class="inline-flex items-start gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700 lg:col-span-2">
                        <input id="resetSelectedTeachers" type="checkbox" name="reset_passwords" value="1" @checked($onboardingResetSelected) class="mt-0.5 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                        <span>Reset selected teachers to default password before invite</span>
                    </label>

                    <label class="text-sm font-medium text-zinc-700 lg:col-span-2">
                        Message Preview
                        <textarea id="onboardingPreviewMessage" readonly rows="14" class="mt-1 w-full rounded-xl border border-zinc-300 bg-zinc-50 px-3 py-2 text-xs text-zinc-800">{{ $onboardingPreviewMessage }}</textarea>
                    </label>

                    <div class="lg:col-span-2 flex flex-wrap items-center gap-3">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                            Generate Invite Links
                        </button>
                        <p class="text-xs text-zinc-500">Mesej akan dibuka secara manual melalui WhatsApp Web. Tiada API WaSender atau queue digunakan.</p>
                    </div>
                </form>

                <div class="mt-6 rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900">Batch Manual Invite Helper</h3>
                            <p class="text-sm text-zinc-500">Senarai guru aktif yang mempunyai nombor WhatsApp untuk dibuka secara manual satu demi satu melalui WhatsApp Web.</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($inviteEligibleTeachers as $eligibleTeacher)
                            @php
                                $generatedInvite = $invitePreviewByTeacherId->get($eligibleTeacher->id);
                                [$onboardingStatusLabel, $onboardingStatusClasses] = $inviteBadge($eligibleTeacher->onboarding_invite_status);
                            @endphp
                            <div id="onboarding-teacher-{{ $eligibleTeacher->id }}" class="rounded-xl border border-white bg-white p-4 shadow-sm" data-onboarding-row="{{ $eligibleTeacher->id }}" data-wa-phone="{{ $generatedInvite['wa_phone'] ?? '' }}">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-900">{{ $eligibleTeacher->name }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ $eligibleTeacher->class_name ?: 'No class assigned' }} · {{ $formatWhatsapp($eligibleTeacher->phone) }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $onboardingStatusClasses }}" data-onboarding-status-badge>{{ $onboardingStatusLabel }}</span>
                                            @if ($eligibleTeacher->onboarding_invite_sent_manually_at)
                                                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                    {{ optional($eligibleTeacher->onboarding_invite_sent_manually_at)->format('d M Y H:i') }}
                                                </span>
                                            @elseif ($eligibleTeacher->onboarding_invite_generated_at)
                                                <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                                    {{ optional($eligibleTeacher->onboarding_invite_generated_at)->format('d M Y H:i') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="w-full max-w-3xl space-y-3">
                                        <textarea readonly rows="7" class="w-full rounded-xl border border-zinc-300 bg-zinc-50 px-3 py-2 text-xs text-zinc-800" data-onboarding-message data-message="{{ $generatedInvite['message'] ?? '' }}">{{ $generatedInvite['message'] ?? '' }}</textarea>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                data-generate-whatsapp
                                                data-teacher-id="{{ $eligibleTeacher->id }}"
                                                class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                            >
                                                Open WhatsApp
                                            </button>
                                            <button
                                                type="button"
                                                data-copy-message
                                                class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100"
                                            >
                                                Copy Message
                                            </button>
                                            <form method="POST" action="{{ route('super-teacher.teachers.mark-invite-sent', $eligibleTeacher) }}" class="inline-flex items-center gap-2" data-mark-sent-form>
                                                @csrf
                                                <input type="hidden" name="temporary_password" value="{{ $onboardingDefaultPassword }}">
                                                <input type="hidden" name="dashboard_url" value="{{ $onboardingDashboardUrl }}">
                                                <input type="hidden" name="reset_passwords" value="{{ $onboardingResetSelected ? 1 : 0 }}">
                                                <input type="hidden" name="scroll_to" value="onboarding-teacher-{{ $eligibleTeacher->id }}">
                                                <label class="inline-flex items-center gap-2 text-xs font-medium text-zinc-700">
                                                    <input type="checkbox" name="confirm_mark_sent" value="1" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                                                    Mark as sent manually
                                                </label>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                                                    Mark Sent
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
                                Tiada guru aktif dengan nombor WhatsApp untuk dijana onboarding invite.
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif

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
                                [$inviteStatusLabel, $inviteStatusClasses] = $inviteBadge($teacherUser->onboarding_invite_status);
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
                                    {{ optional($teacherUser->onboarding_invite_sent_manually_at)->format('d M Y H:i') ?: optional($teacherUser->onboarding_invite_generated_at)->format('d M Y H:i') ?: '—' }}
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

                                        @if ($canManageOnboardingInvites)
                                            @if (blank($teacherUser->phone))
                                                <button type="button" disabled class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 opacity-70">
                                                    Missing phone
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    data-generate-whatsapp
                                                    data-teacher-id="{{ $teacherUser->id }}"
                                                    class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                                >
                                                    Invite via WhatsApp
                                                </button>
                                            @endif
                                        @endif

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

        const onboardingGenerateUrl = @json($canManageOnboardingInvites ? route('super-teacher.teachers.onboarding-invites.generate') : null);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const onboardingPasswordInput = document.getElementById('onboardingTemporaryPassword');
        const onboardingUrlInput = document.getElementById('onboardingDashboardUrl');
        const onboardingPreview = document.getElementById('onboardingPreviewMessage');
        const resetSelectedTeachers = document.getElementById('resetSelectedTeachers');
        const onboardingRows = Array.from(document.querySelectorAll('[data-onboarding-row]'));

        function buildWhatsAppWebUrl(waPhone, message) {
            if (!waPhone || !message) {
                return '';
            }

            return `https://wa.me/${String(waPhone).replace(/^\+/, '')}?text=${encodeURIComponent(message)}`;
        }

        function syncMarkSentFormState() {
            document.querySelectorAll('[data-mark-sent-form]').forEach((form) => {
                const passwordInput = form.querySelector('input[name=\"temporary_password\"]');
                const urlInput = form.querySelector('input[name=\"dashboard_url\"]');
                const resetInput = form.querySelector('input[name=\"reset_passwords\"]');

                if (passwordInput) {
                    passwordInput.value = onboardingPasswordInput?.value || '';
                }

                if (urlInput) {
                    urlInput.value = onboardingUrlInput?.value || '';
                }

                if (resetInput) {
                    resetInput.value = resetSelectedTeachers?.checked ? '1' : '0';
                }
            });
        }

        document.querySelectorAll('[data-copy-message]').forEach((button) => {
            button.addEventListener('click', async () => {
                const messageField = button.closest('[data-onboarding-row]')?.querySelector('[data-onboarding-message]');
                const message = messageField?.value || '';

                if (!message) {
                    return;
                }

                await navigator.clipboard.writeText(message);
                button.textContent = 'Copied';
                window.setTimeout(() => {
                    button.textContent = 'Copy Message';
                }, 1500);
            });
        });

        document.querySelectorAll('[data-generate-whatsapp]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!onboardingGenerateUrl) {
                    return;
                }

                if (resetSelectedTeachers?.checked && !window.confirm('Reset selected teachers to the temporary password before generating this invite?')) {
                    return;
                }

                const teacherId = button.getAttribute('data-teacher-id');
                if (!teacherId) {
                    return;
                }

                button.disabled = true;

                try {
                    const response = await fetch(onboardingGenerateUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            teacher_ids: [teacherId],
                            temporary_password: onboardingPasswordInput?.value || '',
                            dashboard_url: onboardingUrlInput?.value || '',
                            reset_passwords: resetSelectedTeachers?.checked ? 1 : 0,
                        }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to generate onboarding invite.');
                    }

                    const invite = Array.isArray(data.invites) ? data.invites[0] : null;
                    if (!invite) {
                        throw new Error('Unable to generate onboarding invite.');
                    }

                    const row = document.querySelector(`[data-onboarding-row="${invite.teacher_id}"]`);
                    const messageField = row?.querySelector('[data-onboarding-message]');
                    const statusBadge = row?.querySelector('[data-onboarding-status-badge]');
                    if (messageField) {
                        messageField.value = invite.message || '';
                        messageField.dataset.message = invite.message || '';
                    }
                    if (statusBadge) {
                        statusBadge.textContent = invite.status_label || 'Generated';
                        statusBadge.className = 'inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700';
                    }
                    if (row && invite.wa_phone) {
                        row.setAttribute('data-wa-phone', invite.wa_phone);
                    }
                    if (onboardingPreview) {
                        onboardingPreview.value = invite.message || onboardingPreview.value;
                    }
                    syncMarkSentFormState();

                    const waLink = buildWhatsAppWebUrl(invite.wa_phone || row?.getAttribute('data-wa-phone') || '', invite.message || messageField?.value || '');
                    if (waLink) {
                        window.open(waLink, '_blank', 'noopener');
                    }
                } catch (error) {
                    window.alert(error.message || 'Unable to generate onboarding invite.');
                } finally {
                    button.disabled = false;
                }
            });
        });

        [onboardingPasswordInput, onboardingUrlInput, resetSelectedTeachers].forEach((field) => {
            field?.addEventListener('input', syncMarkSentFormState);
            field?.addEventListener('change', syncMarkSentFormState);
        });

        onboardingRows.forEach((row) => {
            const messageField = row.querySelector('[data-onboarding-message]');
            if (messageField && !messageField.dataset.message) {
                messageField.dataset.message = messageField.value || '';
            }
        });

        syncMarkSentFormState();
    </script>
</x-layouts::app>
