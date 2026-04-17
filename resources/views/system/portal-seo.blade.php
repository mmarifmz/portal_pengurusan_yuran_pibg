<x-layouts::app :title="__('Portal SEO Settings')">
    <div class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
            <h1 class="text-2xl font-bold text-zinc-900">Portal SEO & Metadata</h1>
            <p class="text-sm text-zinc-500">Manage global title, description, keywords and favicon across all pages.</p>
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
            <form method="POST" action="{{ route('system.portal-seo.update') }}" class="grid gap-4">
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
                    Favicon URL
                    <input
                        name="seo_favicon_url"
                        type="text"
                        required
                        value="{{ old('seo_favicon_url', $settings['seo_favicon_url'] ?? '') }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <div>
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Save SEO Settings
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::app>
