<x-layouts::guest>
    <div class="min-h-screen flex items-center justify-center bg-zinc-950/40 px-4 py-10">
        <div class="w-full max-w-sm space-y-8 rounded-3xl bg-white/80 p-8 shadow-2xl shadow-black/30 backdrop-blur dark:bg-zinc-900 dark:text-zinc-100">
            <div>
                <a
                    href="{{ route('home') }}"
                    class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:border-emerald-500/60 dark:hover:text-emerald-300"
                >
                    <span class="text-base leading-none">&larr;</span>
                    Back to portal
                </a>
            </div>

            <div class="flex flex-col items-center gap-3 text-center">
                <x-application-logo class="h-12 w-12 text-zinc-800 dark:text-zinc-100" />
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Log Masuk Penjaga / Ibu Bapa</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Masukkan nombor telefon anda untuk menerima kod TAC melalui WhatsApp.</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Nota tambahan: Sila hubungi Sokongan Teknikal (+60 11-1105 5569) jika memerlukan bantuan.</p>
            </div>

            <form action="#" method="POST" class="space-y-5">
                <div>
                    <label for="parent_phone" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Phone number</label>
                    <input id="parent_phone" name="parent_phone" type="tel" placeholder="0123456789" class="mt-2 w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-base text-zinc-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" required />
                </div>

                <button type="submit" class="w-full rounded-2xl bg-[#111827] px-4 py-3 text-sm font-semibold uppercase tracking-[0.08em] text-white transition hover:bg-zinc-900">Send TAC</button>
            </form>

            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-4 text-xs text-zinc-700 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/70 dark:text-zinc-200">
                <p>Need help?</p>
                <p>Pn. Mariam: <span class="font-semibold text-emerald-600">013 6454 001</span></p>
                <p>En. Haron: <span class="font-semibold text-emerald-600">012 310 3205</span></p>
                <p class="mt-2 text-[11px]">If WhatsApp is not available, an email pin is sent to the registered address.</p>
            </div>

        </div>
    </div>
</x-layouts::guest>

