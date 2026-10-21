/**
 * src/js/booking.js
 * Public booking form. All visible texts come from the page's #booking-config JSON
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
    let chosenTime = null;

    function showAlert(type, text) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = text;
        alertBox.hidden = false;
    }

    function showSlotMessage(key) {
        const span = document.createElement('span');
        span.className = 'muted';
        span.textContent = t(key);
        slotsContainer.replaceChildren(span);
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

    function loadSlots() {
        chosenTime = null;
        if (!serviceSelect.value || !dateInput.value) {
            showSlotMessage('booking.slots.selectFirst');
            return;
        }

        showSlotMessage('booking.slots.loading');
        api({
            action: 'check_availability',
            service_id: serviceSelect.value,
            date: dateInput.value,
            country: config.country,
        }).then((data) => {
            if (!data.success) {
                const span = document.createElement('span');
                span.className = 'error-text';
                span.textContent = errorText(data);
                slotsContainer.replaceChildren(span);
                return;
            }
            if (!data.slots.length) {
                showSlotMessage('booking.slots.noneForDate');
                return;
            }

            slotsContainer.replaceChildren(...data.slots.map((slot) => {
                const pill = document.createElement('button');
                pill.type = 'button';
                pill.className = 'slot-pill';
                pill.textContent = slot.start_time + ' - ' + slot.end_time;
                pill.addEventListener('click', () => {
                    slotsContainer.querySelectorAll('.slot-pill').forEach((p) => p.classList.remove('selected'));
                    pill.classList.add('selected');
                    chosenTime = slot.start_time;
                });
                return pill;
            }));
        }).catch(() => showAlert('error', t('booking.error.network')));
    }

    function submitBooking(event) {
        event.preventDefault();
        if (!chosenTime) {
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
                date: dateInput.value,
                time: chosenTime,
                customer_name: document.getElementById('customerName').value,
                customer_email: document.getElementById('customerEmail').value,
            }),
        }).then((data) => {
            if (data.success) {
                showAlert('success', t('booking.result.requestReceived'));
                bookingForm.reset();
                showSlotMessage('booking.slots.selectFirst');
                chosenTime = null;
            } else {
                showAlert('error', errorText(data));
                if (data.code === 'slot_unavailable') {
                    loadSlots();
                }
            }
        }).catch(() => showAlert('error', t('booking.error.network')));
    }

    serviceSelect.addEventListener('change', loadSlots);
    dateInput.addEventListener('change', loadSlots);
    bookingForm.addEventListener('submit', submitBooking);
    document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

    loadServices();
})();
