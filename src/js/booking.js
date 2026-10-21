/**
 * src/js/booking.js
 * Public booking form with a week view: one column per day, time running downwards. Free
 * start times come from api.php?action=check_week; the selection block snaps to them.
 * All visible texts come from the page's #booking-config JSON
 * (translation keys under "booking."), so this file contains no language.
 */
(function () {
    'use strict';

    const config = JSON.parse(document.getElementById('booking-config').textContent);

    /** Translates a key, replacing {placeholders}; falls back to the key itself. */
    function t(key, vars) {
        let text = config.texts[key] || key;
        Object.entries(vars || {}).forEach(([name, value]) => {
            text = text.split('{' + name + '}').join(String(value));
        });
        return text;
    }

    /** Message for an API error: translated by error code (slot_unavailable -> booking.error.slotUnavailable), otherwise the API's text. */
    function errorText(data) {
        const code = String((data && data.code) || '').replace(/_([a-z])/g, (m, c) => c.toUpperCase());
        const key = 'booking.error.' + code;
        return config.texts[key] ? t(key) : t('booking.result.failed', { reason: (data && data.error) || '' });
    }

    function api(params, options) {
        const query = new URLSearchParams(params).toString();
        return fetch(config.apiUrl + '?' + query, options).then((res) => res.json());
    }

    const serviceSelect = document.getElementById('serviceSelect');
    const dateInput = document.getElementById('dateInput');
    const slotsContainer = document.getElementById('slotsContainer');
    const bookingForm = document.getElementById('bookingForm');
    const alertBox = document.getElementById('alertBox');
    const weekLabel = document.getElementById('weekLabel');
    const prevWeek = document.getElementById('prevWeek');
    const nextWeek = document.getElementById('nextWeek');
    const earlierBtn = document.getElementById('earlierBtn');
    const laterBtn = document.getElementById('laterBtn');
    const selectionText = document.getElementById('selectionText');

    /** Pixels per minute on the vertical time axis. */
    const PX_PER_MIN = 1;

    let week = null;       // last check_week response
    let range = null;      // { start, end } in minutes, shared by all day columns
    let allSlots = [];     // every free start of the week, in order: { day, slot, minute }
    let chosen = null;     // entry of allSlots
    let columns = [];      // day column elements, index = day in week.days
    let block = null;      // the draggable selection element
    let loadToken = 0;

    // Dates are handled as UTC midnight so adding days is never affected by DST
    function parseDate(iso) {
        const [y, m, d] = iso.split('-').map(Number);
        return new Date(Date.UTC(y, m - 1, d));
    }
    function isoDate(date) {
        return date.toISOString().slice(0, 10);
    }
    function addDays(iso, days) {
        const date = parseDate(iso);
        date.setUTCDate(date.getUTCDate() + days);
        return isoDate(date);
    }
    function formatDate(iso, options) {
        try {
            return new Intl.DateTimeFormat(config.locale, Object.assign({ timeZone: 'UTC' }, options)).format(parseDate(iso));
        } catch (e) {
            return iso;
        }
    }
    function toMinutes(hhmm) {
        const [h, m] = hhmm.split(':').map(Number);
        return h * 60 + m;
    }
    function toHHMM(minutes) {
        return String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0');
    }

    function showAlert(type, text) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = text;
        alertBox.hidden = false;
    }

    function showSlotMessage(key, className) {
        const span = document.createElement('span');
        span.className = className || 'muted';
        span.textContent = t(key);
        slotsContainer.replaceChildren(span);
    }

    function resetSelection() {
        chosen = null;
        block = null;
        allSlots = [];
        updateSelectionBar();
    }

    function loadServices() {
        api({ action: 'get_services' }).then((data) => {
            const services = Object.entries((data && data.data) || {});
            const placeholder = new Option(t(services.length ? 'booking.service.choose' : 'booking.service.noneFound'), '');
            serviceSelect.replaceChildren(placeholder);
            services.forEach(([id, service]) => {
                serviceSelect.add(new Option(t('booking.service.option', { name: service.name, duration: service.duration }), id));
            });
        }).catch(() => showAlert('error', t('booking.error.network')));
    }

    /** Loads the week containing the date input's date. Keeps the current choice if it is still free. */
    function loadWeek() {
        const keep = chosen ? { date: chosen.day.date, time: chosen.slot.start_time } : null;
        resetSelection();
        if (!serviceSelect.value || !dateInput.value) {
            week = null;
            updateWeekNav();
            showSlotMessage('booking.slots.selectFirst');
            return;
        }

        const token = ++loadToken;
        showSlotMessage('booking.slots.loading');
        api({
            action: 'check_week',
            service_id: serviceSelect.value,
            date: dateInput.value,
            country: config.country,
        }).then((data) => {
            if (token !== loadToken) {
                return; // a newer request has been made meanwhile
            }
            if (!data.success) {
                week = null;
                updateWeekNav();
                const span = document.createElement('span');
                span.className = 'error-text';
                span.textContent = errorText(data);
                slotsContainer.replaceChildren(span);
                return;
            }
            week = data;
            renderWeek(keep);
        }).catch(() => showAlert('error', t('booking.error.network')));
    }

    function updateWeekNav() {
        if (!week) {
            weekLabel.textContent = '';
            prevWeek.disabled = nextWeek.disabled = true;
            return;
        }
        const first = week.week_start;
        const last = addDays(first, 6);
        weekLabel.textContent = formatDate(first, { day: 'numeric', month: 'short' }) + ' – ' + formatDate(last, { day: 'numeric', month: 'short', year: 'numeric' });
        prevWeek.disabled = first <= dateInput.min;
        nextWeek.disabled = false;
    }

    function renderWeek(keep) {
        updateWeekNav();
        const openDays = week.days.filter((day) => day.open && day.close);
        if (!openDays.length) {
            showSlotMessage('booking.week.closedAll');
            return;
        }

        // Shared axis from the earliest opening to the latest closing, on whole hours
        range = {
            start: Math.floor(Math.min(...openDays.map((d) => toMinutes(d.open))) / 60) * 60,
            end: Math.ceil(Math.max(...openDays.map((d) => toMinutes(d.close))) / 60) * 60,
        };
        const height = (range.end - range.start) * PX_PER_MIN;
        const y = (minute) => (minute - range.start) * PX_PER_MIN + 'px';

        const grid = document.createElement('div');
        grid.className = 'week-grid';
        grid.style.setProperty('--hour-height', 60 * PX_PER_MIN + 'px');
        // Closed days get a narrow column so open days keep room on small screens
        grid.style.gridTemplateColumns = 'var(--axis-width) ' + week.days
            .map((day) => (day.open && day.close ? 'minmax(30px, 1fr)' : 'minmax(16px, 0.35fr)')).join(' ');

        grid.appendChild(document.createElement('div')); // corner above the time axis
        week.days.forEach((day) => {
            const head = document.createElement('div');
            head.className = 'day-head';
            head.textContent = formatDate(day.date, { weekday: 'short' });
            const num = document.createElement('small');
            num.textContent = formatDate(day.date, { day: 'numeric', month: 'numeric' });
            head.appendChild(num);
            grid.appendChild(head);
        });

        const axis = document.createElement('div');
        axis.className = 'time-axis';
        axis.style.height = height + 'px';
        for (let m = range.start; m <= range.end; m += 60) {
            const label = document.createElement('span');
            label.style.top = y(m);
            label.textContent = toHHMM(m);
            axis.appendChild(label);
        }
        grid.appendChild(axis);

        allSlots = [];
        columns = week.days.map((day, index) => {
            const col = document.createElement('div');
            col.className = 'day-col';
            col.style.height = height + 'px';
            col.dataset.index = index;

            if (!day.open || !day.close) {
                col.classList.add('closed');
                const label = document.createElement('span');
                label.className = 'closed-label';
                label.textContent = t('booking.slots.closed');
                col.appendChild(label);
                grid.appendChild(col);
                return col;
            }

            const addArea = (className, from, to) => {
                const area = document.createElement('div');
                area.className = className;
                area.style.top = y(from);
                area.style.height = (to - from) * PX_PER_MIN + 'px';
                col.appendChild(area);
            };

            addArea('open-area', toMinutes(day.open), toMinutes(day.close));

            // Bookable areas: runs of consecutive free starts, drawn until the last start's treatment ends
            let runStart = null;
            let previous = null;
            day.slots.forEach((slot) => {
                const minute = toMinutes(slot.start_time);
                allSlots.push({ day, dayIndex: index, slot, minute });
                if (previous === null || minute - previous > week.step) {
                    if (runStart !== null) {
                        addArea('free-area', runStart, previous + week.duration);
                    }
                    runStart = minute;
                }
                previous = minute;
            });
            if (runStart !== null) {
                addArea('free-area', runStart, previous + week.duration);
            }

            day.busy.forEach((busy) => addArea('busy-area busy-' + busy.kind, toMinutes(busy.start), toMinutes(busy.end)));

            col.addEventListener('click', (event) => {
                if (block && block.contains(event.target)) {
                    return;
                }
                // Center the treatment on the tapped point, then snap to the nearest free start
                const minute = range.start + (event.clientY - col.getBoundingClientRect().top) / PX_PER_MIN - week.duration / 2;
                select(nearestStart(index, minute));
            });
            grid.appendChild(col);
            return col;
        });

        slotsContainer.replaceChildren(grid);

        if (!allSlots.length) {
            const note = document.createElement('p');
            note.className = 'muted';
            note.textContent = t('booking.week.noneFree');
            slotsContainer.appendChild(note);
        }

        const kept = keep && allSlots.find((s) => s.day.date === keep.date && s.slot.start_time === keep.time);
        select(kept || null);
    }

    function nearestStart(dayIndex, minute) {
        let best = null;
        allSlots.forEach((entry) => {
            if (entry.dayIndex === dayIndex && (!best || Math.abs(entry.minute - minute) < Math.abs(best.minute - minute))) {
                best = entry;
            }
        });
        return best;
    }

    /** Moves the selection block to $entry (or removes it when null). */
    function select(entry) {
        if (!entry) {
            if (block) {
                block.remove();
                block = null;
            }
            chosen = null;
            updateSelectionBar();
            return;
        }
        if (!block) {
            block = createBlock();
        }
        chosen = entry;
        columns[entry.dayIndex].appendChild(block);
        block.style.top = (entry.minute - range.start) * PX_PER_MIN + 'px';
        block.style.height = week.duration * PX_PER_MIN + 'px';
        block.textContent = entry.slot.start_time + ' – ' + entry.slot.end_time;
        updateSelectionBar();
    }

    function createBlock() {
        const el = document.createElement('div');
        el.className = 'selection-block';
        el.tabIndex = 0;
        el.setAttribute('role', 'slider');
        el.setAttribute('aria-label', t('booking.timeline.label'));

        let grabOffset = 0;
        el.addEventListener('pointerdown', (event) => {
            grabOffset = event.clientY - el.getBoundingClientRect().top;
            el.setPointerCapture(event.pointerId);
            el.classList.add('dragging');
            event.preventDefault();
        });
        el.addEventListener('pointermove', (event) => {
            if (!el.hasPointerCapture(event.pointerId)) {
                return;
            }
            // The column under the pointer decides the day, so the block can be dragged across days
            const col = columns.find((c) => {
                const rect = c.getBoundingClientRect();
                return !c.classList.contains('closed') && event.clientX >= rect.left && event.clientX < rect.right;
            });
            const dayIndex = col ? Number(col.dataset.index) : chosen.dayIndex;
            const top = columns[dayIndex].getBoundingClientRect().top;
            const entry = nearestStart(dayIndex, range.start + (event.clientY - grabOffset - top) / PX_PER_MIN);
            if (entry && entry !== chosen) {
                select(entry);
            }
        });
        const stopDrag = (event) => {
            if (el.hasPointerCapture(event.pointerId)) {
                el.releasePointerCapture(event.pointerId);
            }
            el.classList.remove('dragging');
        };
        el.addEventListener('pointerup', stopDrag);
        el.addEventListener('pointercancel', stopDrag);

        el.addEventListener('keydown', (event) => {
            const index = allSlots.indexOf(chosen);
            let entry = null;
            switch (event.key) {
                case 'ArrowUp': entry = allSlots[index - 1]; break;
                case 'ArrowDown': entry = allSlots[index + 1]; break;
                case 'ArrowLeft': entry = nearestOnOtherDay(-1); break;
                case 'ArrowRight': entry = nearestOnOtherDay(1); break;
                case 'Home': entry = allSlots[0]; break;
                case 'End': entry = allSlots[allSlots.length - 1]; break;
                default: return;
            }
            event.preventDefault();
            if (entry) {
                select(entry);
                el.focus();
            }
        });
        return el;
    }

    /** Same time of day on the nearest earlier/later day that has free starts. */
    function nearestOnOtherDay(direction) {
        for (let i = chosen.dayIndex + direction; i >= 0 && i < week.days.length; i += direction) {
            const entry = nearestStart(i, chosen.minute);
            if (entry) {
                return entry;
            }
        }
        return null;
    }

    function updateSelectionBar() {
        const index = chosen ? allSlots.indexOf(chosen) : -1;
        earlierBtn.disabled = index <= 0;
        laterBtn.disabled = index < 0 || index >= allSlots.length - 1;
        selectionText.textContent = chosen
            ? t('booking.timeline.selected', {
                day: formatDate(chosen.day.date, { weekday: 'long', day: 'numeric', month: 'long' }),
                start: chosen.slot.start_time,
                end: chosen.slot.end_time,
            })
            : '';
        if (block && chosen) {
            block.setAttribute('aria-valuetext', selectionText.textContent);
        }
    }

    function stepSelection(offset) {
        const entry = allSlots[allSlots.indexOf(chosen) + offset];
        if (entry) {
            select(entry);
        }
    }

    function shiftWeek(days) {
        if (!week) {
            return;
        }
        const target = addDays(week.week_start, days);
        dateInput.value = target < dateInput.min ? dateInput.min : target;
        loadWeek();
    }

    function submitBooking(event) {
        event.preventDefault();
        if (!chosen) {
            showAlert('error', t('booking.error.noSlot'));
            return;
        }
        if (!bookingForm.checkValidity()) {
            bookingForm.reportValidity();
            return;
        }

        api({ action: 'book' }, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                service_id: serviceSelect.value,
                date: chosen.day.date,
                time: chosen.slot.start_time,
                customer_name: document.getElementById('customerName').value,
                customer_email: document.getElementById('customerEmail').value,
                website: document.getElementById('contactWebsite').value,
                form_token: config.formToken,
            }),
        }).then((data) => {
            if (data.success) {
                showAlert('success', t('booking.result.requestReceived'));
                bookingForm.reset();
                loadWeek();
            } else {
                showAlert('error', errorText(data));
                if (data.code === 'slot_unavailable') {
                    loadWeek();
                }
            }
        }).catch(() => showAlert('error', t('booking.error.network')));
    }

    serviceSelect.addEventListener('change', loadWeek);
    dateInput.addEventListener('change', loadWeek);
    prevWeek.addEventListener('click', () => shiftWeek(-7));
    nextWeek.addEventListener('click', () => shiftWeek(7));
    earlierBtn.addEventListener('click', () => stepSelection(-1));
    laterBtn.addEventListener('click', () => stepSelection(1));
    bookingForm.addEventListener('submit', submitBooking);
    document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

    updateWeekNav();
    loadServices();
})();
