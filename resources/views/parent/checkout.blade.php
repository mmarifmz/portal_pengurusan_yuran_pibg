<x-layouts::app :title="__('Bayaran PIBG')">
    <div class="space-y-6">
        <div class="flex flex-wrap gap-4">
            <div class="flex-1 rounded-3xl border border-zinc-200 bg-white p-6 shadow-lg">
                <h2 class="text-lg font-bold text-[color:var(--brand-forest)]">Bil Bayaran</h2>
                <p class="mt-1 text-sm text-zinc-600">Kod keluarga: <span class="font-semibold">{{ $familyBilling->family_code }}</span></p>
                <div class="mt-4 space-y-1 text-sm text-zinc-500">
                    <p>Yuran PIBG {{ $familyBilling->billing_year }}</p>
                    <p>Jumlah Anak: {{ $familyChildren->count() }}</p>
                    <p>Kod keluarga dikenakan sekali sahaja setahun.</p>
                </div>
                <div class="mt-5 text-3xl font-bold text-[color:var(--brand-green)]">
                    RM {{ number_format($familyBilling->outstanding_amount, 2) }}
                </div>
            </div>

            <div class="flex-1 rounded-3xl border border-zinc-200 bg-white p-6 shadow-lg">
                <h3 class="text-base font-bold text-zinc-700">Maklumat Pembayaran</h3>
                <p class="mt-1 text-sm text-zinc-500">Sistem akan memindahkan anda ke platform ToyyibPay untuk pilihan FPX atau kad.</p>

                <form action="{{ route('parent.payments.create', $familyBilling) }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Email</label>
                        <input name="payer_email" type="email" required class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm" value="{{ old('payer_email', $defaultEmail) }}">
                        <x-auth-session-status class="text-xs text-red-600" :status="$errors->first('payer_email')" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">No Telefon</label>
                        <input name="payer_phone" type="text" required class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm" value="{{ old('payer_phone', $defaultPhone) }}">
                        <x-auth-session-status class="text-xs text-red-600" :status="$errors->first('payer_phone')" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Derma Tambahan</label>
                        <div class="flex flex-wrap gap-2 text-xs">
                            @foreach([50,100,250,500,1000] as $preset)
                                <button type="button" class="rounded-full border border-zinc-300 px-3 py-1 text-left text-sm text-zinc-700 hover:bg-zinc-50 js-presets" data-amount="{{ $preset }}">RM{{ $preset }}</button>
                            @endforeach
                        </div>
                        <input name="donation_custom" type="number" min="0" step="0.01" class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm" placeholder="Masukkan jumlah derma">
                    </div>
                    <input type="hidden" name="donation_preset" value="">
                    <div class="flex items-center justify-between rounded-2xl border border-zinc-200 bg-[color:var(--brand-soft)] px-4 py-3 text-sm text-zinc-600">
                        <span>Jumlah Bayaran</span>
                        <strong>RM {{ number_format($familyBilling->outstanding_amount, 2) }}</strong>
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-[color:var(--brand-green)] px-4 py-3 text-sm font-semibold text-white">Sahkan & Bayar</button>
                </form>
            </div>
        </div>

        <section class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-lg">
            <h3 class="text-base font-bold text-[color:var(--brand-forest)]">Senarai Anak</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach($familyChildren as $child)
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm">
                        <p class="font-semibold text-zinc-900">{{ $child->full_name }}</p>
                        <p class="text-xs text-zinc-500">{{ $child->class_name }}</p>
                        <p class="text-[0.65rem] text-zinc-500">No. Murid: {{ $child->student_no }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('.js-presets').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelector('input[name=\"donation_preset\"]').value = button.dataset.amount;
                    document.querySelector('input[name=\"donation_custom\"]').value = button.dataset.amount;
                });
            });
        </script>
    @endpush
</x-layouts::app>
