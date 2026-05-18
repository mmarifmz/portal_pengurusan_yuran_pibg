<?php

namespace App\Http\Controllers;

use App\Services\ClassTeacherWhatsAppReportService;
use App\Services\WhatsAppMessageQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class TeacherClassProgressController extends Controller
{
    private const SINGLE_PREVIEW_SESSION_KEY = 'class_progress_whatsapp_preview_tokens';
    private const BATCH_PREVIEW_SESSION_KEY = 'class_progress_whatsapp_batch_tokens';

    public function __construct(
        private readonly ClassTeacherWhatsAppReportService $classTeacherWhatsAppReportService,
        private readonly WhatsAppMessageQueueService $whatsAppMessageQueueService
    ) {
    }

    public function index(Request $request): View
    {
        $billingYear = (int) now()->year;
        $leaderboardRows = $this->classTeacherWhatsAppReportService->leaderboardRowsWithWhatsappMeta($billingYear);

        $yearLevelOptions = $leaderboardRows
            ->pluck('year_level')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('teacher.class-progress', [
            'billingYear' => $billingYear,
            'leaderboardRows' => $leaderboardRows,
            'yearLevelOptions' => $yearLevelOptions,
            'queueDashboard' => $this->classTeacherWhatsAppReportService->queueDashboard(),
            'queueDashboardUrl' => route('admin.whatsapp-queue.index'),
        ]);
    }

    public function whatsappPreview(Request $request, string $class): JsonResponse
    {
        $billingYear = (int) $request->integer('billing_year', (int) now()->year);
        $className = trim(urldecode($class));
        try {
            $preview = $this->classTeacherWhatsAppReportService->previewForClass($billingYear, $className);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        }
        $previewToken = (string) Str::uuid();

        $tokens = $request->session()->get(self::SINGLE_PREVIEW_SESSION_KEY, []);
        $tokens[$previewToken] = [
            'class_name' => $className,
            'billing_year' => $billingYear,
            'generated_at' => now()->toIso8601String(),
        ];
        $request->session()->put(self::SINGLE_PREVIEW_SESSION_KEY, $tokens);

        return response()->json([
            ...$preview,
            'preview_token' => $previewToken,
        ]);
    }

    public function queueWhatsapp(Request $request, string $class): JsonResponse
    {
        $validated = $request->validate([
            'billing_year' => ['required', 'integer'],
            'preview_token' => ['required', 'string'],
            'force_duplicate' => ['nullable', 'boolean'],
        ]);

        $className = trim(urldecode($class));
        $previewReference = $this->resolveSinglePreviewReference($request, (string) $validated['preview_token'], $className, (int) $validated['billing_year']);
        $preview = $this->classTeacherWhatsAppReportService->previewForClass((int) $previewReference['billing_year'], (string) $previewReference['class_name']);

        if (! (bool) $preview['queue_eligibility']['ready']) {
            return response()->json([
                'message' => 'This class report cannot be queued yet.',
                'errors' => $preview['queue_eligibility']['errors'],
            ], 422);
        }

        if ($this->classTeacherWhatsAppReportService->hasRecentDuplicate($className, (int) $validated['billing_year'])
            && ! (bool) ($validated['force_duplicate'] ?? false)) {
            return response()->json([
                'message' => 'A report for this class was queued recently. Are you sure you want to queue again?',
                'requires_confirmation' => true,
            ], 409);
        }

        $queued = $this->classTeacherWhatsAppReportService->queueBatchPreviews([$preview], $request->user());

        return response()->json([
            'message' => "WhatsApp report queued for {$className}.",
            'queued' => $queued,
            'button_label' => 'Queued',
        ]);
    }

    public function batchWhatsappPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'billing_year' => ['nullable', 'integer'],
            'class_names' => ['nullable', 'array'],
            'class_names.*' => ['string'],
        ]);

        $billingYear = (int) ($validated['billing_year'] ?? now()->year);
        $classNames = collect($validated['class_names'] ?? [])
            ->map(fn ($className): string => trim((string) $className))
            ->filter()
            ->values()
            ->all();

        $preview = $this->classTeacherWhatsAppReportService->batchPreview($billingYear, $classNames === [] ? null : $classNames);
        $previewToken = (string) Str::uuid();

        $tokens = $request->session()->get(self::BATCH_PREVIEW_SESSION_KEY, []);
        $tokens[$previewToken] = [
            'billing_year' => $billingYear,
            'class_names' => collect($preview['class_previews'])->pluck('class_name')->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];
        $request->session()->put(self::BATCH_PREVIEW_SESSION_KEY, $tokens);

        return response()->json([
            ...$preview,
            'preview_token' => $previewToken,
        ]);
    }

    public function batchQueueWhatsapp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'billing_year' => ['required', 'integer'],
            'preview_token' => ['required', 'string'],
            'class_names' => ['required', 'array', 'min:1'],
            'class_names.*' => ['string'],
            'force_duplicate' => ['nullable', 'boolean'],
        ]);

        $reference = $this->resolveBatchPreviewReference($request, (string) $validated['preview_token'], (int) $validated['billing_year']);
        $selectedClasses = collect($validated['class_names'])
            ->map(fn ($className): string => trim((string) $className))
            ->filter()
            ->values();

        $allowedClasses = collect($reference['class_names'] ?? [])->map(fn ($className): string => trim((string) $className));
        if ($selectedClasses->diff($allowedClasses)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'class_names' => 'One or more selected classes are not part of the latest preview.',
            ]);
        }

        $previews = $selectedClasses
            ->map(fn (string $className) => $this->classTeacherWhatsAppReportService->previewForClass((int) $reference['billing_year'], $className))
            ->values();

        $recentlyQueued = $previews
            ->filter(fn (array $preview): bool => (bool) $preview['queue_eligibility']['recently_queued'])
            ->pluck('class_name')
            ->values();

        if ($recentlyQueued->isNotEmpty() && ! (bool) ($validated['force_duplicate'] ?? false)) {
            return response()->json([
                'message' => 'Some selected classes were queued recently. Please confirm before queueing again.',
                'requires_confirmation' => true,
                'classes' => $recentlyQueued->all(),
            ], 409);
        }

        $queued = $this->classTeacherWhatsAppReportService->queueBatchPreviews($previews->all(), $request->user());

        return response()->json([
            'message' => "Queued WhatsApp blast for {$queued['classes_queued']} classes.",
            'queued' => $queued,
        ]);
    }

    public function whatsappQueueIndex(): View
    {
        return view('system.whatsapp-queue', [
            'queueDashboard' => $this->classTeacherWhatsAppReportService->queueDashboard(),
            'messages' => $this->whatsAppMessageQueueService->recentMessages(),
        ]);
    }

    private function resolveSinglePreviewReference(Request $request, string $token, string $className, int $billingYear): array
    {
        $tokens = $request->session()->get(self::SINGLE_PREVIEW_SESSION_KEY, []);
        $reference = $tokens[$token] ?? null;

        if (! is_array($reference)
            || (string) ($reference['class_name'] ?? '') !== $className
            || (int) ($reference['billing_year'] ?? 0) !== $billingYear) {
            throw ValidationException::withMessages([
                'preview_token' => 'The WhatsApp preview has expired. Please preview the class report again before queueing.',
            ]);
        }

        return $reference;
    }

    private function resolveBatchPreviewReference(Request $request, string $token, int $billingYear): array
    {
        $tokens = $request->session()->get(self::BATCH_PREVIEW_SESSION_KEY, []);
        $reference = $tokens[$token] ?? null;

        if (! is_array($reference) || (int) ($reference['billing_year'] ?? 0) !== $billingYear) {
            throw ValidationException::withMessages([
                'preview_token' => 'The batch WhatsApp preview has expired. Please preview the batch again before queueing.',
            ]);
        }

        return $reference;
    }
}
