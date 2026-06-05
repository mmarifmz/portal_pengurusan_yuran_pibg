@php
    $calendarBlockLabel = $calendarBlockLabel ?? 'Takwim sekolah';
    $calendarBlockTitle = $calendarBlockTitle ?? 'Aktiviti & program semasa';
    $calendarBlockDescription = $calendarBlockDescription ?? 'Paparan bulan semasa. Klik pada aktiviti untuk melihat butiran penuh.';
    $paidCountByDate = $paidCountByDate ?? [];
    $loginCountByDate = $loginCountByDate ?? [];
    $visitCountByDate = $visitCountByDate ?? [];

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
        line-height: 1.2;
        min-width: 0;
        text-align: center;
    }

    #parentFullCalendar .fc-button {
        border-radius: 0.9rem;
        box-shadow: none;
        font-size: 0.86rem;
        font-weight: 600;
        line-height: 1;
        padding: 0.55rem 0.85rem;
        text-transform: capitalize;
    }

    #parentFullCalendar .fc-header-toolbar {
        align-items: center;
        display: grid;
        gap: 0.6rem;
        grid-template-columns: auto minmax(0, 1fr) auto;
        margin-bottom: 0.8rem;
    }

    #parentFullCalendar .fc-toolbar-chunk {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        min-width: 0;
    }

    #parentFullCalendar .fc-toolbar-chunk:nth-child(2) {
        display: none;
    }

    #parentFullCalendar .fc-toolbar-chunk:first-child > div {
        align-items: center;
        display: grid;
        gap: 0.45rem;
        grid-template-columns: auto minmax(8rem, auto) auto;
    }

    #parentFullCalendar .fc-toolbar-chunk:last-child {
        justify-content: flex-end;
    }

    #parentFullCalendar .fc-toolbar-chunk:last-child .fc-button-group {
        display: flex;
        gap: 0.35rem;
    }

    #parentFullCalendar .fc-toolbar-chunk:last-child .fc-button-group .fc-button {
        border-radius: 0.9rem;
        margin-left: 0;
    }

    #parentFullCalendar .fc-daygrid-day-frame {
        min-height: 5.75rem;
        padding: 0.1rem 0.15rem 0.2rem;
        position: relative;
    }

    #parentFullCalendar .fc-daygrid-day-top {
        justify-content: flex-start;
        padding: 0;
        position: relative;
        z-index: 2;
    }

    #parentFullCalendar .fc-daygrid-day {
        cursor: pointer;
        transition: background-color 140ms ease, box-shadow 140ms ease;
    }

    #parentFullCalendar .fc-daygrid-day:hover {
        background: rgba(59, 130, 246, 0.04);
    }

    #parentFullCalendar .fc-daygrid-day:focus-visible,
    #parentFullCalendar .calendar-day-selected {
        outline: 2px solid rgba(59, 130, 246, 0.35);
        outline-offset: -2px;
    }

    #parentFullCalendar .fc-daygrid-day.fc-day-other {
        background: #fafafa;
    }

    #parentFullCalendar .fc-daygrid-day.fc-day-other .fc-daygrid-day-number {
        color: #a1a1aa;
    }

    #parentFullCalendar .calendar-day-badges {
        display: flex;
        flex-direction: column;
        gap: 2px;
        left: 2px;
        margin: 0;
        max-width: 100%;
        min-height: 0;
        overflow: hidden;
        pointer-events: none;
        position: absolute;
        right: 2px;
        top: 1.25rem;
        z-index: 4;
    }

    #parentFullCalendar .calendar-day-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.1;
        max-width: 100%;
        overflow: hidden;
        padding: 2px 6px;
        text-overflow: ellipsis;
        white-space: nowrap;
        width: fit-content;
    }

    #parentFullCalendar .calendar-day-pill--paid {
        background: #dcfce7;
        border: 1px solid #86efac;
        color: #047857;
    }

    #parentFullCalendar .calendar-day-pill--login {
        background: #dbeafe;
        border: 1px solid #93c5fd;
        color: #1d4ed8;
    }

    #parentFullCalendar .calendar-day-pill--visit {
        background: #ede9fe;
        border: 1px solid #c4b5fd;
        color: #6d28d9;
    }

    #parentFullCalendar .fc-col-header-cell-cushion,
    #parentFullCalendar .fc-daygrid-day-number {
        color: #334155;
        font-weight: 600;
    }

    #parentFullCalendar .fc-daygrid-day-number {
        background: rgba(255, 255, 255, 0.86);
        border-radius: 999px;
        font-size: 0.78rem;
        line-height: 1.1;
        margin: 2px;
        min-width: 1.2rem;
        padding: 2px 5px;
        position: relative;
        text-align: center;
        z-index: 6;
    }

    #parentFullCalendar .fc-event {
        border: 0;
        border-radius: 0.5rem;
        background: #2f7a55;
        color: #ffffff;
        display: block;
        font-size: 0.7rem;
        font-weight: 600;
        line-height: 1.05;
        overflow: hidden;
        padding: 0.12rem 0.35rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #parentFullCalendar .fc-event:hover {
        background: #174a34;
    }

    #parentFullCalendar .fc-daygrid-day-events {
        margin-top: 2.65rem !important;
        min-height: 1.25rem;
        padding-top: 0;
        position: relative;
        z-index: 1;
    }

    #parentFullCalendar .fc-daygrid-event-harness {
        margin-top: 2px !important;
    }

    #parentFullCalendar .fc-day-today .fc-daygrid-day-frame {
        box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.35);
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

    @media (min-width: 1024px) {
        #parentFullCalendar .fc-daygrid-day-frame {
            min-height: 5.25rem;
        }

        #parentFullCalendar .calendar-day-badges {
            top: 1.35rem;
        }

        #parentFullCalendar .fc-daygrid-day-events {
            margin-top: 2.8rem !important;
        }
    }

    @media (max-width: 640px) {
        #parentFullCalendar {
            margin-left: -0.25rem;
            margin-right: -0.25rem;
            overflow-x: clip;
        }

        #parentFullCalendar .fc-header-toolbar {
            align-items: flex-start;
            gap: 0.45rem;
            grid-template-columns: minmax(0, 1fr);
            margin-bottom: 0.55rem;
        }

        #parentFullCalendar .fc-header-toolbar > .fc-toolbar-chunk {
            width: 100%;
        }

        #parentFullCalendar .fc-header-toolbar > .fc-toolbar-chunk:first-child > div {
            grid-template-columns: auto minmax(0, 1fr) auto;
            width: 100%;
        }

        #parentFullCalendar .fc-header-toolbar > .fc-toolbar-chunk:last-child {
            display: flex;
            justify-content: flex-start;
            width: 100%;
        }

        #parentFullCalendar .fc-header-toolbar > .fc-toolbar-chunk:last-child .fc-button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
        }

        #parentFullCalendar .fc-toolbar-title {
            font-size: 0.95rem;
            overflow: hidden;
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #parentFullCalendar .fc-button {
            border-radius: 999px;
            font-size: 0.68rem;
            line-height: 1;
            padding: 0.4rem 0.55rem;
        }

        #parentFullCalendar .fc .fc-button .fc-icon {
            font-size: 0.8rem;
        }

        #parentFullCalendar .fc-col-header-cell-cushion {
            font-size: 0.64rem;
            padding: 4px 1px;
        }

        #parentFullCalendar .fc-daygrid-day-frame {
            min-height: 5.15rem;
            padding: 1px 1px 2px;
        }

        #parentFullCalendar .fc-daygrid-day-number {
            font-size: 0.68rem;
            padding: 2px 3px 0;
        }

        #parentFullCalendar .calendar-day-badges {
            gap: 1px;
            margin: 0 1px 1px;
            top: 1.15rem;
        }

        #parentFullCalendar .calendar-day-pill {
            font-size: 10px;
            line-height: 1;
            padding: 2px 4px;
        }

        #parentFullCalendar .fc-event {
            border-radius: 0.45rem;
            font-size: 9px;
            line-height: 1.05;
            max-height: 0.8rem;
            padding: 1px 3px;
        }

        #parentFullCalendar .fc-daygrid-day-events {
            margin-top: 2.35rem !important;
            padding-top: 0;
        }

        #parentFullCalendar .fc-daygrid-day-bottom {
            display: none;
        }

        #parentCalendarModal {
            align-items: flex-end;
            padding: 0;
        }

        #parentCalendarModal .calendar-modal-panel {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            max-height: 86vh;
            overflow-y: auto;
            padding: 1rem;
        }
    }
