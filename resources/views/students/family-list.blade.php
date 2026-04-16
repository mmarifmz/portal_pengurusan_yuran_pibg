<x-layouts::app :title="__('Family Registry')" class="space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <x-kpi-card title="Families" :value="number_format($totalFamilies)" icon="users" />
        <x-kpi-card title="Students" :value="number_format($totalStudents)" icon="user-group" />
        <x-kpi-card title="Total billed (RM)" :value="number_format($totalBilled, 2)" icon="banknotes" />
        <x-kpi-card title="Outstanding (RM)" :value="number_format($totalOutstanding, 2)" icon="exclamation-triangle" />
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">Family registry</p>
                <h2 class="text-xl font-semibold text-zinc-900">Kod keluarga & status</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('billing.setup.current-year') }}" class="inline-flex">
                    @csrf
                    <button type="submit" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Generate family billing</button>
                </form>
                <a href="{{ route('students.import.form') }}" class="rounded-2xl border border-zinc-200 px-4 py-2 text-xs font-semibold text-zinc-900 hover:border-emerald-500">Tambah murid</a>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3 text-xs text-zinc-600">
            <label class="inline-flex flex-col font-semibold">
                Carian
                <input id="familyRegistrySearch" type="search" placeholder="Cari kod, penjaga atau komen" class="mt-1 w-48 rounded-2xl border border-zinc-200 px-3 py-1 text-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
            </label>
            <label class="inline-flex flex-col font-semibold">
                Kelas
                <select id="familyRegistryClass" class="mt-1 rounded-2xl border border-zinc-200 bg-white px-3 py-1 text-xs outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    <option value="All">Semua kelas</option>
                    @foreach ($classFilters as $classOption)
                        <option value="{{ $classOption }}">{{ $classOption }}</option>
                    @endforeach
                </select>
            </label>
            <label class="inline-flex flex-col font-semibold">
                Status
                <select id="familyRegistryStatus" class="mt-1 rounded-2xl border border-zinc-200 bg-white px-3 py-1 text-xs outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    <option value="All">Semua status</option>
                    @foreach ($statusFilters as $statusOption)
                        <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-700">
                <thead class="text-xs uppercase tracking-wider text-zinc-500">
                    <tr>
                        <th class="px-3 py-2">Kod keluarga</th>
                        <th class="px-3 py-2">Penjaga</th>
                        <th class="px-3 py-2 text-center">Bilangan anak</th>
                        <th class="px-3 py-2 text-right">Jumlah bayar (RM)</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 hidden lg:table-cell">Komen</th>
                        <th class="px-3 py-2 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody id="familyRegistryBody">
                    @forelse ($familyRecords as $family)
                        <tr class="border-t border-zinc-100" data-family-row
                            data-family-code="{{ $family['family_code'] }}"
                            data-guardian="{{ $family['guardian'] }}"
                            data-status="{{ $family['status'] }}"
                            data-comment="{{ $family['comment'] }}"
                            data-classes="{{ $family['classes'] }}">
                            <td class="px-3 py-3 font-semibold text-zinc-900">{{ $family['family_code'] }}</td>
                            <td class="px-3 py-3">{{ $family['guardian'] }}</td>
                            <td class="px-3 py-3 text-center font-semibold text-zinc-900">{{ $family['children'] }}</td>
                            <td class="px-3 py-3 text-right font-semibold text-emerald-600">RM {{ number_format($family['amount_due'], 2) }}</td>
                            <td class="px-3 py-3">{{ $family['status'] }}</td>
                            <td class="px-3 py-3 hidden lg:table-cell text-zinc-500">{{ $family['comment'] }}</td>
                            <td class="px-3 py-3 text-center">
                                <button type="button" data-details-button class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                    {{ __('Lihat murid') }}
                                </button>
                                <script type="application/json" data-family-children>@json($family['children_list'])</script>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-xs text-zinc-500">{{ __('Tiada keluarga berdaftar lagi.') }}</td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    <div id="familyRegistryModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 px-4 py-6" style="display: none;">
        <div class="w-full max-w-lg rounded-3xl border border-zinc-200 bg-white p-5 shadow-2xl">
            <header class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Butiran murid</p>
                    <h3 class="text-lg font-semibold text-zinc-900" id="familyRegistryModalTitle"></h3>
                </div>
                <button type="button" data-close-modal class="rounded-full border border-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-900">Tutup</button>
            </header>
            <p class="mt-2 text-sm text-zinc-500" id="familyRegistryModalComment"></p>
            <div class="mt-4 space-y-3" id="familyRegistryModalList"></div>
        </div>
    </div>

    @once
        <script>
            function initFamilyRegistryModal() {
                const searchInput = document.getElementById('familyRegistrySearch');
                const classFilter = document.getElementById('familyRegistryClass');
                const statusFilter = document.getElementById('familyRegistryStatus');
                const rows = document.querySelectorAll('[data-family-row]');
                const modal = document.getElementById('familyRegistryModal');

                if (!modal || modal.dataset.initialized === 'true') {
                    return;
                }

                modal.dataset.initialized = 'true';

                function applyFilters() {
                    const search = searchInput?.value.trim().toLowerCase() ?? '';
                    const selectedClass = classFilter?.value;
                    const selectedStatus = statusFilter?.value;

                    rows.forEach((row) => {
                        const code = row.dataset.familyCode?.toLowerCase() ?? '';
                        const status = row.dataset.status?.toLowerCase() ?? '';
                        const comment = row.dataset.comment?.toLowerCase() ?? '';
                        const classes = row.dataset.classes ? row.dataset.classes.split(',').map((value) => value.trim()) : [];

                        const matchesSearch = !search || code.includes(search) || comment.includes(search);
                        const matchesClass = !selectedClass || selectedClass === 'All' || classes.includes(selectedClass);
                        const matchesStatus = !selectedStatus || selectedStatus === 'All' || status === selectedStatus.toLowerCase();

                        row.style.display = matchesSearch && matchesClass && matchesStatus ? '' : 'none';
                    });
                }

                searchInput?.addEventListener('input', applyFilters);
                classFilter?.addEventListener('change', applyFilters);
                statusFilter?.addEventListener('change', applyFilters);

                const modalTitle = document.getElementById('familyRegistryModalTitle');
                const modalList = document.getElementById('familyRegistryModalList');
                const modalComment = document.getElementById('familyRegistryModalComment');
                const closeButton = modal?.querySelector('[data-close-modal]');
                const detailButtons = document.querySelectorAll('[data-details-button]');

                function showModal() {
                    if (!modal) return;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.style.display = 'flex';
                }

                function hideModal() {
                    if (!modal) return;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    modal.style.display = 'none';
                }

                function openModal(title, comment, children) {
                    if (!modal) return;

                    modalTitle.textContent = title;
                    modalComment.textContent = comment || 'Tiada komen tambahan.';
                    modalList.innerHTML = '';

                    if (!children.length) {
                        modalList.innerHTML = '<p class="text-sm text-zinc-500">Tiada murid dalam keluarga ini.</p>';
                        return;
                    }

                    children.forEach((child) => {
                        const item = document.createElement('div');
                        item.className = 'rounded-2xl border border-zinc-100 bg-zinc-50 px-4 py-3 text-sm text-zinc-700';
                        item.innerHTML = `<p class="font-semibold text-zinc-900">${child.full_name}</p><p class="text-xs text-zinc-500">${child.class_name} · ${child.status}</p>`;
                        modalList.append(item);
                    });
                }

                detailButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const row = button.closest('[data-family-row]');
                        if (!row) return;
                        const familyCode = row.dataset.familyCode;
                        const comment = row.dataset.comment;
                        let children = [];
                        const childrenPayload = row.querySelector('[data-family-children]');

                        try {
                            children = JSON.parse(childrenPayload?.textContent || '[]');
                        } catch (error) {
                            console.error('Unable to parse family children payload.', error);
                        }

                        openModal(familyCode, comment, children);
                        showModal();
                    });
                });

                closeButton?.addEventListener('click', () => {
                    hideModal();
                });

                modal?.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        hideModal();
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', initFamilyRegistryModal);
            document.addEventListener('livewire:navigated', initFamilyRegistryModal);
        </script>
    @endonce
</x-layouts::app>
