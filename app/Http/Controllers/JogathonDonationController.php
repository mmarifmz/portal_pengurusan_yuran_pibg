<?php

namespace App\Http\Controllers;

use App\Models\JogathonCampaign;
use App\Models\JogathonCause;
use App\Models\JogathonContribution;
use App\Models\JogathonParticipant;
use App\Services\JogathonPublicProgressService;
use App\Services\JogathonToyyibPayService;
use App\Support\JogathonAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class JogathonDonationController extends Controller
{
    public function __construct(private readonly JogathonToyyibPayService $toyyibPayService) {}

    public function create(
        Request $request,
        JogathonCampaign $jogathonCampaign,
        string $publicSlug,
        JogathonPublicProgressService $progressService,
    ): View {
        abort_unless($jogathonCampaign->isPubliclyAvailable(), 404);

        $participant = $this->publicParticipant($jogathonCampaign, $publicSlug);
        $activeCauses = $this->activeCauses($jogathonCampaign);

        return view('jogathon.public.donation', [
            'campaign' => $jogathonCampaign,
            'participant' => $participant,
            'progress' => $progressService->forParticipant($participant),
            'activeCauses' => $activeCauses,
            'selectedAmount' => $this->selectedAmount($request),
        ]);
    }

    public function store(Request $request, JogathonCampaign $jogathonCampaign, string $publicSlug): RedirectResponse
    {
        abort_unless($jogathonCampaign->isPubliclyAvailable(), 404);

        $participant = $this->publicParticipant($jogathonCampaign, $publicSlug);
        $causeIds = $this->activeCauses($jogathonCampaign)->pluck('id')->all();

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'cause_id' => [
                $jogathonCampaign->allow_unspecified_cause ? 'nullable' : 'required',
                'integer',
                Rule::in($causeIds),
            ],
            'donor_name' => ['required', 'string', 'max:120'],
            'donor_email' => ['required', 'email', 'max:255'],
            'donor_phone' => ['required', 'string', 'max:25'],
            'is_anonymous_public' => ['nullable', 'boolean'],
            'encouragement_message' => ['nullable', 'string', 'max:280'],
        ]);

        $amountSen = JogathonAmount::senFromRinggit((string) $validated['amount']);

        if ($amountSen < 1_000) {
            return back()->withErrors([
                'amount' => 'Jumlah minimum sumbangan dalam talian ialah RM10.00.',
            ])->withInput();
        }

        $externalOrderId = $this->generateExternalOrderId();
        $isAnonymous = (bool) ($validated['is_anonymous_public'] ?? false);

        try {
            $billCode = $this->toyyibPayService->createBill([
                'billName' => 'Jogathon SKSP 2026 - '.$participant->public_display_name,
                'billDescription' => $this->buildBillDescription($participant, (int) ($validated['cause_id'] ?? 0), $amountSen),
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => $amountSen,
                'billReturnUrl' => route('jogathon.donations.return'),
                'billCallbackUrl' => route('jogathon.donations.callback'),
                'billExternalReferenceNo' => $externalOrderId,
                'billTo' => (string) $validated['donor_name'],
                'billEmail' => (string) $validated['donor_email'],
                'billPhone' => (string) $validated['donor_phone'],
                'billSplitPayment' => 0,
                'billSplitPaymentArgs' => '',
            ]);
        } catch (RuntimeException $exception) {
            Log::warning('Jogathon ToyyibPay bill creation failed', [
                'campaign_id' => $jogathonCampaign->id,
                'participant_id' => $participant->id,
                'cause_id' => $validated['cause_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'payment_gateway' => $exception->getMessage(),
            ])->withInput();
        }

        JogathonContribution::query()->create([
            'campaign_id' => $jogathonCampaign->id,
            'participant_id' => $participant->id,
            'cause_id' => $validated['cause_id'] ?? null,
            'source' => JogathonContribution::SOURCE_ONLINE,
            'amount_sen' => $amountSen,
            'status' => JogathonContribution::STATUS_PENDING,
            'donor_display_name' => $isAnonymous ? null : trim((string) $validated['donor_name']),
            'is_anonymous_public' => $isAnonymous,
            'encouragement_message' => blank($validated['encouragement_message'] ?? null)
                ? null
                : trim((string) $validated['encouragement_message']),
            'is_message_approved' => false,
            'external_order_id' => $externalOrderId,
            'provider_bill_code' => $billCode,
            'metadata' => [
                'donor_email' => mb_strtolower(trim((string) $validated['donor_email'])),
                'donor_phone' => trim((string) $validated['donor_phone']),
                'checkout_ip' => $request->ip(),
                'checkout_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            ],
        ]);

        return redirect()->away($this->toyyibPayService->paymentUrl($billCode));
    }

    public function handleReturn(Request $request): RedirectResponse
    {
        $billCode = (string) $request->query('billcode', '');
        $orderId = (string) $request->query('order_id', '');

        $contribution = $this->findContribution($orderId, $billCode);

        if (! $contribution) {
            return redirect()->route('home')->with('status', 'Rekod sumbangan Jogathon tidak dijumpai.');
        }

        $metadata = $contribution->metadata ?? [];
        $metadata['toyyibpay_return'] = $request->query();
        $metadata['returned_at'] = now()->toISOString();

        $contribution->forceFill([
            'provider_bill_code' => filled($billCode) ? $billCode : $contribution->provider_bill_code,
            'metadata' => $metadata,
        ])->save();

        return redirect()->route('jogathon.donations.summary', $contribution->external_order_id);
    }

    public function handleCallback(Request $request): Response
    {
        $status = (string) $request->input('status', '');
        $orderId = (string) $request->input('order_id', '');
        $refNo = (string) $request->input('refno', '');
        $hash = (string) $request->input('hash', '');
        $billCode = (string) $request->input('billcode', '');

        if (! $this->toyyibPayService->verifyCallbackHash($status, $orderId, $refNo, $hash)) {
            return response('invalid hash', 422);
        }

        $contribution = $this->findContribution($orderId, $billCode);

        if (! $contribution) {
            return response('contribution not found', 404);
        }

        $metadata = $contribution->metadata ?? [];
        $metadata['toyyibpay_callback'] = $request->all();
        $metadata['callback_received_at'] = now()->toISOString();

        if ($contribution->status === JogathonContribution::STATUS_SUCCESSFUL) {
            $contribution->forceFill([
                'provider_bill_code' => filled($billCode) ? $billCode : $contribution->provider_bill_code,
                'provider_reference' => filled($contribution->provider_reference)
                    ? $contribution->provider_reference
                    : (filled($refNo) ? $refNo : null),
                'metadata' => $metadata,
            ])->save();

            return response('ok');
        }

        if ($status !== '1') {
            $contribution->forceFill([
                'provider_bill_code' => filled($billCode) ? $billCode : $contribution->provider_bill_code,
                'provider_reference' => filled($refNo) ? $refNo : $contribution->provider_reference,
                'status' => $status === '3'
                    ? JogathonContribution::STATUS_FAILED
                    : JogathonContribution::STATUS_PENDING,
                'metadata' => $metadata,
            ])->save();

            return response('ok');
        }

        try {
            $gatewayTransactions = $this->toyyibPayService->getBillTransactions($billCode ?: (string) $contribution->provider_bill_code);
        } catch (RuntimeException $exception) {
            Log::warning('Jogathon ToyyibPay reconciliation failed', [
                'contribution_id' => $contribution->id,
                'external_order_id' => $contribution->external_order_id,
                'provider_bill_code' => $billCode ?: $contribution->provider_bill_code,
                'error' => $exception->getMessage(),
            ]);

            return response('reconciliation failed', 503);
        }

        $matchedGatewayTransaction = $this->matchingGatewayTransaction($gatewayTransactions, $contribution, $orderId);
        $metadata['toyyibpay_transactions'] = $gatewayTransactions;

        if ($matchedGatewayTransaction === []) {
            Log::warning('Jogathon ToyyibPay reconciliation mismatch', [
                'contribution_id' => $contribution->id,
                'external_order_id' => $contribution->external_order_id,
                'provider_bill_code' => $billCode ?: $contribution->provider_bill_code,
            ]);

            return response('reconciliation mismatch', 409);
        }

        DB::transaction(function () use ($contribution, $billCode, $refNo, $matchedGatewayTransaction, $metadata): void {
            $freshContribution = JogathonContribution::query()
                ->whereKey($contribution->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($freshContribution->status === JogathonContribution::STATUS_SUCCESSFUL) {
                return;
            }

            $freshContribution->forceFill([
                'provider_bill_code' => filled($billCode) ? $billCode : $freshContribution->provider_bill_code,
                'provider_reference' => $this->providerReference($matchedGatewayTransaction, $refNo, $freshContribution->provider_reference),
                'status' => JogathonContribution::STATUS_SUCCESSFUL,
                'received_at' => $this->receivedAt($matchedGatewayTransaction) ?? now(),
                'finalised_at' => now(),
                'metadata' => array_merge($freshContribution->metadata ?? [], $metadata),
            ])->save();
        });

        return response('ok');
    }

    public function summary(string $externalOrderId): View
    {
        $contribution = JogathonContribution::query()
            ->with([
                'campaign:id,name,slug,status,show_class_publicly,allow_public_indexing,archived_at',
                'participant' => fn ($query) => $query->select($this->participantSummaryColumns()),
                'cause:id,name',
            ])
            ->where('external_order_id', $externalOrderId)
            ->firstOrFail();

        return view('jogathon.public.donation-summary', [
            'campaign' => $contribution->campaign,
            'contribution' => $contribution,
        ]);
    }

    private function buildBillDescription(JogathonParticipant $participant, int $causeId, int $amountSen): string
    {
        $causeName = $causeId > 0
            ? (string) JogathonCause::query()->whereKey($causeId)->value('name')
            : 'Tujuan belum ditetapkan';

        return sprintf(
            'Sumbangan Jogathon SKSP 2026 untuk %s. Tujuan: %s. Jumlah: %s.',
            $participant->public_display_name,
            $causeName,
            JogathonAmount::ringgit($amountSen)
        );
    }

    private function publicParticipant(JogathonCampaign $campaign, string $publicSlug): JogathonParticipant
    {
        $normalizedCardNumber = JogathonParticipant::normalizePhysicalCardNumber($publicSlug);

        $participant = JogathonParticipant::query()
            ->where('campaign_id', $campaign->id)
            ->where(function ($query) use ($normalizedCardNumber, $publicSlug): void {
                $query->where('public_slug', $publicSlug);

                if ($normalizedCardNumber !== null && JogathonParticipant::hasPhysicalCardNumberColumn()) {
                    $query->orWhere('physical_card_number', $normalizedCardNumber);
                }
            })
            ->firstOrFail();

        abort_unless($participant->isPubliclyVisible(), 404);

        return $participant;
    }

    private function activeCauses(JogathonCampaign $campaign): Collection
    {
        return $campaign->causes()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->get(['id', 'name', 'description', 'target_amount_sen']);
    }

    /**
     * @return array<int, string>
     */
    private function participantSummaryColumns(): array
    {
        $columns = [
            'id',
            'campaign_id',
            'public_slug',
            'public_display_name',
            'class_name_snapshot',
        ];

        if (JogathonParticipant::hasPhysicalCardNumberColumn()) {
            $columns[] = 'physical_card_number';
        }

        return $columns;
    }

    private function selectedAmount(Request $request): string
    {
        $amount = trim((string) $request->query('amount', '20.00'));

        return preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $amount) === 1 ? $amount : '20.00';
    }

    private function findContribution(string $orderId, string $billCode): ?JogathonContribution
    {
        return JogathonContribution::query()
            ->when(filled($orderId), fn ($query) => $query->where('external_order_id', $orderId))
            ->when(blank($orderId) && filled($billCode), fn ($query) => $query->where('provider_bill_code', $billCode))
            ->latest('id')
            ->first();
    }

    private function matchingGatewayTransaction(array $gatewayTransactions, JogathonContribution $contribution, string $orderId): array
    {
        foreach ($gatewayTransactions as $gatewayTransaction) {
            if (! is_array($gatewayTransaction)) {
                continue;
            }

            $gatewayOrderId = (string) ($gatewayTransaction['billExternalReferenceNo'] ?? '');
            $gatewayAmount = $this->gatewayAmountSen($gatewayTransaction);

            if (($gatewayOrderId === $orderId || $gatewayOrderId === $contribution->external_order_id)
                && $gatewayAmount === (int) $contribution->amount_sen) {
                return $gatewayTransaction;
            }
        }

        return [];
    }

    private function gatewayAmountSen(array $gatewayTransaction): int
    {
        $amount = (string) ($gatewayTransaction['billpaymentAmount']
            ?? $gatewayTransaction['billPaymentAmount']
            ?? $gatewayTransaction['amount']
            ?? '0');

        if (str_contains($amount, '.')) {
            return JogathonAmount::senFromRinggit($amount);
        }

        return ((int) $amount) * 100;
    }

    private function providerReference(array $gatewayTransaction, string $fallbackRefNo, ?string $currentReference): ?string
    {
        $reference = $gatewayTransaction['billpaymentInvoiceNo']
            ?? $gatewayTransaction['billPaymentInvoiceNo']
            ?? $fallbackRefNo
            ?? $currentReference;

        return filled($reference) ? (string) $reference : null;
    }

    private function receivedAt(array $gatewayTransaction): ?Carbon
    {
        $date = $gatewayTransaction['billPaymentDate']
            ?? $gatewayTransaction['billpaymentDate']
            ?? null;

        return filled($date) ? Carbon::parse((string) $date) : null;
    }

    private function generateExternalOrderId(): string
    {
        do {
            $externalOrderId = 'JOG-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (JogathonContribution::query()->where('external_order_id', $externalOrderId)->exists());

        return $externalOrderId;
    }
}
