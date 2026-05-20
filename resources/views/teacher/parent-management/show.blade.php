<x-layouts::app :title="__('Parent Profile')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <a href="{{ route('teacher.parent-management.index') }}" class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">Back to Parent Management</a>
                    <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-zinc-900">{{ $parentUser->name }}</h1>
                    <p class="mt-1 text-sm text-zinc-600">{{ $parentUser->phone ?: 'No phone' }} @if($parentUser->email) · {{ $parentUser->email }} @endif</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($parentUser->is_active === false)
                            <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">Blocked</span>
                        @else
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Active</span>
                        @endif
                        @foreach ($parentUser->roleNames() as $roleName)
                            <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                {{ str_replace('_', ' ', strtoupper($roleName === 'system_admin' ? 'admin' : $roleName)) }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Linked Children</p>
                        <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($linkedStudents->count()) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Last Login</p>
                        <p class="mt-1 text-sm font-semibold text-zinc-900">{{ $lastLoginAt ? $lastLoginAt->format('d M Y H:i') : '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Last Activity</p>
                        <p class="mt-1 text-sm font-semibold text-zinc-900">{{ $lastActivityAt ? $lastActivityAt->format('d M Y H:i') : '-' }}</p>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.2fr,0.8fr]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-zinc-900">Contact Profile</h2>
                            <p class="mt-1 text-sm text-zinc-600">Update the parent-facing contact details without changing linked student access.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('teacher.parent-management.contact.update', $parentUser) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf
                        @method('PATCH')
                        <label class="text-sm font-semibold text-zinc-700 md:col-span-2">
                            Parent name
                            <input type="text" name="name" value="{{ old('name', $parentUser->getRawOriginal('name')) }}" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                        </label>
                        <label class="text-sm font-semibold text-zinc-700">
                            Phone
                            <input type="text" name="phone" value="{{ old('phone', $parentUser->getRawOriginal('phone')) }}" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                        </label>
                        <label class="text-sm font-semibold text-zinc-700">
                            Email
                            <input type="email" name="email" value="{{ old('email', $parentUser->getRawOriginal('email')) }}" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                        </label>
                        <div class="md:col-span-2 flex items-center justify-end">
                            <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                                Save Contact
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-zinc-900">Linked Students</h2>
                            <p class="mt-1 text-sm text-zinc-600">Choose the students this parent can access. Notes save automatically with debounce.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('teacher.parent-management.student-links.sync', $parentUser) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')
                        <label class="block text-sm font-semibold text-zinc-700">
                            Student links
                            <select name="student_ids[]" multiple size="10" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                @foreach ($availableStudents as $student)
                                    <option value="{{ $student->id }}" @selected($linkedStudents->contains('id', $student->id))>
                                        {{ $student->class_name ?: 'No class' }} - {{ $student->full_name }} ({{ $student->family_code ?: 'No family code' }})
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">
                                Save Linked Students
                            </button>
                        </div>
                    </form>

                    <div class="mt-5 space-y-4">
                        @forelse ($linkedStudents as $student)
                            @php
                                $link = $explicitLinks->get($student->id);
                            @endphp
                            <form
                                method="POST"
                                action="{{ route('teacher.parent-management.student-links.update', [$parentUser, $student]) }}"
                                class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4"
                                data-autosave-form
                                data-autosave-delay="700"
                            >
                                @csrf
                                @method('PATCH')
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <p class="font-semibold text-zinc-900">{{ $student->full_name }}</p>
                                        <p class="text-xs text-zinc-500">{{ $student->class_name ?: 'No class' }} · {{ $student->family_code ?: 'No family code' }}</p>
                                    </div>
                                    <span class="text-xs font-semibold text-zinc-500">{{ $link ? 'Explicit link' : 'Phone/email matched' }}</span>
                                </div>
                                <label class="mt-3 block text-sm font-semibold text-zinc-700">
                                    Link note
                                    <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">{{ $link?->notes }}</textarea>
                                </label>
                                <p class="mt-2 text-xs font-semibold text-zinc-500" data-autosave-status>Saved</p>
                            </form>
                        @empty
                            <p class="rounded-2xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500">No students are linked to this parent yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-zinc-900">Payment History</h2>
                            <p class="mt-1 text-sm text-zinc-600">Recent transactions across the parent’s linked family records.</p>
                        </div>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200">
                            <thead class="bg-zinc-50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">
                                    <th class="px-3 py-2">Family</th>
                                    <th class="px-3 py-2">Amount</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Paid At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                                @forelse ($recentPayments as $payment)
                                    <tr>
                                        <td class="px-3 py-2 font-semibold text-zinc-900">{{ $payment->familyBilling?->family_code ?: '-' }}</td>
                                        <td class="px-3 py-2">RM {{ number_format((float) $payment->amount, 2) }}</td>
                                        <td class="px-3 py-2">{{ ucfirst((string) $payment->status) }}</td>
                                        <td class="px-3 py-2">{{ $payment->paid_at_for_display?->format('d M Y H:i') ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-6 text-center text-zinc-500">No payment activity yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-zinc-900">Access & Roles</h2>
                            <p class="mt-1 text-sm text-zinc-600">These settings autosave after a short pause.</p>
                        </div>
                        <span class="rounded-full border border-zinc-200 bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-700" data-autosave-status-global>Saved</span>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('teacher.parent-management.settings.autosave', $parentUser) }}"
                        class="mt-4 space-y-5"
                        data-autosave-form
                        data-autosave-status-target="[data-autosave-status-global]"
                        data-autosave-delay="700"
                    >
                        @csrf
                        @method('PATCH')

                        <div>
                            <p class="text-sm font-semibold text-zinc-700">Roles</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach ($roleOptions as $roleKey => $roleLabel)
                                    <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium text-zinc-700">
                                        <input type="checkbox" name="roles[]" value="{{ $roleKey }}" @checked(
                                            $roleKey === 'admin'
                                                ? $parentUser->isSystemAdmin()
                                                : $parentUser->hasRole($roleKey)
                                        ) class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                                        <span>{{ $roleLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <label class="block text-sm font-semibold text-zinc-700">
                            Access status
                            <select name="is_active" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="1" @selected($parentUser->is_active !== false)>Active</option>
                                <option value="0" @selected($parentUser->is_active === false)>Blocked</option>
                            </select>
                        </label>

                        <label class="block text-sm font-semibold text-zinc-700">
                            Block / unblock reason
                            <textarea name="access_block_reason" rows="4" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">{{ $parentUser->access_block_reason }}</textarea>
                        </label>

                        <label class="block text-sm font-semibold text-zinc-700">
                            Social tag
                            <select name="social_tag_id" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="">No social tag</option>
                                @foreach ($activeSocialTags as $socialTag)
                                    <option value="{{ $socialTag->id }}" @selected($currentSocialTagId === $socialTag->id)>{{ $socialTag->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>

                    <form method="POST" action="{{ route('teacher.parent-management.reset-access', $parentUser) }}" class="mt-5">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100">
                            Reset Parent Login Verification
                        </button>
                    </form>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-bold text-zinc-900">Linked Families</h2>
                        <p class="mt-1 text-sm text-zinc-600">Latest billing records currently tied to this parent account.</p>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($latestBillings as $billing)
                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-zinc-900">{{ $billing->family_code }}</p>
                                        <p class="text-xs text-zinc-500">Year {{ $billing->billing_year }}</p>
                                    </div>
                                    <span class="text-sm font-semibold {{ $billing->outstanding_amount <= 0 ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ $billing->outstanding_amount <= 0 ? 'Paid' : 'Outstanding RM '.number_format((float) $billing->outstanding_amount, 2) }}
                                    </span>
                                </div>
                                @if ($billing->socialTags->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($billing->socialTags as $socialTag)
                                            <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $socialTag->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-2xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500">No family billing records are linked yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-bold text-zinc-900">Access Activity</h2>
                        <p class="mt-1 text-sm text-zinc-600">Recent parent portal actions for this user.</p>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($recentActivities as $activity)
                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-zinc-900">{{ str_replace('_', ' ', ucfirst((string) ($activity->action_type ?? 'login'))) }}</p>
                                        <p class="text-xs text-zinc-500">{{ $activity->page_visited ?: '-' }} · {{ $activity->device_browser ?: 'Unknown device' }}</p>
                                    </div>
                                    <span class="text-xs font-semibold text-zinc-500">{{ $activity->occurred_at_for_display?->format('d M Y H:i') ?: '-' }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-2xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500">No access activity recorded yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            document.querySelectorAll('[data-autosave-form]').forEach((form) => {
                const delay = Number(form.dataset.autosaveDelay || 600);
                const statusTargetSelector = form.dataset.autosaveStatusTarget || '[data-autosave-status]';
                const localStatusTarget = form.querySelector('[data-autosave-status]');
                const explicitStatusTarget = statusTargetSelector ? document.querySelector(statusTargetSelector) : null;
                const statusNode = explicitStatusTarget || localStatusTarget;
                let timer = null;

                const updateStatus = (message, classes = '') => {
                    if (!statusNode) {
                        return;
                    }

                    statusNode.textContent = message;
                    statusNode.className = classes !== '' ? classes : statusNode.className;
                };

                const submitAutosave = async () => {
                    const formData = new FormData(form);
                    updateStatus('Saving...', 'rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: formData,
                        });

                        if (!response.ok) {
                            throw new Error('Autosave failed');
                        }

                        updateStatus('Saved', 'rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700');
                    } catch (_error) {
                        updateStatus('Failed, retry', 'rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700');
                    }
                };

                const queueAutosave = () => {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(submitAutosave, delay);
                };

                form.querySelectorAll('input, textarea, select').forEach((field) => {
                    field.addEventListener(field.tagName === 'SELECT' || field.type === 'checkbox' ? 'change' : 'input', queueAutosave);
                });
            });
        });
    </script>
</x-layouts::app>
