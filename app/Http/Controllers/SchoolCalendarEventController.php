<?php

namespace App\Http\Controllers;

use App\Models\SchoolCalendarEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolCalendarEventController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEvent($request);

        SchoolCalendarEvent::create($data + [
            'sort_order' => (int) (SchoolCalendarEvent::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('status', 'Aktiviti kalendar berjaya ditambah.');
    }

    public function update(Request $request, SchoolCalendarEvent $schoolCalendarEvent): RedirectResponse
    {
        $schoolCalendarEvent->update($this->validateEvent($request));

        return back()->with('status', 'Aktiviti kalendar berjaya dikemas kini.');
    }

    public function destroy(SchoolCalendarEvent $schoolCalendarEvent): RedirectResponse
    {
        $schoolCalendarEvent->delete();

        return back()->with('status', 'Aktiviti kalendar berjaya dipadam.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEvent(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'day_label' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (blank($data['end_date'] ?? null)) {
            $data['end_date'] = null;
        }

        if (blank($data['day_label'] ?? null)) {
            $data['day_label'] = null;
        }

        if (blank($data['notes'] ?? null)) {
            $data['notes'] = null;
        }

        return $data;
    }
}
