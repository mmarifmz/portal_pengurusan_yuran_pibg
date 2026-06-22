<x-layouts::app :title="__('Family Profile')">
    @php
        $allPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'all']);
        $successfulPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'successful']);
        $pendingPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'pending']);
        $cancelledPaymentsUrl = route('teacher.records.family', ['familyCode' => $familyCode, 'payment_status' => 'cancelled']);
        $exportPaymentsUrl = route('teacher.records.family.payments.export', ['familyCode' => $familyCode, 'payment_status' => $paymentFilter]);
        $updateParentProfileUrl = route('teacher.records.family.parent-profile.update', ['familyCode' => $familyCode]);
    @endphp

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Family profile</p>
                <h1 class="text-2xl font-bold text-zinc-900">{{ $familyCode }}</h1>
                <p class="text-sm text-zinc-500">Butiran keluarga, sejarah pembayaran dan log akses TAC.</p>
            </div>
            <a href="{{ route('teacher.records') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50">
                Back to Student &amp; Family Lists
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Students</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ $students->count() }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Total billed (RM)</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($totalBilled, 2) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Total paid (RM)</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ number_format($totalPaid, 2) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Outstanding (RM)</p>
                <p class="mt-2 text-2xl font-semibold {{ $totalOutstanding > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($totalOutstanding, 2) }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Parent Account</h2>
                    <p class="text-xs text-zinc-500">Status onboarding parent berdasarkan user parent dan log TAC berjaya.</p>
                </div>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $isOnboarded ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $isOnboarded ? 'Onboarded' : 'Not onboarded' }}
                </span>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Linked parent users</p>
                    <p class="mt-1 text-xl font-semibold text-zinc-900">{{ $linkedParents->count() }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Successful TAC logins</p>
                    <p class="mt-1 text-xl font-semibold text-zinc-900">{{ $successfulLogins }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Latest access</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900">{{ $latestAccessAt?->format('d M Y H:i') ?: '-' }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <h3 class="mb-2 text-sm font-semibold text-zinc-900">Family Attached Phones</h3>
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Phone Number</th>
                            <th class="px-4 py-3">Last Logged In</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($familyPhoneAccess as $phoneAccess)
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $phoneAccess['phone'] }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $phoneAccess['latest_login_at']?->format('d M Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-4 text-center text-zinc-500">No phone number attached to this family yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($linkedParents as $linkedParent)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $linkedParent->name ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $linkedParent->email ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $linkedParent->phone ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $linkedParent->created_at?->format('d M Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-zinc-500">No linked parent account found for this family yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($currentBilling)
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Tag Keluarga <span class="sr-only">Family Social Tags</span></h2>
                        <p class="mt-1 text-xs text-zinc-500">Kategori bantuan, sosioekonomi atau klasifikasi bayaran untuk family tahun {{ $currentBilling->billing_year }}.</p>
                    </div>
                    @if (auth()->user()->isSystemAdmin())
                        <a href="{{ route('teacher.social-tags.index') }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-[11px] font-semibold text-zinc-700 transition hover:bg-zinc-100">
                            Open Tag Count Page
                        </a>
                    @endif
                </div>
                <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Tag keluarga semasa</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($currentFamilySocialTags as $tagName)
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                {{ $tagName }}
                            </span>
                        @empty
                            <span class="text-xs text-zinc-500">Belum ada tag keluarga direkodkan untuk family ini.</span>
                        @endforelse
                    </div>
                </div>

                @can('manageStudentRecords')
                    <form method="POST" action="{{ route('teacher.records.family.social-tags.update', ['familyCode' => $familyCode]) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="target_type" value="family">

                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Kemaskini Tag Keluarga</p>
                            <label class="mt-3 block text-xs font-semibold text-zinc-600">
                                Cari tag
                                <input
                                    type="search"
                                    placeholder="Taip untuk tapis tag keluarga"
                                    data-tag-search="family-tag-options"
                                    class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                />
                            </label>
                            <div id="family-tag-options" class="mt-3 flex flex-wrap gap-3">
                                @foreach ($availableSocialTags as $socialTag)
                                    <label data-tag-option="{{ mb_strtolower($socialTag->name) }}" class="inline-flex items-center gap-2 rounded-full border border-zinc-300 bg-white px-3 py-2 text-xs font-medium text-zinc-700">
                                        <input
                                            type="checkbox"
                                            name="social_tag_ids[]"
                                            value="{{ $socialTag->id }}"
                                            @checked(collect(old('social_tag_ids', $currentBilling->socialTags->pluck('id')->all()))->map(fn ($id) => (string) $id)->contains((string) $socialTag->id))
                                        >
                                        {{ $socialTag->name }}
                                    </label>
                                @endforeach
                            </div>
                            <label class="mt-3 block text-xs font-semibold text-zinc-600">
                                Cipta tag baharu jika tiada dalam senarai
                                <input
                                    type="text"
                                    name="new_social_tag_name"
                                    value="{{ old('new_social_tag_name') }}"
                                    placeholder="Contoh: Asnaf"
                                    class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                />
                            </label>
                            @error('social_tag_ids')
                                <p class="mt-2 text-[11px] font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                            @error('social_tag_ids.*')
                                <p class="mt-2 text-[11px] font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <button type="submit" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">
                                Simpan Tag Keluarga
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-500">
                        Teacher access is read-only here. Tag hanya boleh dikemaskini oleh Super Admin.
                    </p>
                @endcan
            </section>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Family Members</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Student No</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Tag Murid / Jawatan</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3">Status Murid</th>
                            <th class="px-4 py-3">Parent Name</th>
                            <th class="px-4 py-3">Parent Contact</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @foreach ($students as $student)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $student->student_no ?: '-' }}</td>
                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $student->full_name }}</td>
                                <td class="px-4 py-3 text-zinc-700">
                                    @php
                                        $studentRoleTags = collect($student->resolved_student_social_tags ?? []);
                                    @endphp
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($studentRoleTags as $tag)
                                            <span class="inline-flex items-center rounded-full border border-cyan-200 bg-cyan-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-cyan-800">
                                                {{ $tag }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-zinc-500">Belum ada tag untuk murid ini.</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ $student->class_name ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ ! $student->isActiveStatus() ? 'border-zinc-300 bg-zinc-100 text-zinc-600' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                        {{ $student->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ $student->resolved_parent_name ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-600">
                                    <div>{{ $student->parent_phone ?: '-' }}</div>
                                    <div class="text-xs">{{ $student->parent_email ?: '-' }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @can('manageStudentRecords')
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Tag Murid / Jawatan Murid</h2>
                        <p class="text-xs text-zinc-500">Tambah tag untuk jawatan, peranan atau kategori khas murid. Tag ini melekat pada murid tertentu, bukan seluruh family.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4">
                    @foreach ($students as $student)
                        @php
                            $studentTagIds = $student->socialTags->pluck('id')->map(fn ($id) => (string) $id);
                            $studentTagNames = collect($student->resolved_student_social_tags ?? []);
                        @endphp
                        <form
                            id="student-tags-{{ $student->id }}"
                            method="POST"
                            action="{{ route('teacher.records.students.tags.update', $student) }}"
                            class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="target_type" value="student">
                            <input type="hidden" name="family_code" value="{{ $familyCode }}">

                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-zinc-900">{{ $student->full_name }}</h3>
                                    <p class="text-xs text-zinc-500">{{ $student->class_name ?: 'Tiada kelas' }} | {{ $student->student_no ?: '-' }}</p>
                                </div>
                                <div class="flex max-w-xl flex-wrap justify-end gap-1">
                                    @forelse ($studentTagNames as $tag)
                                        <span class="inline-flex items-center rounded-full border border-cyan-200 bg-cyan-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-cyan-800">
                                            {{ $tag }}
                                        </span>
                                    @empty
                                        <span class="rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-[11px] text-zinc-500">Belum ada tag untuk murid ini.</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <label class="text-xs font-semibold text-zinc-600">
                                    Cari Tag
                                    <input
                                        type="search"
                                        placeholder="Contoh: Pengawas, Ketua Kelas"
                                        data-select-search="student-tag-select-{{ $student->id }}"
                                        class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </label>

                                <label class="text-xs font-semibold text-zinc-600">
                                    Tag baharu
                                    <input
                                        type="text"
                                        name="new_social_tag_name"
                                        placeholder="Contoh: Ketua Kelas"
                                        class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </label>

                                <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                                    Pilih Tag
                                    <select
                                        id="student-tag-select-{{ $student->id }}"
                                        name="social_tag_ids[]"
                                        multiple
                                        size="{{ min(8, max(4, $availableSocialTags->count())) }}"
                                        class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    >
                                        @foreach ($availableSocialTags as $socialTag)
                                            <option value="{{ $socialTag->id }}" @selected($studentTagIds->contains((string) $socialTag->id))>{{ $socialTag->name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="mt-1 block text-[11px] text-zinc-500">Gunakan Ctrl/Cmd untuk pilih lebih daripada satu tag. Buang pilihan untuk Buang Tag.</span>
                                </label>

                                <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                                    Nota
                                    <textarea
                                        name="notes"
                                        rows="2"
                                        placeholder="Nota ringkas jika perlu"
                                        class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    ></textarea>
                                </label>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                                    Simpan Tag Murid
                                </button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <h2 class="text-lg font-semibold text-zinc-900">Tag Murid / Jawatan Murid</h2>
                <p class="mt-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-500">
                    Teacher access is read-only here. Tag murid hanya boleh dikemaskini oleh Super Admin.
                </p>
            </section>
        @endcan

        @can('manageStudentRecords')
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Status Murid</h2>
                        <p class="text-xs text-zinc-500">Murid dengan status tidak aktif tidak akan dikira dalam statistik kutipan, sasaran bayaran dan senarai belum bayar.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4">
                    @foreach ($students as $student)
                        <form
                            id="student-status-{{ $student->id }}"
                            method="POST"
                            action="{{ route('teacher.records.students.status.update', $student) }}"
                            class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4"
                        >
                            @csrf
                            @method('PATCH')
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-zinc-900">{{ $student->full_name }}</h3>
                                    <p class="text-xs text-zinc-500">{{ $student->class_name ?: 'Tiada kelas' }} | {{ $student->student_no ?: '-' }}</p>
                                    @if ($student->transferred_at)
                                        <p class="mt-1 text-[11px] text-zinc-500">
                                            Ditanda berpindah pada {{ $student->transferred_at->format('d M Y H:i') }}
                                        </p>
                                    @endif
                                </div>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ ! $student->isActiveStatus() ? 'border-zinc-300 bg-zinc-100 text-zinc-600' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                    {{ $student->statusLabel() }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <label class="text-xs font-semibold text-zinc-600">
                                    Status
                                    <select
                                        name="status"
                                        class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    >
                                        @foreach ($studentStatusOptions as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}" @selected((string) ($student->status ?: \App\Models\Student::STATUS_ACTIVE) === $statusValue)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                                    Catatan status
                                    <textarea
                                        name="transfer_note"
                                        rows="3"
                                        class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    >{{ old('transfer_note', $student->transfer_note ?? '') }}</textarea>
                                </label>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                                    Save Student Status
                                </button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </section>
        @endcan

        @can('manageStudentIdentity')
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Maklumat Murid</h2>
                        <p class="text-xs text-zinc-500">Kemaskini nama murid akan digunakan untuk carian, laporan dan paparan seterusnya.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4">
                    @foreach ($students as $student)
                        @php
                            $nameFormHasOldInput = (string) old('student_id') === (string) $student->id;
                            $nameHistory = $studentNameChangesByStudentId->get($student->id, collect());
                        @endphp
                        <form
                            id="student-info-{{ $student->id }}"
                            method="POST"
                            action="{{ route('teacher.records.students.name.update', $student) }}"
                            class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4"
                            data-student-name-form
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="student_id" value="{{ $student->id }}">

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                                    Student Name
                                    <input
                                        type="text"
                                        name="full_name"
                                        value="{{ $nameFormHasOldInput ? old('full_name') : $student->full_name }}"
                                        required
                                        minlength="3"
                                        class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm uppercase text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                        data-uppercase-input
                                    />
                                    @if ($nameFormHasOldInput)
                                        @error('full_name')
                                            <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                                        @enderror
                                    @endif
                                </label>

                                <label class="text-xs font-semibold text-zinc-600">
                                    Class
                                    <input
                                        type="text"
                                        value="{{ $student->class_name ?: '-' }}"
                                        readonly
                                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm text-zinc-700"
                                    />
                                </label>

                                <label class="text-xs font-semibold text-zinc-600">
                                    Student Number
                                    <input
                                        type="text"
                                        value="{{ $student->student_no ?: '-' }}"
                                        readonly
                                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm text-zinc-700"
                                    />
                                </label>

                                <label class="text-xs font-semibold text-zinc-600 md:col-span-2">
                                    Change Reason
                                    <textarea
                                        name="reason"
                                        rows="3"
                                        required
                                        class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    >{{ $nameFormHasOldInput ? old('reason') : '' }}</textarea>
                                    @if ($nameFormHasOldInput)
                                        @error('reason')
                                            <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                                        @enderror
                                    @endif
                                </label>
                            </div>

                            <div class="mt-3">
                                <button type="button" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700" data-open-student-name-confirm>
                                    Simpan Perubahan
                                </button>
                            </div>

                            <div class="mt-5 overflow-x-auto">
                                <h3 class="mb-2 text-sm font-semibold text-zinc-900">History Perubahan Nama</h3>
                                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                                    <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                        <tr>
                                            <th class="px-4 py-3">Tarikh</th>
                                            <th class="px-4 py-3">Nama Lama</th>
                                            <th class="px-4 py-3">Nama Baharu</th>
                                            <th class="px-4 py-3">Sebab</th>
                                            <th class="px-4 py-3">Dikemaskini Oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 bg-white">
                                        @forelse ($nameHistory as $change)
                                            <tr>
                                                <td class="px-4 py-3 text-zinc-700">{{ $change->created_at?->format('d M Y H:i') ?: '-' }}</td>
                                                <td class="px-4 py-3 font-semibold text-zinc-800">{{ $change->old_name }}</td>
                                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $change->new_name }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $change->reason }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $change->changedBy?->name ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-4 text-center text-zinc-500">Belum ada perubahan nama.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    @endforeach
                </div>
            </section>
        @endcan

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Payment History</h2>
                    <p class="text-xs text-zinc-500">Semua transaksi untuk family code ini.</p>
                </div>
                <a href="{{ $exportPaymentsUrl }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50">
                    Export payment log (CSV)
                </a>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <a href="{{ $allPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'all' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">All</a>
                <a href="{{ $successfulPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'successful' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">Successful</a>
                <a href="{{ $pendingPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'pending' ? 'border-amber-300 bg-amber-100 text-amber-800' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">Pending</a>
                <a href="{{ $cancelledPaymentsUrl }}" class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $paymentFilter === 'cancelled' ? 'border-rose-600 bg-rose-600 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' }}">Cancelled</a>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Order ID</th>
                            <th class="px-4 py-3">Bill Code</th>
                            <th class="px-4 py-3 text-right">Amount (RM)</th>
                            <th class="px-4 py-3 text-right">Sumbangan (RM)</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Return Status</th>
                            <th class="px-4 py-3">Sumbangan Intention</th>
                            <th class="px-4 py-3">Payer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($paymentHistory as $payment)
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->paid_at_for_display?->format('d M Y H:i') ?? $payment->created_at_for_display?->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $payment->external_order_display }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->provider_bill_code ?: '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-zinc-900">{{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((float) ($portalDonationByPaymentId[$payment->id] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ ucfirst((string) $payment->status) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->return_status ? ucfirst((string) $payment->return_status) : '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $payment->donation_intention ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-600">
                                    <div>{{ $payment->payer_name ?: '-' }}</div>
                                    <div class="text-xs">{{ $payment->payer_email ?: $payment->payer_phone ?: '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-6 text-center text-zinc-500">No payment history recorded for the selected filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @can('manageStudentRecords')
            <section id="update-parent-profile" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Update Family Parent Profile</h2>
                        <p class="text-xs text-zinc-500">Super Admin boleh kemas kini nama dan email parent untuk semua murid di family code ini.</p>
                    </div>
                </div>

                <form method="POST" action="{{ $updateParentProfileUrl }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <label class="text-xs font-semibold text-zinc-600">
                        Parent Name
                        <input
                            type="text"
                            name="parent_name"
                            value="{{ old('parent_name', $parentProfileName ?? '') }}"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                        @error('parent_name')
                            <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <label class="text-xs font-semibold text-zinc-600">
                        Parent Email
                        <input
                            type="email"
                            name="parent_email"
                            value="{{ old('parent_email', $parentProfileEmail ?? '') }}"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                        @error('parent_email')
                            <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <p class="sm:col-span-2 text-[11px] text-zinc-500">Boleh kemas kini satu medan sahaja (nama atau email), atau kedua-duanya sekali.</p>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                            Save Parent Profile
                        </button>
                    </div>
                </form>
            </section>
        @endcan

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Historical Paid Records (Imported)</h2>
                    <p class="text-xs text-zinc-500">Past-year paid history imported from legacy CSV.</p>
                </div>
                <div class="text-right text-sm text-zinc-700">
                    <div>Total paid: <span class="font-semibold">RM {{ number_format($legacyPaidTotal, 2) }}</span></div>
                    <div>Total sumbangan: <span class="font-semibold">RM {{ number_format($legacyDonationTotal, 2) }}</span></div>
                </div>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Paid At</th>
                            <th class="px-4 py-3">Ref</th>
                            <th class="px-4 py-3 text-right">Amount (RM)</th>
                            <th class="px-4 py-3 text-right">Sumbangan (RM)</th>
                            <th class="px-4 py-3">Year</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($legacyPayments as $legacy)
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $legacy->paid_at?->format('d M Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $legacy->payment_reference ?: '-' }}</td>
                                <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((float) $legacy->amount_paid, 2) }}</td>
                                <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((float) $legacy->donation_amount, 2) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $legacy->source_year }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-zinc-500">No historical paid record imported yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Access / Login Log</h2>
            <p class="text-xs text-zinc-500">Log permintaan TAC dan status log masuk parent berkaitan keluarga ini.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Requested At</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Linked User</th>
                            <th class="px-4 py-3">Channel</th>
                            <th class="px-4 py-3">Attempts</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Used At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($accessLogs as $log)
                            @php
                                $logStatus = $log->used_at
                                    ? 'Used'
                                    : (($log->expires_at && $log->expires_at->isPast()) ? 'Expired' : 'Pending');
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->created_at?->format('d M Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->phone ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-600">
                                    @if ($log->user_id)
                                        @php $linkedUser = $linkedParents->firstWhere('id', $log->user_id); @endphp
                                        <div>{{ $linkedUser?->name ?: 'Parent User #'.$log->user_id }}</div>
                                        <div class="text-xs">{{ $linkedUser?->email ?: '-' }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ strtoupper((string) $log->channel) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ (int) $log->attempts }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $logStatus === 'Used' ? 'bg-emerald-100 text-emerald-700' : ($logStatus === 'Expired' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $logStatus }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->used_at?->format('d M Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-zinc-500">No TAC access/login log found for this family yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="studentNameConfirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-900/60 px-4" role="dialog" aria-modal="true" aria-labelledby="studentNameConfirmTitle">
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
            <h2 id="studentNameConfirmTitle" class="text-lg font-semibold text-zinc-900">Sahkan Perubahan Nama</h2>
            <p class="mt-3 text-sm leading-6 text-zinc-700">Tindakan ini akan mengubah nama murid untuk semua transaksi dan paparan akan datang. Rekod sejarah pembayaran tidak akan dipadam. Teruskan?</p>
            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <button type="button" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50" data-student-name-cancel>
                    Batal
                </button>
                <button type="button" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700" data-student-name-submit>
                    Teruskan
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-tag-search]').forEach((input) => {
                const container = document.getElementById(input.dataset.tagSearch);
                if (! container) {
                    return;
                }

                input.addEventListener('input', () => {
                    const needle = input.value.trim().toLocaleLowerCase();
                    container.querySelectorAll('[data-tag-option]').forEach((option) => {
                        const label = option.dataset.tagOption || '';
                        option.classList.toggle('hidden', needle !== '' && ! label.includes(needle));
                    });
                });
            });

            document.querySelectorAll('[data-select-search]').forEach((input) => {
                const select = document.getElementById(input.dataset.selectSearch);
                if (! select) {
                    return;
                }

                input.addEventListener('input', () => {
                    const needle = input.value.trim().toLocaleLowerCase();
                    Array.from(select.options).forEach((option) => {
                        option.hidden = needle !== '' && ! option.text.toLocaleLowerCase().includes(needle);
                    });
                });
            });

            document.querySelectorAll('[data-uppercase-input]').forEach((input) => {
                input.addEventListener('input', () => {
                    const start = input.selectionStart;
                    const end = input.selectionEnd;
                    input.value = input.value.toLocaleUpperCase();
                    if (start !== null && end !== null) {
                        input.setSelectionRange(start, end);
                    }
                });
            });

            const modal = document.getElementById('studentNameConfirmModal');
            let pendingForm = null;

            document.querySelectorAll('[data-open-student-name-confirm]').forEach((button) => {
                button.addEventListener('click', () => {
                    pendingForm = button.closest('[data-student-name-form]');

                    if (pendingForm && ! pendingForm.reportValidity()) {
                        return;
                    }

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            document.querySelector('[data-student-name-cancel]')?.addEventListener('click', () => {
                pendingForm = null;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            });

            document.querySelector('[data-student-name-submit]')?.addEventListener('click', () => {
                pendingForm?.submit();
            });
        });
    </script>

</x-layouts::app>
