<?php

namespace App\Http\Controllers;

use App\Models\PaymentCampaignSetting;
use App\Models\SocialTag;
use App\Services\PaymentCampaignService;
use App\Services\SocialTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentCampaignSettingController extends Controller
{
    public function __construct(
        private readonly PaymentCampaignService $paymentCampaignService,
        private readonly SocialTagService $socialTagService
    )
    {
    }

    public function index(Request $request): View
    {
        $latestSetting = PaymentCampaignSetting::query()
            ->with(['split2SocialTag', 'split3SocialTag'])
            ->latest('updated_at')
            ->latest('id')
            ->first();

        $history = PaymentCampaignSetting::query()
            ->with(['split2SocialTag', 'split3SocialTag'])
            ->latest('updated_at')
            ->latest('id')
            ->limit(10)
            ->get();

        $selectedSettingId = (int) $request->integer('campaign_id', 0);
        $formMode = trim((string) $request->query('mode', 'latest'));
        if (! in_array($formMode, ['latest', 'edit', 'duplicate'], true)) {
            $formMode = 'latest';
        }

        $selectedHistorySetting = $selectedSettingId > 0
            ? PaymentCampaignSetting::query()
                ->with(['split2SocialTag', 'split3SocialTag'])
                ->find($selectedSettingId)
            : null;

        $formSetting = $latestSetting;
        $formSettingId = $latestSetting?->id;
        $formHeading = 'Kempen Semasa';
        $formDescription = 'Kemas kini tetapan kempen semasa atau cipta kempen baharu.';

        if ($selectedHistorySetting && $formMode === 'edit') {
            $formSetting = $selectedHistorySetting;
            $formSettingId = $selectedHistorySetting->id;
            $formHeading = 'Edit Kempen';
            $formDescription = 'Anda sedang mengemas kini rekod kempen yang dipilih dari sejarah.';
        } elseif ($selectedHistorySetting && $formMode === 'duplicate') {
            $formSetting = $selectedHistorySetting->replicate();
            $formSetting->campaign_name = trim((string) $selectedHistorySetting->campaign_name).' (Salinan)';
            $formSetting->is_active = false;
            $formSettingId = null;
            $formHeading = 'Duplicate Kempen';
            $formDescription = 'Salinan ini akan disimpan sebagai rekod kempen baharu supaya sejarah asal kekal selamat.';
        }

        return view('system.payment-campaign-settings', [
            'currentSetting' => $formSetting,
            'formSettingId' => $formSettingId,
            'formMode' => $formMode,
            'formHeading' => $formHeading,
            'formDescription' => $formDescription,
            'selectedHistorySetting' => $selectedHistorySetting,
            'latestSetting' => $latestSetting,
            'history' => $history,
            'socialTags' => SocialTag::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $settingId = (int) $request->input('setting_id', 0);
        $setting = $settingId > 0 ? PaymentCampaignSetting::query()->findOrFail($settingId) : new PaymentCampaignSetting();

        $validated = $request->validate([
            'campaign_name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'allow_single_payment' => ['nullable', 'boolean'],
            'allow_split_payment' => ['nullable', 'boolean'],
            'allow_split_2' => ['nullable', 'boolean'],
            'split_2_visibility' => ['required', Rule::in([
                PaymentCampaignSetting::VISIBILITY_ALL,
                PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG,
            ])],
            'split_2_social_tag_id' => ['nullable', 'integer', 'exists:social_tags,id'],
            'split_2_social_tag' => ['nullable', 'string', 'max:255'],
            'allow_split_3' => ['nullable', 'boolean'],
            'split_3_visibility' => ['required', Rule::in([
                PaymentCampaignSetting::VISIBILITY_ALL,
                PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG,
            ])],
            'split_3_social_tag_id' => ['nullable', 'integer', 'exists:social_tags,id'],
            'split_3_social_tag' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $isActive = (bool) ($validated['is_active'] ?? false);
        $allowSingle = (bool) ($validated['allow_single_payment'] ?? false);
        $allowSplit2 = (bool) ($validated['allow_split_2'] ?? false);
        $allowSplit3 = (bool) ($validated['allow_split_3'] ?? false);
        $allowSplit = (bool) ($validated['allow_split_payment'] ?? false) || $allowSplit2 || $allowSplit3;
        $split2Visibility = (string) ($validated['split_2_visibility'] ?? PaymentCampaignSetting::VISIBILITY_ALL);
        $split3Visibility = (string) ($validated['split_3_visibility'] ?? PaymentCampaignSetting::VISIBILITY_ALL);
        $split2SocialTag = $this->resolveTagSelection($validated['split_2_social_tag_id'] ?? null, $validated['split_2_social_tag'] ?? null, $request);
        $split3SocialTag = $this->resolveTagSelection($validated['split_3_social_tag_id'] ?? null, $validated['split_3_social_tag'] ?? null, $request);

        if (! $allowSingle && ! $allowSplit2 && ! $allowSplit3) {
            return back()
                ->withErrors(['campaign_name' => 'Sila aktifkan sekurang-kurangnya satu pilihan bayaran.'])
                ->withInput();
        }

        if ($allowSplit2 && $split2Visibility === PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG && ! $split2SocialTag) {
            return back()
                ->withErrors(['split_2_social_tag_id' => 'Tag sosial diperlukan untuk Ansuran 2 Kali apabila menggunakan pilihan Berdasarkan Tag Sosial.'])
                ->withInput();
        }

        if ($allowSplit3 && $split3Visibility === PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG && ! $split3SocialTag) {
            return back()
                ->withErrors(['split_3_social_tag_id' => 'Tag sosial diperlukan untuk Ansuran 3 Kali apabila menggunakan pilihan Berdasarkan Tag Sosial.'])
                ->withInput();
        }

        if ($isActive) {
            PaymentCampaignSetting::query()
                ->when($setting->exists, fn ($query) => $query->whereKeyNot($setting->id))
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        if (! $setting->exists) {
            $setting->created_by = $request->user()?->id;
        }

        $wasExisting = $setting->exists;

        $setting->fill([
            'campaign_name' => trim((string) $validated['campaign_name']),
            'is_active' => $isActive,
            'allow_single_payment' => $allowSingle,
            'allow_split_payment' => $allowSplit,
            'allow_split_2' => $allowSplit2,
            'split_2_visibility' => $split2Visibility,
            'split_2_social_tag' => $allowSplit2 && $split2Visibility === PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG ? $split2SocialTag?->name : null,
            'split_2_social_tag_id' => $allowSplit2 && $split2Visibility === PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG ? $split2SocialTag?->id : null,
            'allow_split_3' => $allowSplit3,
            'split_3_visibility' => $split3Visibility,
            'split_3_social_tag' => $allowSplit3 && $split3Visibility === PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG ? $split3SocialTag?->name : null,
            'split_3_social_tag_id' => $allowSplit3 && $split3Visibility === PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG ? $split3SocialTag?->id : null,
            'effective_from' => $validated['effective_from'] ?? null,
            'effective_until' => $validated['effective_until'] ?? null,
            'updated_by' => $request->user()?->id,
        ])->save();

        return redirect()
            ->route('system.payment-campaign-settings.index')
            ->with('status', $wasExisting
                ? 'Kempen bayaran berjaya dikemas kini.'
                : 'Kempen bayaran baharu berjaya dicipta.');
    }

    private function resolveTagSelection(mixed $tagId, mixed $legacyTagName, Request $request): ?SocialTag
    {
        if ((int) $tagId > 0) {
            return SocialTag::query()->find((int) $tagId);
        }

        $tag = trim((string) $legacyTagName);

        return $tag === '' ? null : $this->socialTagService->findOrCreateByName($tag, $request->user()?->id);
    }
}
