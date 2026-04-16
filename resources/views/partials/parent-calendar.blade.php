@php
    $calendarBlockLabel = $calendarBlockLabel ?? 'Takwim sekolah';
    $calendarBlockTitle = $calendarBlockTitle ?? 'Aktiviti & program semasa';
    $calendarBlockDescription = $calendarBlockDescription ?? 'Paparan bulan semasa. Klik pada aktiviti untuk melihat butiran penuh.';
    $paidCountByDate = $paidCountByDate ?? [];

    $calendarEventsPayload = $calendarEvents->map(function ($event) {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->start_date->format('Y-m-d'),
            'end' => $event->end_date
                ? $event->end_date->copy()->addDay()->format('Y-m-d')
                : $event->start_date->copy()->addDay()->format('Y-m-d'),
            'allDay' => true,
            'extendedProps' => [
                'display_start' => $event->start_date->format('Y-m-d'),
                'display_end' => $event->end_date?->format('Y-m-d') ?? $event->start_date->format('Y-m-d'),
                'day_label' => $event->day_label,
                'description' => $event->description,
                'notes' => $event->notes,
            ],
        ];
    })->values();
@endphp

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.css">

<style>
    #parentFullCalendar .fc {
        --fc-border-color: #e4e4e7;
        --fc-button-bg-color: #174a34;
        --fc-button-border-color: #174a34;
        --fc-button-hover-bg-color: #2f7a55;
        --fc-button-hover-border-color: #2f7a55;
        --fc-button-active-bg-color: #174a34;
        --fc-button-active-border-color: #174a34;
        --fc-today-bg-color: rgba(47, 122, 85, 0.08);
        color: #1f2a24;
    }

    #parentFullCalendar .fc-toolbar-title {
        font-size: 1.1rem;
        font-weight: 700;
    }

    #parentFullCalendar .fc-button {
        border-radius: 0.9rem;
        box-shadow: none;
        font-weight: 600;
        padding: 0.5rem 0.9rem;
        text-transform: capitalize;
    }

    #parentFullCalendar .fc-daygrid-day-frame {
        min-height: 6.5rem;
    }

    #parentFullCalendar .fc-daygrid-day-top {
        position: relative;
    }

    #parentFullCalendar .calendar-paid-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #047857;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        padding: 2px 6px;
        margin-left: auto;
        margin-right: 2px;
        margin-top: 2px;
    }

    #parentFullCalendar .fc-col-header-cell-cushion,
    #parentFullCalendar .fc-daygrid-day-number {
        color: #334155;
        font-weight: 600;
    }

    #parentFullCalendar .fc-event {
        border: 0;
        border-radius: 0.75rem;
        background: #2f7a55;
        color: #ffffff;
        font-weight: 600;
        padding: 0.1rem 0.3rem;
    }

    #parentFullCalendar .fc-event:hover {
        background: #174a34;
    }

    #parentCalendarModal {
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(4px);
        z-index: 9999;
    }

    #parentCalendarModal .calendar-modal-panel {
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(247, 250, 248, 0.98));
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
        position: relative;
        z-index: 10000;
    }
</style>

<div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm lg:col-span-2">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-emerald-500">{{ $calendarBlockLabel }}</p>
            <h3 class="text-lg font-semibold text-zinc-900">{{ $calendarBlockTitle }}</h3>
            <p class="mt-1 text-sm text-zinc-500">{{ $calendarBlockDescription }}</p>
        </div>
    </div>

    <div id="parentFullCalendar" class="mt-5"></div>
</div>

<div id="parentCalendarModal" class="fixed inset-0 hidden items-center justify-center bg-black/40 px-4 py-6">
    <div class="calendar-modal-panel w-full max-w-lg rounded-3xl border border-zinc-200 p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-emerald-500">Butiran aktiviti</p>
                <h3 class="mt-1 text-lg font-semibold text-zinc-900" id="calendarModalTitle"></h3>
                <p class="mt-1 text-sm text-zinc-500" id="calendarModalDate"></p>
            </div>
            <button type="button" id="calendarModalClose" class="rounded-2xl border border-zinc-200 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Tutup</button>
        </div>
        <p class="mt-4 text-sm text-zinc-700" id="calendarModalDescription"></p>
        <p class="mt-3 rounded-2xl border border-zinc-100 bg-zinc-50 px-3 py-3 text-sm text-zinc-600" id="calendarModalNotes"></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<script>
    (() => {
        function initParentCalendar() {
        const calendarEl = document.getElementById('parentFullCalendar');
        const modal = document.getElementById('parentCalendarModal');
        const modalTitle = document.getElementById('calendarModalTitle');
        const modalDate = document.getElementById('calendarModalDate');
        const modalDescription = document.getElementById('calendarModalDescription');
        const modalNotes = document.getElementById('calendarModalNotes');
        const modalClose = document.getElementById('calendarModalClose');
        const events = @json($calendarEventsPayload);
        const paidCountByDate = @json($paidCountByDate);

        if (!calendarEl || calendarEl.dataset.calendarReady === '1' || typeof FullCalendar === 'undefined') {
            return;
        }

        function formatLongDate(start, end, dayLabel) {
            const startDate = new Date(start + 'T00:00:00');
            const endDate = new Date(end + 'T00:00:00');
            const startText = startDate.toLocaleDateString('ms-MY', { day: '2-digit', month: 'long', year: 'numeric' });
            const endText = endDate.toLocaleDateString('ms-MY', { day: '2-digit', month: 'long', year: 'numeric' });

            if (start === end) {
                return `${startText}${dayLabel ? ' / ' + dayLabel : ''}`;
            }

            return `${startText} - ${endText}${dayLabel ? ' / ' + dayLabel : ''}`;
        }

        function openModal(eventData) {
            modalTitle.textContent = eventData.title;
            modalDate.textContent = formatLongDate(
                eventData.extendedProps.display_start,
                eventData.extendedProps.display_end,
                eventData.extendedProps.day_label
            );
            modalDescription.textContent = eventData.extendedProps.description || '';
            modalNotes.textContent = eventData.extendedProps.notes || 'Tiada catatan tambahan.';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            initialDate: new Date(),
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,listMonth',
            },
            buttonText: {
                today: 'Hari ini',
                month: 'Bulan',
                list: 'Senarai',
            },
            height: 'auto',
            fixedWeekCount: false,
            showNonCurrentDates: true,
            firstDay: 1,
            navLinks: false,
            events: events,
            dayCellDidMount(info) {
                const year = info.date.getFullYear();
                const month = String(info.date.getMonth() + 1).padStart(2, '0');
                const day = String(info.date.getDate()).padStart(2, '0');
                const key = `${year}-${month}-${day}`;
                const paidCount = Number(paidCountByDate[key] || 0);
                if (!paidCount) {
                    return;
                }

                const dayTop = info.el.querySelector('.fc-daygrid-day-top');
                if (!dayTop) {
                    return;
                }

                const badge = document.createElement('span');
                badge.className = 'calendar-paid-pill';
                badge.textContent = `${paidCount} paid`;
                dayTop.appendChild(badge);
            },
            eventClick(info) {
                info.jsEvent.preventDefault();
                openModal(info.event);
            },
        });

        calendar.render();
        calendarEl.dataset.calendarReady = '1';

        modalClose?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
        }

        document.addEventListener('DOMContentLoaded', initParentCalendar);
        document.addEventListener('livewire:navigated', initParentCalendar);
        window.addEventListener('pageshow', initParentCalendar);
        setTimeout(initParentCalendar, 50);
        setTimeout(initParentCalendar, 250);
    })();
</script>
