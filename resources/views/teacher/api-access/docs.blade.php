<x-layouts::app :title="__('API Documentation')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">API Access</p>
            <h1 class="mt-1 text-2xl font-bold text-zinc-900">API Documentation</h1>
            <p class="mt-2 max-w-3xl text-sm text-zinc-600">Teachers can search PIBG payment status by student name, guardian name, family code, class, or phone number. Responses use existing family billing status, payment totals, and receipt URLs from the portal.</p>
        </section>

        <section class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-zinc-900">Request</h2>
                <div class="mt-4 space-y-5 text-sm text-zinc-700">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Endpoint</p>
                        <code class="mt-1 block rounded-xl bg-zinc-100 px-3 py-2 text-zinc-900">GET {{ url('/api/v1/payment-status/search') }}</code>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Required Header</p>
                        <code class="mt-1 block rounded-xl bg-zinc-100 px-3 py-2 text-zinc-900">Authorization: Bearer YOUR_API_KEY</code>
                        <code class="mt-2 block rounded-xl bg-zinc-100 px-3 py-2 text-zinc-900">Accept: application/json</code>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Query Parameters</p>
                        <div class="mt-2 overflow-hidden rounded-xl border border-zinc-200">
                            <table class="min-w-full divide-y divide-zinc-200">
                                <tbody class="divide-y divide-zinc-200">
                                    <tr><td class="px-3 py-2 font-semibold text-zinc-900">q</td><td class="px-3 py-2">Required search keyword.</td></tr>
                                    <tr><td class="px-3 py-2 font-semibold text-zinc-900">year</td><td class="px-3 py-2">Optional billing year, for example 2026.</td></tr>
                                    <tr><td class="px-3 py-2 font-semibold text-zinc-900">class</td><td class="px-3 py-2">Optional class filter, for example 5 AZALEA.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-zinc-900">curl Example</h2>
                <pre class="mt-4 overflow-x-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-50"><code>curl -X GET "{{ url('/api/v1/payment-status/search?q=putri%20auni&year=2026') }}" \
-H "Authorization: Bearer YOUR_API_KEY" \
-H "Accept: application/json"</code></pre>

                <h2 class="mt-6 text-lg font-bold text-zinc-900">Example JSON Response</h2>
                <pre class="mt-4 overflow-x-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-50"><code>{
  "success": true,
  "query": "putri auni",
  "count": 1,
  "data": [
    {
      "family_code": "SSP-0022",
      "bill_year": 2026,
      "guardian_name": "MAS ALIF",
      "students": [
        {
          "name": "PUTRI AUNI MEDINA BINTI MAS ALIF",
          "class": "5 AZALEA",
          "status": "Aktif"
        }
      ],
      "payment": {
        "status": "Paid",
        "status_label": "Telah Bayar",
        "total_due": "100.00",
        "total_paid": "100.00",
        "outstanding": "0.00",
        "latest_payment_date": "2026-05-21",
        "receipt_url": "https://sumbangan-pibg.sripetaling.edu.my/receipts/xxxxx",
        "remarks": null
      }
    }
  ]
}</code></pre>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-zinc-900">Common Errors</h2>
            <div class="mt-4 grid gap-3 text-sm text-zinc-700 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3"><span class="font-semibold text-zinc-900">401</span> Invalid API key</div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3"><span class="font-semibold text-zinc-900">403</span> API key revoked</div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3"><span class="font-semibold text-zinc-900">422</span> Missing search keyword</div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3"><span class="font-semibold text-zinc-900">429</span> Too many requests</div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3"><span class="font-semibold text-zinc-900">500</span> Server error</div>
            </div>
        </section>
    </div>
</x-layouts::app>
