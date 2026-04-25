<?php

namespace App\Services;

use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Models\User;
use App\Support\ParentPhone;
use Illuminate\Support\Collection;

class ParentProfileSyncFromPaymentsService
{
    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $latestPayerByPhone = $this->buildLatestPayerByPhoneMap();

        if ($latestPayerByPhone->isEmpty()) {
            return [
                'families_matched' => 0,
                'students_updated' => 0,
                'users_updated' => 0,
            ];
        }

        $students = Student::query()
            ->orderBy('id')
            ->get(['id', 'family_code', 'parent_name', 'parent_email', 'parent_phone']);

        $matchedFamilies = collect();
        $studentsUpdated = 0;
        $usersUpdated = 0;

        $studentsByFamily = $students
            ->filter(fn (Student $student): bool => filled($student->family_code))
            ->groupBy(fn (Student $student): string => (string) $student->family_code);

        foreach ($students as $student) {
            if (! $this->isPlaceholderName((string) ($student->parent_name ?? ''))) {
                continue;
            }

            $payer = $this->findPayerForPhone((string) ($student->parent_phone ?? ''), $latestPayerByPhone);
            if ($payer === null) {
                continue;
            }

            $targetStudents = filled($student->family_code)
                ? collect($studentsByFamily->get((string) $student->family_code, []))
                : collect([$student]);

            if ($targetStudents->isEmpty()) {
                continue;
            }

            if (filled($student->family_code)) {
                $matchedFamilies->push((string) $student->family_code);
            }

            foreach ($targetStudents as $targetStudent) {
                $dirty = false;

                if ($this->isPlaceholderName((string) ($targetStudent->parent_name ?? ''))) {
                    $targetStudent->parent_name = $payer['name'];
                    $dirty = true;
                }

                if ($this->isPlaceholderEmail((string) ($targetStudent->parent_email ?? '')) && $payer['email'] !== '') {
                    $targetStudent->parent_email = $payer['email'];
                    $dirty = true;
                }

                if ($dirty) {
                    $targetStudent->save();
                    $studentsUpdated++;
                }
            }

            $usersUpdated += $this->syncParentUsersForStudents($targetStudents, $payer['name'], $payer['email']);
        }

        return [
            'families_matched' => $matchedFamilies->unique()->count(),
            'students_updated' => $studentsUpdated,
            'users_updated' => $usersUpdated,
        ];
    }

    /**
     * @return Collection<string, array{name:string,email:string}>
     */
    private function buildLatestPayerByPhoneMap(): Collection
    {
        $transactions = FamilyPaymentTransaction::query()
            ->whereNotNull('payer_phone')
            ->where('payer_phone', '!=', '')
            ->whereNotNull('payer_name')
            ->where('payer_name', '!=', '')
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['payer_name', 'payer_email', 'payer_phone']);

        $map = collect();

        foreach ($transactions as $transaction) {
            $name = trim((string) ($transaction->payer_name ?? ''));
            if ($name === '' || $this->isPlaceholderName($name)) {
                continue;
            }

            $normalizedPhone = ParentPhone::normalizeForMatch((string) ($transaction->payer_phone ?? ''));
            if ($normalizedPhone === '' || $map->has($normalizedPhone)) {
                continue;
            }

            $email = trim((string) ($transaction->payer_email ?? ''));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }

            $map->put($normalizedPhone, [
                'name' => $name,
                'email' => $email,
            ]);
        }

        return $map;
    }

    /**
     * @param  Collection<string, array{name:string,email:string}>  $payerByPhone
     * @return array{name:string,email:string}|null
     */
    private function findPayerForPhone(string $phone, Collection $payerByPhone): ?array
    {
        $variants = collect(ParentPhone::variants($phone))
            ->map(fn (string $variant): string => ParentPhone::normalizeForMatch($variant))
            ->filter()
            ->unique()
            ->values();

        foreach ($variants as $variant) {
            $payer = $payerByPhone->get($variant);
            if (is_array($payer)) {
                return $payer;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function syncParentUsersForStudents(Collection $students, string $name, string $email): int
    {
        $phoneVariants = $students
            ->pluck('parent_phone')
            ->filter()
            ->flatMap(fn ($phone): array => ParentPhone::variants((string) $phone))
            ->filter()
            ->unique()
            ->values();

        if ($phoneVariants->isEmpty()) {
            return 0;
        }

        $users = User::query()
            ->where('role', 'parent')
            ->whereIn('phone', $phoneVariants->all())
            ->get();

        $updated = 0;

        foreach ($users as $user) {
            $dirty = false;

            if ($this->isPlaceholderName((string) ($user->name ?? ''))) {
                $user->name = $name;
                $dirty = true;
            }

            if ($email !== '' && $this->isPlaceholderEmail((string) ($user->email ?? ''))) {
                $emailExistsOnAnotherUser = User::query()
                    ->where('email', $email)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if (! $emailExistsOnAnotherUser) {
                    $user->email = $email;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $user->save();
                $updated++;
            }
        }

        return $updated;
    }

    private function isPlaceholderName(string $name): bool
    {
        $value = trim($name);

        if ($value === '' || $value === '-') {
            return true;
        }

        return preg_match('/^parent\s+ssp-/i', $value) === 1;
    }

    private function isPlaceholderEmail(string $email): bool
    {
        $value = mb_strtolower(trim($email));

        if ($value === '' || $value === '-') {
            return true;
        }

        return str_contains($value, '@placeholder.local');
    }
}

