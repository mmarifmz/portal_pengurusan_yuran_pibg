<?php

namespace App\Http\Controllers;

use App\Models\FamilyPaymentTransaction;
use App\Models\PaymentCampaignSetting;
use App\Models\QrCampaign;
use App\Models\QrCampaignScan;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Services\QrCodeImageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QrCampaignController extends Controller
{
    public function __construct(
        private readonly QrCodeImageService $qrCodeImageService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $purpose = trim((string) $request->query('purpose', 'all'));

        $campaignQuery = QrCampaign::query()
            ->with('paymentCampaignSetting')
            ->withCount('scans')
            ->withCount([
                'transactions as confirmed_payments_count' => fn ($query) => $query->where('status', 'success'),
            ])
            ->withSum([
                'transactions as confirmed_amount' => fn ($query) => $query->where('status', 'success'),
            ], 'amount')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('poster_title', 'like', "%{$search}%")
                        ->orWhere('class_name', 'like', "%{$search}%")
                        ->orWhere('location_name', 'like', "%{$search}%")
                        ->orWhere('distribution_channel', 'like', "%{$search}%")
                        ->orWhere('short_code', 'like', "%{$search}%");
                });
            })
            ->when(in_array($purpose, $this->purposes(), true), fn ($query) => $query->where('purpose', $purpose))
            ->latest('updated_at')
            ->latest('id');

        $campaigns = $campaignQuery->get();
        $uniqueScanCounts = QrCampaignScan::query()
            ->select('qr_campaign_id')
            ->selectRaw('COUNT(DISTINCT visitor_hash) as aggregate')
            ->groupBy('qr_campaign_id')
            ->pluck('aggregate', 'qr_campaign_id');

        $campaigns->each(function (QrCampaign $campaign) use ($uniqueScanCounts): void {
            $campaign->setAttribute('unique_scans_count', (int) ($uniqueScanCounts[$campaign->id] ?? 0));
        });

        $totalScans = QrCampaignScan::query()->count();
        $uniqueScans = (int) QrCampaignScan::query()->distinct()->count('visitor_hash');
        $confirmedPayments = FamilyPaymentTransaction::query()
            ->whereNotNull('qr_campaign_id')
            ->where('status', 'success')
            ->count();
        $confirmedAmount = (float) FamilyPaymentTransaction::query()
            ->whereNotNull('qr_campaign_id')
            ->where('status', 'success')
            ->sum('amount');

        $trend = $this->buildTrend();
        $editCampaign = $request->integer('edit') > 0
            ? QrCampaign::query()->find($request->integer('edit'))
            : null;

        return view('system.qr-campaigns.index', [
            'campaigns' => $campaigns,
            'editCampaign' => $editCampaign,
            'paymentCampaignSettings' => PaymentCampaignSetting::query()
                ->latest('updated_at')
                ->latest('id')
                ->get(),
            'classOptions' => Student::query()
                ->whereNotNull('class_name')
                ->where('class_name', '!=', '')
                ->distinct()
                ->orderBy('class_name')
                ->pluck('class_name'),
            'search' => $search,
            'purpose' => $purpose,
            'totalScans' => $totalScans,
            'uniqueScans' => $uniqueScans,
            'confirmedPayments' => $confirmedPayments,
            'confirmedAmount' => $confirmedAmount,
            'conversionRate' => $totalScans > 0 ? round(($confirmedPayments / $totalScans) * 100, 1) : 0.0,
            'trend' => $trend,
            'trendMax' => max(1, (int) $trend->max(fn (array $day): int => max($day['scans'], $day['payments']))),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedCampaignData($request);
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;

        $campaign = QrCampaign::query()->create($data);

        return redirect()
            ->route('system.qr-campaigns.index', ['edit' => $campaign->id])
            ->with('status', 'QR kempen berjaya dijana. Pautan pendek dan poster A4 kini sedia digunakan.');
    }

    public function update(Request $request, QrCampaign $qrCampaign): RedirectResponse
    {
        $data = $this->validatedCampaignData($request);
        $data['updated_by'] = $request->user()?->id;
        $qrCampaign->update($data);

        return redirect()
            ->route('system.qr-campaigns.index', ['edit' => $qrCampaign->id])
            ->with('status', 'QR kempen berjaya dikemas kini.');
    }

    public function toggle(QrCampaign $qrCampaign): RedirectResponse
    {
        $qrCampaign->forceFill([
            'is_active' => ! $qrCampaign->is_active,
            'updated_by' => request()->user()?->id,
        ])->save();

        return back()->with('status', $qrCampaign->is_active
            ? 'QR kempen telah diaktifkan.'
            : 'QR kempen telah dinyahaktifkan. Pautan pendek tidak lagi menerima imbasan.');
    }

    public function qrImage(QrCampaign $qrCampaign): Response
    {
        $filename = 'qr-'.$qrCampaign->short_code.'.png';

        return response($this->qrCodeImageService->png($qrCampaign->shortUrl()))
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function posterPdf(QrCampaign $qrCampaign): Response
    {
        $settings = SiteSetting::getMany([
            'seo_og_site_name' => 'Portal Sumbangan PIBG SK Sri Petaling',
        ]);

        return Pdf::loadView('system.qr-campaigns.poster-pdf', [
            'campaign' => $qrCampaign->loadMissing('paymentCampaignSetting'),
            'qrDataUri' => $this->qrCodeImageService->dataUri($qrCampaign->shortUrl()),
            'schoolLogoSource' => SiteSetting::schoolLogoPdfSource(),
            'siteName' => $settings['seo_og_site_name'],
        ])
            ->setPaper('a4', 'portrait')
            ->download('poster-'.str($qrCampaign->name)->slug().'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCampaignData(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in($this->purposes())],
            'destination_type' => ['required', Rule::in($this->destinationTypes())],
            'destination_path' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('destination_type') !== QrCampaign::DESTINATION_CUSTOM_INTERNAL) {
                        return;
                    }

                    $path = trim((string) $value);
                    if (
                        $path === ''
                        || ! str_starts_with($path, '/')
                        || str_starts_with($path, '//')
                        || str_contains($path, '\\')
                        || preg_match('/^\/(?:q|system)(?:\/|$)/i', $path) === 1
                    ) {
                        $fail('Destinasi tersuai mesti laluan dalaman portal yang selamat dan tidak boleh menunjuk ke pautan QR atau ruang sistem.');
                    }
                },
            ],
            'payment_campaign_setting_id' => ['nullable', 'integer', 'exists:payment_campaign_settings,id'],
            'class_name' => ['nullable', 'string', 'max:255'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'distribution_channel' => ['nullable', 'string', 'max:255'],
            'poster_title' => ['required', 'string', 'max:255'],
            'poster_subtitle' => ['nullable', 'string', 'max:255'],
            'call_to_action' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $validated['destination_path'] = $this->destinationPath(
            (string) $validated['destination_type'],
            (string) ($validated['destination_path'] ?? ''),
        );
        $validated['is_active'] = $request->boolean('is_active');

        foreach (['name', 'class_name', 'location_name', 'distribution_channel', 'poster_title', 'poster_subtitle', 'call_to_action'] as $key) {
            if (array_key_exists($key, $validated)) {
                $value = trim((string) $validated[$key]);
                $validated[$key] = $value !== '' ? $value : null;
            }
        }

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function purposes(): array
    {
        return [
            QrCampaign::PURPOSE_PAYMENT,
            QrCampaign::PURPOSE_DONATION,
            QrCampaign::PURPOSE_EVENT,
            QrCampaign::PURPOSE_PROGRAMME,
        ];
    }

    /**
     * @return list<string>
     */
    private function destinationTypes(): array
    {
        return [
            QrCampaign::DESTINATION_PAYMENT_DIRECTORY,
            QrCampaign::DESTINATION_PARENT_LOGIN,
            QrCampaign::DESTINATION_SCHOOL_CALENDAR,
            QrCampaign::DESTINATION_CUSTOM_INTERNAL,
        ];
    }

    private function destinationPath(string $destinationType, string $customPath): string
    {
        return match ($destinationType) {
            QrCampaign::DESTINATION_PAYMENT_DIRECTORY => route('parent.search', absolute: false),
            QrCampaign::DESTINATION_PARENT_LOGIN => route('parent.login.form', absolute: false),
            QrCampaign::DESTINATION_SCHOOL_CALENDAR => route('school-calendar', absolute: false),
            default => trim($customPath),
        };
    }

    /**
     * @return Collection<int, array{date: string, label: string, scans: int, payments: int}>
     */
    private function buildTrend(): Collection
    {
        $start = CarbonImmutable::today()->subDays(13);
        $end = CarbonImmutable::today()->endOfDay();

        $scanCounts = QrCampaignScan::query()
            ->whereBetween('scanned_at', [$start, $end])
            ->get(['scanned_at'])
            ->countBy(fn (QrCampaignScan $scan): string => $scan->scanned_at->format('Y-m-d'));

        $paymentCounts = FamilyPaymentTransaction::query()
            ->whereNotNull('qr_campaign_id')
            ->where('status', 'success')
            ->whereBetween('paid_at', [$start, $end])
            ->get(['paid_at'])
            ->countBy(fn (FamilyPaymentTransaction $transaction): string => $transaction->paid_at->format('Y-m-d'));

        return collect(range(0, 13))->map(function (int $offset) use ($start, $scanCounts, $paymentCounts): array {
            $date = $start->addDays($offset);
            $key = $date->format('Y-m-d');

            return [
                'date' => $key,
                'label' => $date->format('d M'),
                'scans' => (int) ($scanCounts[$key] ?? 0),
                'payments' => (int) ($paymentCounts[$key] ?? 0),
            ];
        });
    }
}
