<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalSeoSettingsController extends Controller
{
    public function index(): View
    {
        return view('system.portal-seo', [
            'settings' => SiteSetting::getMany($this->defaultSettings()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seo_site_title' => ['required', 'string', 'max:160'],
            'seo_description' => ['required', 'string', 'max:500'],
            'seo_keywords' => ['required', 'string', 'max:600'],
            'seo_og_site_name' => ['required', 'string', 'max:160'],
            'school_logo_url' => ['nullable', 'string', 'max:400'],
            'school_logo_file' => ['nullable', 'image', 'max:2048'],
            'order_id_shortform' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z0-9]{3}$/'],
        ]);

        $schoolLogoUrl = trim((string) ($validated['school_logo_url'] ?? ''));

        if ($request->hasFile('school_logo_file')) {
            $path = $request->file('school_logo_file')->store('branding', 'public');
            $schoolLogoUrl = asset('storage/'.$path);
        }

        if ($schoolLogoUrl === '') {
            $schoolLogoUrl = SiteSetting::schoolLogoUrl();
        }

        SiteSetting::setMany([
            'seo_site_title' => trim((string) $validated['seo_site_title']),
            'seo_description' => trim((string) $validated['seo_description']),
            'seo_keywords' => trim((string) $validated['seo_keywords']),
            'seo_og_site_name' => trim((string) $validated['seo_og_site_name']),
            'seo_favicon_url' => $schoolLogoUrl,
            'school_logo_url' => $schoolLogoUrl,
            'order_id_shortform' => strtoupper((string) $validated['order_id_shortform']),
        ]);

        return redirect()
            ->route('system.portal-seo.index')
            ->with('status', 'Portal branding and SEO settings updated successfully.');
    }

    /**
     * @return array<string, string>
     */
    private function defaultSettings(): array
    {
        return [
            'seo_site_title' => 'Portal Yuran PIBG SK Sri Petaling',
            'seo_description' => 'Portal rasmi semakan dan pembayaran Yuran & Sumbangan PIBG SK Sri Petaling, didukung oleh Avante Intelligence dan Arif.my sebagai inisiatif pendigitalan pendidikan sekolah.',
            'seo_keywords' => 'Portal Yuran PIBG, SK Sri Petaling, Avante Intelligence, Arif.my, digitalisasi pendidikan, pendigitalan sekolah, semakan yuran, pembayaran PIBG, portal ibu bapa, inisiatif pendidikan digital',
            'seo_og_site_name' => 'Portal Yuran PIBG SK Sri Petaling',
            'seo_favicon_url' => asset('images/sksp-logo.png'),
            'school_logo_url' => asset('images/sksp-logo.png'),
            'order_id_shortform' => 'PBG',
        ];
    }
}
