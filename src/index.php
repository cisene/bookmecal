<?php
/**
 * src/index.php
 * Public client-facing booking portal with dynamic service and slot discovery.
 */
require_once __DIR__ . '/classes/ResourceProvider.php';
ResourceProvider::initTimezone();
$settings = ResourceProvider::getSettings();
$defaultCountry =$settings['default_country'] ?? 'se';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment</title>
    <style>
        :root { --primary: #0284c7; --primary-dark: #0369a1; --bg: #f8fafc; --card-bg: #ffffff; --text: #1e293b; --border: #cbd5e1; }
        body { font-family: system-ui, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 40px 20px; }
        .container { max-width: 600px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { color: var(--primary-dark); margin-top: 0; font-size: 1.75rem; text-align: center; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        select, input { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        .btn { background: var(--primary); color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; font-weight: bold; font-size: 1rem; cursor: pointer; }
        .btn:hover { background: var(--primary-dark); }
        .slot-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .slot-pill { padding: 8px 14px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; border-radius: 6px; cursor: pointer; font-family: monospace; font-size: 0.95rem; }
        .slot-pill.selected { background: var(--primary); color: white; border-color: var(--primary-dark); }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; display: none; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<div class="container">
    <h1>Book an Appointment</h1>
    
    <div id="alertBox" class="alert"></div>

    <form id="bookingForm">
        <div class="form-group">
            <label for="serviceSelect">Select Service</label>
            <select id="serviceSelect" required>
                <option value="">-- Choose a service --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="dateInput">Date (24-Hour Standard)</label>
            <input type="date" id="dateInput" required>
        </div>

        <div class="form-group">
            <label>Available Time Slots (24-Hour HH:mm)</label>
            <div id="slotsContainer" style="color: #64748b; font-style: italic;">Please select a service and date first.</div>
            <input type="hidden" id="selectedTime" required>
        </div>

        <div class="form-group">
            <label for="customerName">Full Name</label>
            <input type="text" id="customerName" required placeholder="John Doe">
        </div>

        <div class="form-group">
            <label for="customerEmail">Email Address</label>
            <input type="email" id="customerEmail" required placeholder="john@example.com">
        </div>

        <button type="submit" class="btn">Confirm Booking</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const serviceSelect = document.getElementById('serviceSelect');
    const dateInput = document.getElementById('dateInput');
    const slotsContainer = document.getElementById('slotsContainer');
    const selectedTimeInput = document.getElementById('selectedTime');
    const bookingForm = document.getElementById('bookingForm');
    const alertBox = document.getElementById('alertBox');

    let selectedServiceId = null;
    let selectedDate = null;
    let chosenTime = null;

    // Fetch services on load
    fetch('api.php?action=get_services')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                for (const [id, srv] of Object.entries(data.data)) {
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = `${srv.name} (${srv.duration_minutes || srv.duration || 'N/A'} mins)`;
                    serviceSelect.appendChild(opt);
                }
            }
        });

    serviceSelect.addEventListener('change', (e) => {
        selectedServiceId = e.target.value;
        checkAvailability();
    });

    dateInput.addEventListener('change', (e) => {
        selectedDate = e.target.value;
        checkAvailability();
    });

    function checkAvailability() {
        if (!selectedServiceId || !selectedDate) return;

        slotsContainer.innerHTML = 'Loading available slots...';
        fetch(`api.php?action=check_availability&service_id=${encodeURIComponent(selectedServiceId)}&date=${encodeURIComponent(selectedDate)}&country=${encodeURIComponent(<?php echo json_encode((string)$defaultCountry, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)}`)
            .then(res => res.json())
            .then(data => {
                slotsContainer.innerHTML = '';
                if (!data.success) {
                    const errSpan = document.createElement('span');
                    errSpan.style.color = 'red';
                    errSpan.textContent = data.error;
                    slotsContainer.appendChild(errSpan);
                    return;
                }

                if (!data.slots || data.slots.length === 0) {
                    slotsContainer.innerHTML = '<span style="color:#64748b;">No available slots for this date (Closed or Holiday).</span>';
                    return;
                }

                data.slots.forEach(slot => {
                    const pill = document.createElement('div');
                    pill.className = 'slot-pill';
                    pill.textContent = `${slot.start_time} - ${slot.end_time}`;
                    pill.addEventListener('click', () => {
                        document.querySelectorAll('.slot-pill').forEach(p => p.classList.remove('selected'));
                        pill.classList.add('selected');
                        chosenTime = slot.start_time;
                        selectedTimeInput.value = chosenTime;
                    });
                    slotsContainer.appendChild(pill);
                });
            });
    }

    bookingForm.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!chosenTime) {
            alert('Please select a time slot.');
            return;
        }

        const payload = {
            service_id: selectedServiceId,
            date: selectedDate,
            time: chosenTime,
            customer_name: document.getElementById('customerName').value,
            customer_email: document.getElementById('customerEmail').value
        };

        fetch('api.php?action=book', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            alertBox.style.display = 'block';
            if (data.success) {
                alertBox.className = 'alert alert-success';
                alertBox.textContent = data.message;
                bookingForm.reset();
                slotsContainer.innerHTML = 'Please select a service and date first.';
                chosenTime = null;
            } else {
                alertBox.className = 'alert alert-error';
                alertBox.textContent = data.error || 'Failed to submit booking.';
            }
        });
    });
});
</script>
</body>
</html>