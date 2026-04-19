<x-layouts::app :title="__('Portal SEO Settings')">
    <div class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
            <h1 class="text-2xl font-bold text-zinc-900">Portal SEO & Branding</h1>
            <p class="text-sm text-zinc-500">Manage global metadata and one shared school logo used across portal UI, favicon, web receipt, and PDF documents.</p>
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
            <form method="POST" action="{{ route('system.portal-seo.update') }}" enctype="multipart/form-data" class="grid gap-4">
                @csrf
                @method('PATCH')

                <label class="text-sm font-medium text-zinc-700">
                    Site Title
                    <input
                        name="seo_site_title"
                        type="text"
                        required
                        value="{{ old('seo_site_title', $settings['seo_site_title'] ?? '') }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    Meta Description
                    <textarea
                        name="seo_description"
                        rows="3"
                        required
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    >{{ old('seo_description', $settings['seo_description'] ?? '') }}</textarea>
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    Meta Keywords
                    <textarea
                        name="seo_keywords"
                        rows="3"
                        required
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    >{{ old('seo_keywords', $settings['seo_keywords'] ?? '') }}</textarea>
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    OpenGraph Site Name
                    <input
                        name="seo_og_site_name"
                        type="text"
                        required
                        value="{{ old('seo_og_site_name', $settings['seo_og_site_name'] ?? '') }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    School Logo URL
                    <input
                        name="school_logo_url"
                        type="text"
                        value="{{ old('school_logo_url', $settings['school_logo_url'] ?? '') }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                    <span class="mt-1 block text-xs text-zinc-500">Optional. If you upload a file below, the uploaded file will override this URL.</span>
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    Upload School Logo
                    <input
                        name="school_logo_file"
                        type="file"
                        accept="image/*"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-emerald-700"
                    />
                    <span class="mt-1 block text-xs text-zinc-500">PNG/JPG/WEBP, max 2MB. This one logo is used for portal, favicon, and receipts.</span>
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    Order ID Shortform (3 chars)
                    <input
                        name="order_id_shortform"
                        type="text"
                        maxlength="3"
                        required
                        value="{{ old('order_id_shortform', $settings['order_id_shortform'] ?? 'PBG') }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm uppercase text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                    <span class="mt-1 block text-xs text-zinc-500">Digunakan sebagai suffix Order ID ringkas. Contoh: <span class="font-semibold">PBG-260417-A1B2-{{ old('order_id_shortform', $settings['order_id_shortform'] ?? 'PBG') }}</span></span>
                </label>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Student Social Tags</p>
                    <p class="mt-1 text-xs text-zinc-500">Kosongkan mana-mana label jika tidak mahu dipaparkan dalam mod edit profile.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <label class="text-sm font-medium text-zinc-700">
                            Tag 1 label
                            <input
                                name="social_tag_label_b40"
                                type="text"
                                maxlength="30"
                                value="{{ old('social_tag_label_b40', $settings['social_tag_label_b40'] ?? 'B40') }}"
                                class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm uppercase text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>
                        <label class="text-sm font-medium text-zinc-700">
                            Tag 2 label
                            <input
                                name="social_tag_label_kwap"
                                type="text"
                                maxlength="30"
                                value="{{ old('social_tag_label_kwap', $settings['social_tag_label_kwap'] ?? 'KWAP') }}"
                                class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm uppercase text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>
                        <label class="text-sm font-medium text-zinc-700">
                            Tag 3 label
                            <input
                                name="social_tag_label_rmt"
                                type="text"
                                maxlength="30"
                                value="{{ old('social_tag_label_rmt', $settings['social_tag_label_rmt'] ?? 'RMT') }}"
                                class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm uppercase text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Current Logo Preview</p>
                    <img src="{{ $settings['school_logo_url'] ?? asset('images/sksp-logo.png') }}" alt="Current school logo" class="mt-2 h-14 w-14 rounded-full border border-zinc-200 bg-white p-1" />
                </div>

                <div>
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Save SEO & Branding
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::app>
