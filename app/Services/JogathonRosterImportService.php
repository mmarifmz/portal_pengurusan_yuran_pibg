<?php

namespace App\Services;

use App\Models\JogathonClassTeacher;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class JogathonRosterImportService
{
    /**
     * @param  array<int, string>  $classNames
     * @param  array<int, string>  $keywords
     * @param  array<string, string>  $teacherNamesByClass
     * @return array{classes: int, requests: int, imported: int, updated: int, teachers: int}
     */
    public function import(array $classNames, array $keywords, array $teacherNamesByClass, string $apiKey, int $year, string $endpoint): array
    {
        $classNames = $this->normaliseList($classNames);
        $keywords = $this->normaliseList($keywords);

        if ($classNames === [] || $keywords === []) {
            throw new RuntimeException('Sila masukkan sekurang-kurangnya satu kelas dan satu kata carian.');
        }

        $stats = [
            'classes' => count($classNames),
            'requests' => 0,
            'imported' => 0,
            'updated' => 0,
            'teachers' => 0,
        ];

        foreach ($classNames as $className) {
            $teacherName = $this->teacherNameForClass($className, $teacherNamesByClass);

            if ($teacherName !== null) {
                JogathonClassTeacher::query()->updateOrCreate(
                    ['class_name' => $className],
                    [
                        'teacher_name' => $teacherName,
                        'source' => isset($teacherNamesByClass[$this->classKey($className)]) ? 'manual_import' : 'local_user_match',
                        'synced_at' => now(),
                    ],
                );
                $stats['teachers']++;
            }

            foreach ($keywords as $keyword) {
                $payload = $this->fetch($endpoint, $apiKey, $keyword, $year, $className);
                $stats['requests']++;

                foreach ($this->studentsFromPayload($payload, $className) as $studentRow) {
                    $result = $this->upsertStudent($studentRow['name'], $studentRow['class'], $year);
                    $stats[$result]++;
                }
            }
        }

        return $stats;
    }

    /**
     * @return array<string, string>
     */
    public function parseTeacherMappings(?string $rawMappings): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $rawMappings) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->mapWithKeys(function (string $line): array {
                [$className, $teacherName] = array_pad(preg_split('/=|,/', $line, 2) ?: [], 2, '');

                $className = $this->normaliseClassName($className);
                $teacherName = $this->normaliseName($teacherName);

                return $className !== '' && $teacherName !== ''
                    ? [$this->classKey($className) => $teacherName]
                    : [];
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $items
     * @return array<int, string>
     */
    private function normaliseList(array $items): array
    {
        return collect($items)
            ->flatMap(fn (string $item): array => preg_split('/\r\n|\r|\n|,/', $item) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique(fn (string $item): string => mb_strtoupper($item))
            ->values()
            ->all();
    }

    private function fetch(string $endpoint, string $apiKey, string $keyword, int $year, string $className): array
    {
        $response = Http::acceptJson()
            ->withToken($apiKey)
            ->timeout(20)
            ->retry(2, 500)
            ->get($endpoint, [
                'q' => $keyword,
                'year' => $year,
                'class' => $className,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'API roster gagal untuk kelas %s / kata carian %s. Status: %d.',
                $className,
                $keyword,
                $response->status(),
            ));
        }

        return $response->json();
    }

    /**
     * @return Collection<int, array{name: string, class: string}>
     */
    private function studentsFromPayload(array $payload, string $expectedClass): Collection
    {
        return collect($payload['data'] ?? [])
            ->flatMap(fn (array $family): array => is_array($family['students'] ?? null) ? $family['students'] : [])
            ->map(function (array $student): ?array {
                $name = $this->normaliseName((string) ($student['name'] ?? ''));
                $className = $this->normaliseClassName((string) ($student['class'] ?? ''));

                if ($name === '' || $className === '') {
                    return null;
                }

                return ['name' => $name, 'class' => $className];
            })
            ->filter()
            ->filter(fn (array $student): bool => $this->classKey($student['class']) === $this->classKey($expectedClass))
            ->unique(fn (array $student): string => $this->classKey($student['class']).'|'.$this->normaliseName($student['name']))
            ->values();
    }

    private function upsertStudent(string $name, string $className, int $year): string
    {
        $studentNo = 'JOG-'.Str::upper(substr(hash('sha256', $year.'|'.$className.'|'.$name), 0, 16));

        $existingStudent = Student::query()
            ->where('full_name', $name)
            ->where('class_name', $className)
            ->orderBy('id')
            ->first();

        if ($existingStudent !== null) {
            $existingStudent->forceFill([
                'status' => Student::STATUS_ACTIVE,
                'billing_year' => $year,
                'family_code' => null,
                'parent_name' => null,
                'parent_phone' => null,
                'parent_email' => null,
                'total_fee' => 0,
                'paid_amount' => 0,
                'annual_fee' => 0,
            ])->save();

            return 'updated';
        }

        $student = Student::query()->updateOrCreate(
            ['student_no' => $studentNo],
            [
                'full_name' => $name,
                'class_name' => $className,
                'status' => Student::STATUS_ACTIVE,
                'billing_year' => $year,
                'family_code' => null,
                'parent_name' => null,
                'parent_phone' => null,
                'parent_email' => null,
                'total_fee' => 0,
                'paid_amount' => 0,
                'annual_fee' => 0,
            ],
        );

        return $student->wasRecentlyCreated ? 'imported' : 'updated';
    }

    private function teacherNameForClass(string $className, array $teacherNamesByClass): ?string
    {
        $key = $this->classKey($className);

        if (isset($teacherNamesByClass[$key])) {
            return $teacherNamesByClass[$key];
        }

        $teacherName = User::query()
            ->withAnyRole(['teacher', 'super_teacher'])
            ->where('class_name', $className)
            ->orderBy('name')
            ->value('name');

        return filled($teacherName) ? $this->normaliseName((string) $teacherName) : null;
    }

    private function normaliseName(string $name): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $name) ?: ''));
    }

    private function normaliseClassName(string $className): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $className) ?: ''));
    }

    private function classKey(string $className): string
    {
        return $this->normaliseClassName($className);
    }
}
