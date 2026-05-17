<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentPlan;
use App\Models\PaymentCampaignSetting;
use Illuminate\Support\Collection;

class PaymentCampaignService
{
    public function __construct(private readonly SocialTagService $socialTagService)
    {
    }

    /**
     * @return array<string, string>
     */
    public function socialTagLabels(): array
    {
        return $this->socialTagService->legacyTagLabels();
    }

    /**
     * @return array<int, string>
     */
    public function campaignTagSuggestions(): array
    {
        return collect($this->socialTagService->activeTagNames())
            ->map(fn (string $tag): string => $this->normalizeTag($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function activeCampaign(): ?PaymentCampaignSetting
    {
        return PaymentCampaignSetting::query()
            ->with(['split2SocialTag', 'split3SocialTag'])
            ->activeWindow()
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    public function eligiblePlanTypes(FamilyBilling $familyBilling): array
    {
        $campaign = $this->activeCampaign();

        if (! $campaign) {
            return [FamilyPaymentPlan::PLAN_FULL];
        }

        $socialTags = $this->resolveFamilySocialTags($familyBilling);
        $allowed = [];

        if ($campaign->allow_single_payment) {
            $allowed[] = FamilyPaymentPlan::PLAN_FULL;
        }

        if ($campaign->allow_split_payment) {
            if ($campaign->allow_split_2 && $this->matchesVisibility(
                (string) $campaign->split_2_visibility,
                $campaign->split2SocialTag?->name ?? $campaign->split_2_social_tag,
                $socialTags
            )) {
                $allowed[] = FamilyPaymentPlan::PLAN_TWO_TIMES;
            }

            if ($campaign->allow_split_3 && $this->matchesVisibility(
                (string) $campaign->split_3_visibility,
                $campaign->split3SocialTag?->name ?? $campaign->split_3_social_tag,
                $socialTags
            )) {
                $allowed[] = FamilyPaymentPlan::PLAN_THREE_TIMES;
            }
        }

        return array_values(array_unique($allowed));
    }

    public function resolveFamilySocialTag(FamilyBilling $familyBilling): ?string
    {
        return $this->resolveFamilySocialTags($familyBilling)->first();
    }

    /**
     * @return Collection<int, string>
     */
    public function resolveFamilySocialTags(FamilyBilling $familyBilling): Collection
    {
        return $this->socialTagService->resolveFamilyTagNames($familyBilling);
    }

    /**
     * @return array<int, string>
     */
    public function eligiblePlanLabels(FamilyBilling $familyBilling): array
    {
        return collect($this->eligiblePlanTypes($familyBilling))
            ->map(fn (string $planType): string => match ($planType) {
                FamilyPaymentPlan::PLAN_TWO_TIMES => 'Ansuran 2 Kali',
                FamilyPaymentPlan::PLAN_THREE_TIMES => 'Ansuran 3 Kali',
                default => 'Bayaran Penuh',
            })
            ->values()
            ->all();
    }

    private function matchesVisibility(string $visibility, ?string $requiredSocialTag, Collection $familySocialTags): bool
    {
        if ($visibility === PaymentCampaignSetting::VISIBILITY_ALL) {
            return true;
        }

        if ($visibility !== PaymentCampaignSetting::VISIBILITY_SOCIAL_TAG) {
            return false;
        }

        $required = $this->normalizeTag((string) $requiredSocialTag);

        return $required !== ''
            && $familySocialTags->contains(
                fn (string $familyTag): bool => $this->normalizeTag($familyTag) === $required
            );
    }

    private function normalizeTag(string $tag): string
    {
        $value = trim($tag);

        return $value === '' ? '' : mb_strtoupper($value);
    }
}
