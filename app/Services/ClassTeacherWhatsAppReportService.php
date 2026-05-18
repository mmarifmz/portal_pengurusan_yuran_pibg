<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppMessageQueue;
use App\Support\MalaysianPhone;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ClassTeacherWhatsAppReportService
{
    private const SAFE_MESSAGE_LENGTH = 1500;

    public function __construct(
        private readonly PaymentReportingService $paymentReportingService,
        private readonly WhatsAppMessageQueueService $whatsAppMessageQueueService
    ) {
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboardRowsWithWhatsappMeta(int $billingYear): Collection
    {
        $dataset = $this->buildDataset($billingYear);

        return $dataset['leaderboard_rows']
            ->map(fn (array $row) => $this->augmentLeaderboardRow($row, $dataset))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function previewForClass(int $billingYear, string $className): array
    {
        $dataset = $this->buildDataset($billingYear);

        return $this->buildClassPreviewFromDataset($dataset, $className);
    }

    /**
     * @param  array<int, string>|null  $selectedClasses
     * @return array<string, mixed>
     */
    public function batchPreview(int $billingYear, ?array $selectedClasses = null): array
    {
        $dataset = $this->buildDataset($billingYear);
        $classNames = collect($selectedClasses ?: $dataset['leaderboard_rows']->pluck('class_name')->all())
            ->map(fn ($className): string => trim((string) $className))
            ->filter()
            ->unique()
            ->values();

        $previews = $classNames
            ->map(fn (string $className) => $this->buildClassPreviewFromDataset($dataset, $className))
            ->values();

        return [
            'billing_year' => $billingYear,
            'preview_generated_at' => now()->toIso8601String(),
            'queue_dashboard' => $dataset['queue_dashboard'],
            'queue_page_url' => route('admin.whatsapp-queue.index'),
            'total_classes' => $dataset['leaderboard_rows']->count(),
            'classes_with_assigned_teachers' => $previews->where('teacher_exists', true)->count(),
            'classes_missing_teachers' => $previews->where('teacher_exists', false)->count(),
            'teachers_with_whatsapp_number' => $previews->filter(fn (array $preview): bool => filled($preview['teacher_phone']))->count(),
            'teachers_missing_whatsapp_number' => $previews->filter(fn (array $preview): bool => blank($preview['teacher_phone']))->count(),
            'estimated_total_queued_messages' => $previews
                ->filter(fn (array $preview): bool => (bool) $preview['queue_eligibility']['ready'])
                ->sum(fn (array $preview): int => count($preview['generated_messages'])),
            'class_previews' => $previews->map(function (array $preview): array {
                return [
                    'class_name' => $preview['class_name'],
                    'teacher_name' => $preview['teacher_name'],
                    'teacher_phone' => $preview['teacher_phone'],
                    'payment_percentage' => $preview['class_stats']['payment_percentage'],
                    'total_collected' => $preview['class_stats']['total_collected'],
                    'status' => $preview['queue_eligibility']['status'],
                    'status_label' => $preview['queue_eligibility']['status_label'],
                    'recently_queued' => $preview['queue_eligibility']['recently_queued'],
                    'estimated_messages' => count($preview['generated_messages']),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queueBatchPreviews(array $previews, User $queuedBy): array
    {
        $messages = [];
        $queuedClasses = 0;
        $skippedClasses = 0;
        $batchId = (string) Str::uuid();

        foreach ($previews as $preview) {
            $eligible = (bool) ($preview['queue_eligibility']['ready'] ?? false);

            if (! $eligible) {
                $skippedClasses++;

                continue;
            }

            $queuedClasses++;

            foreach ($preview['generated_messages'] as $message) {
                $messages[] = [
                    'billing_year' => (int) $preview['billing_year'],
                    'class_name' => (string) $preview['class_name'],
                    'teacher_user_id' => (int) $preview['teacher_id'],
                    'recipient_name' => (string) $preview['teacher_name'],
                    'recipient_phone' => (string) $preview['teacher_phone'],
                    'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
                    'message_part' => (string) $message['message_part'],
                    'message_segment' => (int) $message['segment'],
                    'segment_count' => (int) $message['segment_count'],
                    'total_parts' => count($preview['generated_messages']),
                    'message_body' => (string) $message['body'],
                ];
            }
        }

        $queued = $this->whatsAppMessageQueueService->queueMessages($messages, $queuedBy, [
            'batch_id' => $batchId,
            'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
            'source' => 'class_progress_batch',
            'total_classes_selected' => count($previews),
            'total_classes_queued' => $queuedClasses,
            'total_skipped' => $skippedClasses,
            'meta' => [
                'class_names' => collect($previews)->pluck('class_name')->values()->all(),
            ],
        ]);

        return [
            'batch_id' => $queued['batch_id'],
            'classes_queued' => $queuedClasses,
            'classes_skipped' => $skippedClasses,
            'messages_queued' => $queued['messages_queued'],
        ];
    }

    public function hasRecentDuplicate(string $className, int $billingYear, int $withinMinutes = 15): bool
    {
        return $this->whatsAppMessageQueueService->hasRecentDuplicate($className, $billingYear, $withinMinutes);
    }

    /**
     * @return array<string, mixed>
     */
    public function queueDashboard(): array
    {
        return $this->whatsAppMessageQueueService->dashboardSnapshot();
    }

    /**
     * @return array{
     *   leaderboard_rows: Collection<int, array<string, mixed>>,
     *   leaderboard_lookup: Collection<string, array<string, mixed>>,
     *   family_metrics: Collection<string, array<string, mixed>>,
     *   students_by_class: Collection<string, Collection<int, Student>>,
     *   teachers_by_class: Collection<string, User>,
     *   queue_dashboard: array<string, mixed>
     * }
     */
    private function buildDataset(int $billingYear): array
    {
        $leaderboardRows = $this->paymentReportingService->classLeaderboard($billingYear)
            ->map(fn (array $row, int $index): array => [
                ...$row,
                'rank' => $index + 1,
            ])
            ->values();

        $classNames = $leaderboardRows
            ->pluck('class_name')
            ->map(fn ($className): string => trim((string) $className))
            ->filter()
            ->values();

        $teachersByClass = User::query()
            ->whereIn('role', ['teacher', 'super_teacher'])
            ->whereIn('class_name', $classNames->all())
            ->orderByRaw("case when role = 'teacher' then 0 else 1 end")
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'class_name', 'role', 'is_active'])
            ->groupBy(fn (User $teacher): string => trim((string) $teacher->class_name))
            ->map(fn (Collection $rows): ?User => $rows->first())
            ->filter();

        $studentsByClass = Student::query()
            ->where('billing_year', $billingYear)
            ->whereIn('class_name', $classNames->all())
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get(['id', 'student_no', 'family_code', 'full_name', 'class_name', 'billing_year'])
            ->groupBy(fn (Student $student): string => trim((string) $student->class_name));

        return [
            'leaderboard_rows' => $leaderboardRows,
            'leaderboard_lookup' => $leaderboardRows->keyBy('class_name'),
            'family_metrics' => $this->paymentReportingService->familyMetricsForYear($billingYear)->keyBy('family_code'),
            'students_by_class' => $studentsByClass,
            'teachers_by_class' => $teachersByClass,
            'queue_dashboard' => $this->queueDashboard(),
        ];
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    private function buildClassPreviewFromDataset(array $dataset, string $className): array
    {
        $className = trim($className);
        $row = $dataset['leaderboard_lookup']->get($className);

        if (! is_array($row)) {
            throw new InvalidArgumentException('The selected class could not be found.');
        }

        /** @var User|null $teacher */
        $teacher = $dataset['teachers_by_class']->get($className);
        /** @var Collection<int, Student> $students */
        $students = $dataset['students_by_class']->get($className, collect());
        /** @var Collection<string, array<string, mixed>> $familyMetrics */
        $familyMetrics = $dataset['family_metrics'];

        $paidStudents = $students
            ->map(function (Student $student) use ($familyMetrics): array {
                $metric = $familyMetrics->get((string) $student->family_code, []);

                return [
                    'student_name' => (string) $student->full_name,
                    'family_code' => (string) $student->family_code,
                    'amount_paid' => (float) ($metric['total_collection'] ?? 0),
                    'is_fully_paid' => (bool) ($metric['is_fully_paid'] ?? false),
                ];
            })
            ->filter(fn (array $student): bool => $student['is_fully_paid'])
            ->values();

        $unpaidStudents = $students
            ->map(function (Student $student) use ($familyMetrics): array {
                $metric = $familyMetrics->get((string) $student->family_code, []);

                return [
                    'student_name' => (string) $student->full_name,
                    'family_code' => (string) $student->family_code,
                    'is_fully_paid' => (bool) ($metric['is_fully_paid'] ?? false),
                ];
            })
            ->filter(fn (array $student): bool => ! $student['is_fully_paid'])
            ->values();

        $teacherPhone = $teacher?->phone ? (string) $teacher->phone : '';
        $normalizedTeacherPhone = $teacherPhone !== '' ? MalaysianPhone::normalize($teacherPhone) : null;
        $recentlyQueued = $this->hasRecentDuplicate($className, (int) $row['billing_year']);

        $errors = [];
        if (! $teacher) {
            $errors[] = 'Tiada guru kelas ditugaskan untuk kelas ini.';
        }

        if ($teacher && $normalizedTeacherPhone === null) {
            $errors[] = 'Guru kelas belum mempunyai nombor WhatsApp yang sah.';
        }

        $messages = $this->buildMessages(
            className: $className,
            teacherName: $teacher?->name ? (string) $teacher->name : 'Guru Kelas',
            totalStudents: $students->count(),
            paidCount: $paidStudents->count(),
            unpaidCount: $unpaidStudents->count(),
            paymentPercentage: (float) $row['completion_percent'],
            pibgAmount: (float) $row['yuran_collected'],
            additionalDonation: (float) $row['sumbangan_tambahan_collected'],
            totalCollected: (float) $row['jumlah_kutipan'],
            expectedAmount: (float) ((float) $row['yuran_collected'] + (float) $row['baki_tertunggak']),
            rank: (int) $row['rank'],
            paidStudents: $paidStudents,
            unpaidStudents: $unpaidStudents,
        );

        $status = 'ready';
        $statusLabel = 'Ready';
        if (! $teacher) {
            $status = 'missing_teacher';
            $statusLabel = 'Missing Teacher';
        } elseif ($normalizedTeacherPhone === null) {
            $status = 'missing_phone';
            $statusLabel = 'Missing Phone';
        } elseif ($recentlyQueued) {
            $status = 'recently_queued';
            $statusLabel = 'Recently Queued';
        }

        return [
            'billing_year' => (int) $row['billing_year'],
            'class_name' => $className,
            'teacher_exists' => $teacher !== null,
            'teacher_id' => $teacher?->id,
            'teacher_name' => $teacher?->name ? (string) $teacher->name : '-',
            'teacher_phone' => $normalizedTeacherPhone ?? '',
            'class_stats' => [
                'rank' => (int) $row['rank'],
                'total_students' => $students->count(),
                'paid_count' => $paidStudents->count(),
                'unpaid_count' => $unpaidStudents->count(),
                'payment_percentage' => round((float) $row['completion_percent'], 2),
                'pibg_amount' => round((float) $row['yuran_collected'], 2),
                'additional_donation' => round((float) $row['sumbangan_tambahan_collected'], 2),
                'total_collected' => round((float) $row['jumlah_kutipan'], 2),
                'expected_amount' => round((float) ((float) $row['yuran_collected'] + (float) $row['baki_tertunggak']), 2),
                'current_ranking' => (int) $row['rank'],
            ],
            'paid_students' => $paidStudents->values()->all(),
            'unpaid_students' => $unpaidStudents->values()->all(),
            'generated_messages' => $messages,
            'queue_eligibility' => [
                'ready' => $errors === [],
                'errors' => $errors,
                'recently_queued' => $recentlyQueued,
                'duplicate_warning' => $recentlyQueued ? 'A report for this class was queued recently. Are you sure you want to queue again?' : null,
                'status' => $status,
                'status_label' => $statusLabel,
            ],
            'queue_dashboard' => $dataset['queue_dashboard'],
            'queue_page_url' => route('admin.whatsapp-queue.index'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    private function augmentLeaderboardRow(array $row, array $dataset): array
    {
        /** @var User|null $teacher */
        $teacher = $dataset['teachers_by_class']->get((string) $row['class_name']);
        $normalizedPhone = $teacher?->phone ? MalaysianPhone::normalize((string) $teacher->phone) : null;
        $recentlyQueued = $this->hasRecentDuplicate((string) $row['class_name'], (int) $row['billing_year']);

        $badges = [];

        if ($teacher === null) {
            $badges[] = ['label' => 'Missing Teacher', 'classes' => 'border-rose-200 bg-rose-50 text-rose-700'];
        } elseif ($normalizedPhone === null) {
            $badges[] = ['label' => 'Missing Phone', 'classes' => 'border-amber-200 bg-amber-50 text-amber-700'];
        } else {
            $badges[] = ['label' => 'Ready', 'classes' => 'border-emerald-200 bg-emerald-50 text-emerald-700'];
        }

        if ($recentlyQueued) {
            $badges[] = ['label' => 'Recently Queued', 'classes' => 'border-sky-200 bg-sky-50 text-sky-700'];
        }

        return [
            ...$row,
            'year_level' => $this->extractYearLevel((string) $row['class_name']),
            'teacher_id' => $teacher?->id,
            'teacher_name' => $teacher?->name ? (string) $teacher->name : '-',
            'teacher_phone' => $normalizedPhone ?? '',
            'whatsapp_ready' => $teacher !== null && $normalizedPhone !== null,
            'recently_queued' => $recentlyQueued,
            'status_badges' => $badges,
        ];
    }

    private function extractYearLevel(string $className): ?int
    {
        if (preg_match('/^(\d+)/', trim($className), $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);

        return ($year >= 1 && $year <= 6) ? $year : null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $paidStudents
     * @param  Collection<int, array<string, mixed>>  $unpaidStudents
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(
        string $className,
        string $teacherName,
        int $totalStudents,
        int $paidCount,
        int $unpaidCount,
        float $paymentPercentage,
        float $pibgAmount,
        float $additionalDonation,
        float $totalCollected,
        float $expectedAmount,
        int $rank,
        Collection $paidStudents,
        Collection $unpaidStudents
    ): array {
        $summaryLines = [
            "Assalamualaikum / Salam Sejahtera {$teacherName},",
            '',
            "Ringkasan kutipan Yuran PIBG bagi kelas {$className}:",
            '',
            "Jumlah murid: {$totalStudents}",
            "Telah bayar: {$paidCount}",
            "Belum bayar: {$unpaidCount}",
            'Peratus bayaran: '.number_format($paymentPercentage, 2).'%',
            '',
            'Yuran PIBG terkumpul: RM '.number_format($pibgAmount, 2),
            'Sumbangan tambahan: RM '.number_format($additionalDonation, 2),
            'Jumlah kutipan: RM '.number_format($totalCollected, 2),
            'Sasaran kutipan: RM '.number_format($expectedAmount, 2),
            "Ranking semasa: #{$rank}",
            '',
            'Terima kasih atas bantuan cikgu untuk mengingatkan ibu bapa yang masih belum membuat bayaran.',
        ];

        $paidLines = $paidStudents->isEmpty()
            ? ['Tiada rekod bayaran setakat ini.']
            : $paidStudents
                ->values()
                ->map(fn (array $student, int $index): string => sprintf(
                    '%d. %s - RM %s',
                    $index + 1,
                    $student['student_name'],
                    number_format((float) $student['amount_paid'], 2)
                ))
                ->all();

        $unpaidLines = $unpaidStudents->isEmpty()
            ? ['Semua murid telah membuat bayaran. Terima kasih cikgu.']
            : $unpaidStudents
                ->values()
                ->map(fn (array $student, int $index): string => sprintf(
                    '%d. %s',
                    $index + 1,
                    $student['student_name']
                ))
                ->all();

        return array_merge(
            $this->chunkMessage('summary', 'Ringkasan Kutipan', $summaryLines),
            $this->chunkMessage('paid_list', '✅ Senarai Telah Bayar', $paidLines),
            $this->chunkMessage('unpaid_list', '⏳ Senarai Belum Bayar', $unpaidLines),
        );
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function chunkMessage(string $messagePart, string $heading, array $lines): array
    {
        $segments = [];
        $current = [$heading, ''];

        foreach ($lines as $line) {
            $candidate = implode("\n", [...$current, $line]);

            if (mb_strlen($candidate) > self::SAFE_MESSAGE_LENGTH && count($current) > 2) {
                $segments[] = implode("\n", $current);
                $current = [$heading, '', $line];

                continue;
            }

            $current[] = $line;
        }

        $segments[] = implode("\n", $current);
        $segmentCount = count($segments);

        return collect($segments)
            ->map(function (string $body, int $index) use ($messagePart, $segmentCount, $heading): array {
                if ($segmentCount > 1) {
                    $body = str_replace($heading, sprintf('%s (%d/%d)', $heading, $index + 1, $segmentCount), $body);
                }

                return [
                    'message_part' => $messagePart,
                    'part_label' => $heading,
                    'segment' => $index + 1,
                    'segment_count' => $segmentCount,
                    'body' => $body,
                ];
            })
            ->values()
            ->all();
    }
}
