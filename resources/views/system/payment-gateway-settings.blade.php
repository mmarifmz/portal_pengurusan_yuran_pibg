<x-layouts::app :title="__('Tetapan Payment Gateway')">
    <div class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
            <h1 class="text-2xl font-bold text-zinc-900">Tetapan Payment Gateway</h1>
            <p class="text-sm text-zinc-500">Kawal kaedah pembayaran ToyyibPay yang dibenarkan secara global untuk seluruh portal.</p>
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
            <form method="POST" action="{{ route('system.payment-gateway-settings.save') }}" class="grid gap-4">
                @csrf

                <label class="inline-flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700">
                    <input type="hidden" name="enable_fpx" value="0">
                    <input type="checkbox" name="enable_fpx" value="1" @checked(old('enable_fpx', $paymentGatewaySetting->enable_fpx))>
                    Aktifkan FPX
                </label>

                <label class="inline-flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700">
                    <input type="hidden" name="enable_duitnow_qr" value="0">
                    <input type="checkbox" name="enable_duitnow_qr" value="1" @checked(old('enable_duitnow_qr', $paymentGatewaySetting->enable_duitnow_qr))>
                    Aktifkan DuitNow QR
                </label>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-4">
                    <p class="text-sm font-semibold text-zinc-900">Caj DuitNow QR ditanggung oleh pembayar</p>
                    <p class="mt-1 text-sm text-zinc-600">{{ $paymentGatewaySetting->qrServiceFeeNotice() }}</p>
                </div>

                <div>
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Simpan Tetapan Payment Gateway
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::app>
