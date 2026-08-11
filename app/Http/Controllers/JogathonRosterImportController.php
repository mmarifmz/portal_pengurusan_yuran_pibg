<?php

namespace App\Http\Controllers;

use App\Models\JogathonCampaign;
use App\Services\JogathonParticipantProvisioningService;
use App\Services\JogathonRosterImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JogathonRosterImportController extends Controller
{
    public function store(
        Request $request,
        JogathonCampaign $jogathonCampaign,
        JogathonRosterImportService $importService,
        JogathonParticipantProvisioningService $participantProvisioningService,
    ): RedirectResponse {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'class_names' => ['required', 'string', 'max:2000'],
            'keywords' => ['required', 'string', 'max:2000'],
            'teacher_mappings' => ['nullable', 'string', 'max:4000'],
            'provision_participants' => ['nullable', 'boolean'],
        ]);

        $stats = $importService->import(
            classNames: [$validated['class_names']],
            keywords: [$validated['keywords']],
            teacherNamesByClass: $importService->parseTeacherMappings($validated['teacher_mappings'] ?? null),
            apiKey: (string) $validated['api_key'],
            year: (int) $validated['year'],
            endpoint: (string) $validated['endpoint'],
        );

        $provisionStats = null;

        if ((bool) ($validated['provision_participants'] ?? false)) {
            $provisionStats = $participantProvisioningService->provision($jogathonCampaign, $request->user());
        }

        $message = sprintf(
            'Import roster selesai: %d murid baharu, %d dikemas kini, %d guru kelas, %d API request.',
            $stats['imported'],
            $stats['updated'],
            $stats['teachers'],
            $stats['requests'],
        );

        if ($provisionStats !== null) {
            $message .= sprintf(
                ' Provision peserta: %d layak, %d baharu, %d disegarkan.',
                $provisionStats['eligible'],
                $provisionStats['created'],
                $provisionStats['refreshed'],
            );
        }

        return redirect()
            ->route('system.jogathon.campaigns.index', ['campaign' => $jogathonCampaign->id])
            ->with('status', $message);
    }
}