</style>

<div class="rounded-3xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-5 lg:col-span-2">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-emerald-500">{{ $calendarBlockLabel }}</p>
            <h3 class="text-lg font-semibold text-zinc-900">{{ $calendarBlockTitle }}</h3>
            <p class="mt-1 text-sm text-zinc-500">{{ $calendarBlockDescription }}</p>
        </div>
    </div>

    <div id="parentFullCalendar" class="mt-4 sm:mt-5"></div>
</div>

<div id="parentCalendarModal" class="fixed inset-0 hidden items-center justify-center bg-black/40 px-4 py-6">
    <div class="calendar-modal-panel w-full max-w-lg rounded-3xl border border-zinc-200 p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-emerald-500">Ringkasan harian</p>
                <h3 class="mt-1 text-lg font-semibold text-zinc-900" id="calendarModalTitle"></h3>
                <p class="mt-1 text-sm text-zinc-500" id="calendarModalDate"></p>
            </div>
            <button type="button" id="calendarModalClose" class="rounded-2xl border border-zinc-200 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Tutup</button>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-2 text-xs" id="calendarModalStats"></div>
        <div class="mt-4 space-y-2" id="calendarModalEvents"></div>
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
        const modalStats = document.getElementById('calendarModalStats');
        const modalEvents = document.getElementById('calendarModalEvents');
        const modalClose = document.getElementById('calendarModalClose');
        const events = @json($calendarEventsPayload);
        const paidCountByDate = @json($paidCountByDate);
        const loginCountByDate = @json($loginCountByDate);
        const visitCountByDate = @json($visitCountByDate);
        let selectedDayCell = null;
        let lastFocusedElement = null;

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

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function dateKeyFromDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        function formatDateKey(dateKey) {
            return new Date(dateKey + 'T00:00:00').toLocaleDateString('ms-MY', {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            });
        }

        function compactEventTitle(title) {
            const value = String(title || '');
            const mobile = window.matchMedia('(max-width: 640px)').matches;
            const limit = mobile ? 18 : 42;

            return value.length > limit ? value.slice(0, Math.max(0, limit - 3)).trimEnd() + '...' : value;
        }

        function addDays(date, days) {
            const next = new Date(date);
            next.setDate(next.getDate() + days);

            return next;
        }

        function buildEventsByDate() {
            const grouped = {};

            events.forEach((eventData) => {
                const start = new Date(eventData.extendedProps.display_start + 'T00:00:00');
                const end = new Date(eventData.extendedProps.display_end + 'T00:00:00');

                for (let cursor = start; cursor <= end; cursor = addDays(cursor, 1)) {
                    const key = dateKeyFromDate(cursor);
                    grouped[key] = grouped[key] || [];
                    grouped[key].push(eventData);
                }
            });

            return grouped;
        }

        const eventsByDate = buildEventsByDate();

        function daySummary(dateKey) {
            const paidCount = Number(paidCountByDate[dateKey] || 0);
            const loginCount = Number(loginCountByDate[dateKey] || 0);
            const visitCount = Number(visitCountByDate[dateKey] || 0);
            const displayVisitCount = loginCount > 0 ? 0 : visitCount;
            const dayEvents = eventsByDate[dateKey] || [];

            return {
                paidCount,
                loginCount,
                visitCount,
                displayVisitCount,
                dayEvents,
            };
        }

        function dayAriaLabel(dateKey) {
            const summary = daySummary(dateKey);
            const parts = [formatDateKey(dateKey)];

            if (summary.dayEvents.length > 0) {
                parts.push(`${summary.dayEvents.length} aktiviti`);
            }

            if (summary.paidCount > 0) {
                parts.push(`${summary.paidCount} paid`);
            }

            if (summary.loginCount > 0) {
                parts.push(`${summary.loginCount} login`);
            } else if (summary.displayVisitCount > 0) {
                parts.push(`${summary.displayVisitCount} views`);
            }

            parts.push('Tekan Enter untuk ringkasan harian');

            return parts.join(', ');
        }

        function statCard(label, value, colorClasses) {
            return `
                <div class="rounded-2xl border px-3 py-2 ${colorClasses}">
                    <p class="font-semibold">${escapeHtml(label)}</p>
                    <p class="mt-1 text-lg font-bold">${Number(value || 0).toLocaleString('en-MY')}</p>
                </div>
            `;
        }

        function openDayModal(dateKey, triggerElement = null) {
            const { paidCount, loginCount, visitCount, dayEvents } = daySummary(dateKey);

            selectedDayCell?.classList.remove('calendar-day-selected');
            selectedDayCell = triggerElement || calendarEl.querySelector(`.fc-daygrid-day[data-date="${dateKey}"]`);
            selectedDayCell?.classList.add('calendar-day-selected');
            lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : selectedDayCell;

            modalTitle.textContent = formatDateKey(dateKey);
            modalDate.textContent = dayEvents.length
                ? `${dayEvents.length} aktiviti direkodkan`
                : 'Tiada aktiviti sekolah pada tarikh ini';

            modalStats.innerHTML = [
                statCard('Paid families', paidCount, 'border-emerald-100 bg-emerald-50 text-emerald-800'),
                statCard('Parent login', loginCount, 'border-blue-100 bg-blue-50 text-blue-800'),
                statCard('Page views', visitCount, 'border-violet-100 bg-violet-50 text-violet-800'),
            ].join('');

            modalEvents.innerHTML = dayEvents.length
                ? dayEvents.map((eventData) => `
                    <article class="rounded-2xl border border-zinc-100 bg-zinc-50 px-3 py-3 text-sm">
                        <h4 class="font-semibold text-zinc-900">${escapeHtml(eventData.title)}</h4>
                        <p class="mt-1 text-xs text-zinc-500">${escapeHtml(formatLongDate(
                            eventData.extendedProps.display_start,
                            eventData.extendedProps.display_end,
                            eventData.extendedProps.day_label
                        ))}</p>
                        ${eventData.extendedProps.description ? `<p class="mt-2 text-zinc-700">${escapeHtml(eventData.extendedProps.description)}</p>` : ''}
                        <p class="mt-2 text-xs text-zinc-500">${escapeHtml(eventData.extendedProps.notes || 'Tiada catatan tambahan.')}</p>
                    </article>
                `).join('')
                : '<p class="rounded-2xl border border-zinc-100 bg-zinc-50 px-3 py-3 text-sm text-zinc-500">Tiada aktiviti sekolah untuk hari ini.</p>';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modalClose?.focus({ preventScroll: true });
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            selectedDayCell?.classList.remove('calendar-day-selected');
            selectedDayCell = null;
            lastFocusedElement?.focus?.({ preventScroll: true });
            lastFocusedElement = null;
        }

        modal?.setAttribute('role', 'dialog');
        modal?.setAttribute('aria-modal', 'true');
        modal?.setAttribute('aria-labelledby', 'calendarModalTitle');

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            initialDate: new Date(),
            headerToolbar: {
                left: 'prev,title,next',
                center: '',
                right: 'today dayGridMonth,listMonth',
            },
            buttonText: {
                today: 'Hari Ini',
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
                const key = dateKeyFromDate(info.date);
                const { paidCount, loginCount, displayVisitCount } = daySummary(key);

                info.el.setAttribute('role', 'button');
                info.el.setAttribute('tabindex', '0');
                info.el.setAttribute('aria-label', dayAriaLabel(key));
                info.el.addEventListener('click', () => openDayModal(key, info.el));
                info.el.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }

                    event.preventDefault();
                    openDayModal(key, info.el);
                });

                if (!paidCount && !loginCount && !displayVisitCount) {
                    return;
                }

                const dayFrame = info.el.querySelector('.fc-daygrid-day-frame');
                const dayEvents = info.el.querySelector('.fc-daygrid-day-events');
                if (!dayFrame || !dayEvents) {
                    return;
                }

                const badgeStack = document.createElement('div');
                badgeStack.className = 'calendar-day-badges';

                [
                    [paidCount, 'paid', 'calendar-day-pill--paid'],
                    [loginCount, 'login', 'calendar-day-pill--login'],
                    [displayVisitCount, 'views', 'calendar-day-pill--visit'],
                ].forEach(([count, label, className]) => {
                    if (!Number(count)) {
                        return;
                    }

                    const badge = document.createElement('span');
                    badge.className = `calendar-day-pill ${className}`;
                    badge.textContent = `${Number(count)} ${label}`;
                    badgeStack.appendChild(badge);
                });

                dayFrame.insertBefore(badgeStack, dayEvents);
            },
            eventContent(info) {
                return {
                    html: `<span title="${escapeHtml(info.event.title)}">${escapeHtml(compactEventTitle(info.event.title))}</span>`,
                };
            },
            eventClick(info) {
                info.jsEvent.preventDefault();
                info.jsEvent.stopPropagation();
                openDayModal(info.event.extendedProps.display_start, info.el.closest('.fc-daygrid-day'));
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
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal?.classList.contains('hidden')) {
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
