<?php

namespace App\Services;

use App\Models\JogathonAudit;
use App\Models\JogathonCampaign;
use App\Models\JogathonParticipant;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JogathonParticipantProvisioningService
{
    /** @return array{eligible: int, created: int, refreshed: int, withdrawn: int} */
    public function provision(JogathonCampaign $campaign, ?User $actor = null): array
    {
        return DB::transaction(function () use ($campaign, $actor): array {
            JogathonCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            $activeStudents = Student::query()
                ->active()
                ->orderBy('id')
                ->get(['id', 'full_name', 'class_name']);

            $created = 0;
            $refreshed = 0;

            foreach ($activeStudents as $student) {
                $participant = JogathonParticipant::query()->firstOrNew([
                    'campaign_id' => $campaign->id,
                    'student_id' => $student->id,
                ]);

                if (! $participant->exists) {
                    $participant->fill([
                        'public_slug' => $this->generateUniqueSlug(),
                        'public_display_name' => $this->generatePublicDisplayName(),
                        'target_amount_sen' => (int) $campaign->default_target_amount_sen,
                        'target_distance_cm' => (int) $campaign->default_target_distance_cm,
                        'is_published' => false,
                        'participation_opt_out' => false,
                        'enrolled_at' => now(),
                    ]);
                    $created++;
                } else {
                    $refreshed++;
                }

                $participant->fill([
                    'class_name_snapshot' => $this->normalizeClassName($student->class_name),
                    'is_eligible' => true,
                    'withdrawn_at' => null,
                ])->save();
            }

            $withdrawn = JogathonParticipant::query()
                ->where('campaign_id', $campaign->id)
                ->where('is_eligible', true)
                ->whereNotIn('student_id', $activeStudents->pluck('id')->all())
                ->update([
                    'is_eligible' => false,
                    'is_published' => false,
                    'withdrawn_at' => now(),
                    'updated_at' => now(),
                ]);

            JogathonAudit::query()->create([
                'campaign_id' => $campaign->id,
                'auditable_type' => JogathonCampaign::class,
                'auditable_id' => $campaign->id,
                'action' => 'participants.provisioned',
                'after_values' => [
                    'eligible' => $activeStudents->count(),
                    'created' => $created,
                    'refreshed' => $refreshed,
                    'withdrawn' => $withdrawn,
                ],
                'actor_user_id' => $actor?->id,
            ]);

            return [
                'eligible' => $activeStudents->count(),
                'created' => $created,
                'refreshed' => $refreshed,
                'withdrawn' => $withdrawn,
            ];
        }, 3);
    }

    /** @return array{eligible: int, existing: int, would_create: int} */
    public function preview(JogathonCampaign $campaign): array
    {
        $eligible = Student::query()->active()->count();
        $existing = JogathonParticipant::query()->where('campaign_id', $campaign->id)->count();

        return [
            'eligible' => $eligible,
            'existing' => $existing,
            'would_create' => max(0, $eligible - $existing),
        ];
    }

    public function generateUniqueSlug(?int $ignoreParticipantId = null): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $slug = 'pelari-'.Str::lower(Str::random(10));

            if (! $this->slugExists($slug, $ignoreParticipantId)) {
                return $slug;
            }
        }

        $slug = 'pelari-'.Str::lower(Str::ulid()->toBase32());

        if (! $this->slugExists($slug, $ignoreParticipantId)) {
            return $slug;
        }

        return 'pelari-'.Str::lower(Str::ulid()->toBase32()).'-'.Str::lower(Str::random(4));
    }

    public function generatePublicDisplayName(?string $className = null, int $sequence = 0): string
    {
        $normalizedClass = $this->normalizeClassName($className);

        if ($normalizedClass !== null && $sequence > 0) {
            return sprintf('Pelari %s %03d', $normalizedClass, $sequence);
        }

        return 'Pelari '.Str::upper(Str::random(6));
    }

    public function slugMayRevealStudentName(JogathonParticipant $participant): bool
    {
        $studentName = (string) ($participant->student?->full_name ?? '');
        $nameSlug = Str::slug($studentName);

        if ($nameSlug === '') {
            return false;
        }

        $publicSlug = (string) $participant->public_slug;

        if (str_contains($publicSlug, $nameSlug)) {
            return true;
        }

        $nameTokens = collect(explode('-', $nameSlug))
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => mb_strlen($token) >= 4)
            ->unique()
            ->values();

        return $nameTokens->count() >= 2
            && $nameTokens->filter(fn (string $token): bool => str_contains($publicSlug, $token))->count() >= 2;
    }

    /**
     * @param  Collection<int, JogathonParticipant>  $participants
     * @return array{published: int, slug_rotated: int, aliases_reset: int}
     */
    public function publishSafely(Collection $participants): array
    {
        $classSequences = [];
        $published = 0;
        $slugRotated = 0;
        $aliasesReset = 0;

        foreach ($participants as $participant) {
            $classKey = mb_strtoupper((string) ($participant->class_name_snapshot ?: 'UMUM'));
            $classSequences[$classKey] = ($classSequences[$classKey] ?? 0) + 1;
            $studentName = trim((string) ($participant->student?->full_name ?? ''));
            $displayName = trim((string) $participant->public_display_name);
            $updates = [
                'is_published' => true,
            ];

            if (($studentName !== '' && strcasecmp($displayName, $studentName) === 0)
                || preg_match('/^Pelari [A-Z0-9]{6}$/', $displayName) === 1
            ) {
                $updates['public_display_name'] = $this->generatePublicDisplayName(
                    $participant->class_name_snapshot,
                    $classSequences[$classKey],
                );
                $aliasesReset++;
            }

            if ($this->slugMayRevealStudentName($participant)) {
                $updates['public_slug'] = $this->generateUniqueSlug($participant->id);
                $slugRotated++;
            }

            $participant->update($updates);
            $published++;
        }

        return [
            'published' => $published,
            'slug_rotated' => $slugRotated,
            'aliases_reset' => $aliasesReset,
        ];
    }

    private function normalizeClassName(?string $className): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $className));

        return $value !== '' ? $value : null;
    }

    private function slugExists(string $slug, ?int $ignoreParticipantId = null): bool
    {
        return JogathonParticipant::query()
            ->where('public_slug', $slug)
            ->when($ignoreParticipantId, fn ($query) => $query->whereKeyNot($ignoreParticipantId))
            ->exists();
    }
}
