<?php

namespace App\Http\Controllers;

use App\Jobs\SendTeacherPaymentNotificationJob;
use App\Models\TeacherPaymentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherPaymentNotificationAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = TeacherPaymentNotification::query()
            ->with(['student:id,full_name', 'family:id,family_code'])
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', (string) $request->string('status')))
            ->when($request->filled('class_name'), fn ($builder) => $builder->where('class_name', (string) $request->string('class_name')))
            ->when($request->filled('teacher'), function ($builder) use ($request): void {
                $teacher = trim((string) $request->input('teacher'));

                $builder->where(function ($inner) use ($teacher): void {
                    $inner->where('teacher_name', 'like', "%{$teacher}%")
                        ->orWhere('teacher_phone', 'like', "%{$teacher}%");
                });
            })
            ->when($request->filled('order_id'), fn ($builder) => $builder->where('order_id', 'like', '%'.trim((string) $request->input('order_id')).'%'))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('queued_at', '>=', (string) $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('queued_at', '<=', (string) $request->input('date_to')))
            ->latest('queued_at');

        return view('system.teacher-payment-notifications', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'statusOptions' => [
                TeacherPaymentNotification::STATUS_QUEUED,
                TeacherPaymentNotification::STATUS_PROCESSING,
                TeacherPaymentNotification::STATUS_SENT,
                TeacherPaymentNotification::STATUS_FAILED,
                TeacherPaymentNotification::STATUS_RETRYING,
                TeacherPaymentNotification::STATUS_CANCELLED,
            ],
            'classOptions' => TeacherPaymentNotification::query()
                ->whereNotNull('class_name')
                ->where('class_name', '!=', '')
                ->orderBy('class_name')
                ->distinct()
                ->pluck('class_name'),
            'kpis' => [
                'queued' => TeacherPaymentNotification::query()->where('status', TeacherPaymentNotification::STATUS_QUEUED)->count(),
                'processing' => TeacherPaymentNotification::query()->where('status', TeacherPaymentNotification::STATUS_PROCESSING)->count(),
                'sent' => TeacherPaymentNotification::query()->where('status', TeacherPaymentNotification::STATUS_SENT)->count(),
                'failed' => TeacherPaymentNotification::query()->where('status', TeacherPaymentNotification::STATUS_FAILED)->count(),
                'retrying' => TeacherPaymentNotification::query()->where('status', TeacherPaymentNotification::STATUS_RETRYING)->count(),
                'cancelled' => TeacherPaymentNotification::query()->where('status', TeacherPaymentNotification::STATUS_CANCELLED)->count(),
            ],
        ]);
    }

    public function show(TeacherPaymentNotification $teacherPaymentNotification): JsonResponse
    {
        return response()->json([
            'message_body' => $teacherPaymentNotification->message_body,
            'status' => $teacherPaymentNotification->status,
        ]);
    }

    public function retry(TeacherPaymentNotification $teacherPaymentNotification): RedirectResponse
    {
        if ($teacherPaymentNotification->status !== TeacherPaymentNotification::STATUS_FAILED) {
            return back()->with('status', 'Hanya rekod failed boleh dihantar semula.');
        }

        $teacherPaymentNotification->resetForRetry();

        SendTeacherPaymentNotificationJob::dispatch($teacherPaymentNotification->id)
            ->onQueue('teacher-notification');

        return back()->with('status', 'Makluman kepada guru kelas telah dimasukkan semula ke dalam giliran penghantaran.');
    }

    public function cancel(TeacherPaymentNotification $teacherPaymentNotification): RedirectResponse
    {
        if (! in_array($teacherPaymentNotification->status, [
            TeacherPaymentNotification::STATUS_QUEUED,
            TeacherPaymentNotification::STATUS_RETRYING,
            TeacherPaymentNotification::STATUS_FAILED,
        ], true)) {
            return back()->with('status', 'Hanya rekod queued, retrying atau failed boleh dibatalkan.');
        }

        $teacherPaymentNotification->markCancelled();

        return back()->with('status', 'Makluman kepada guru kelas telah dibatalkan.');
    }
}
