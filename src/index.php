<?php
/**
 * index.php
 * Main client-facing booking interface supporting dynamic localization and Closed vs Fully Booked states.
 */
$countryCode = $_GET['country'] ?? 'se';$langCode = $_GET['lang'] ?? 'sv';$defaultDaysRange = 7;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($langCode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking System</title>
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #cbd5e1;
            --slot-bg: #f0f9ff;
            --slot-border: #bae6fd;
            --slot-hover: #0284c7;
            --slot-hover-text: #ffffff;
            --slot-past-bg: #f1f5f9;
            --slot-past-border: #e2e8f0;
            --slot-past-text: #94a3b8;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 750px;
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        h1 { font-size: 1.5rem; margin-top: 0; color: var(--primary-dark); }
        .step { margin-bottom: 1.5rem; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        .day-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.05);
        }
        .day-header {
            font-weight: bold;
            color: var(--primary-dark);
            margin-bottom: 10px;
            font-size: 1.05rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 6px;
            text-transform: capitalize;
        }
        .day-badge {
            font-size: 0.8rem;
            background: #e0f2fe;
            color: var(--primary-dark);
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: normal;
        }
        .day-badge.closed {
            background: #f1f5f9;
            color: #64748b;
        }
        .day-badge.booked {
            background: #ffeeef;
            color: #dc2626;
        }
        
        .time-category {
            margin-bottom: 8px;
        }
        .time-category:last-child {
            margin-bottom: 0;
        }
        .category-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: bold;
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
            gap: 6px;
        }
        .slot-pill {
            background: var(--slot-bg);
            border: 1px solid var(--slot-border);
            color: var(--primary-dark);
            padding: 6px 4px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            text-align: center;
            transition: all 0.15s ease;
        }
        .slot-pill:hover {
            background: var(--slot-hover);
            color: var(--slot-hover-text);
            border-color: var(--slot-hover);
        }
        .slot-pill.selected {
            background: var(--primary-dark);
            color: white;
            border-color: var(--primary-dark);
            box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.3);
        }
        
        .slot-pill.past {
            background: var(--slot-past-bg);
            border-color: var(--slot-past-border);
            color: var(--slot-past-text);
            cursor: not-allowed;
            opacity: 0.5;
            text-decoration: line-through;
        }
        .slot-pill.past:hover {
            background: var(--slot-past-bg);
            color: var(--slot-past-text);
            border-color: var(--slot-past-border);
        }

        .status-msg {
            color: #64748b;
            font-size: 0.9rem;
            font-style: italic;
            padding: 6px 0;
        }
        
        button.submit-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        button.submit-btn:hover { background: var(--primary-dark); }
        .hidden { display: none; }
    </style>
</head>
<body>

<div class="container">
    <h1 id="uiTitle">Booking</h1>
    
    <form id="bookingForm" onsubmit="submitBooking(event)">
        <div class="step">
            <label for="serviceSelect" id="lblService">Select Service:</label>
            <select id="serviceSelect" required onchange="loadAvailabilityRange()">
                <option value="">Loading...</option>
            </select>
        </div>

        <div class="step">
            <label id="lblTimeline">Available Time Slots:</label>
            <div id="rangeContainer">
                <p class="status-msg">Loading availability...</p>
            </div>
            <input type="hidden" id="selectedDate" required>
            <input type="hidden" id="selectedTime" required>
        </div>

        <div id="customerDetails" class="hidden">
            <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">
            <h3 id="lblCustomerDetails" style="font-size: 1.1rem; color: var(--primary-dark);">Your Details</h3>
            <div class="step">
                <label for="name" id="lblName">Name:</label>
                <input type="text" id="name" required>
            </div>
            <div class="step">
                <label for="email" id="lblEmail">Email:</label>
                <input type="email" id="email" required>
            </div>
            <div class="step">
                <label for="phone" id="lblPhone">Phone:</label>
                <input type="tel" id="phone" required>
            </div>
            <button type="submit" id="btnSubmit" class="submit-btn">Confirm Booking</button>
        </div>
    </form>
    
    <div id="confirmationMsg" class="hidden" style="text-align:center; padding: 2rem;">
        <h2 id="successTitle" style="color: #16a34a;">Thank you for your booking!</h2>
        <p id="successMessage">A confirmation has been sent to your email.</p>
    </div>
</div>

<script>
    const API_URL = 'api.php';
    const COUNTRY_CODE = '<?php echo $countryCode; ?>';
    const LANG_CODE = '<?php echo $langCode; ?>';
    const DAYS_RANGE = <?php echo $defaultDaysRange; ?>;
    
    let adminSettings = { show_past_slots: true };
    let i18n = {};

    document.addEventListener('DOMContentLoaded', () => {
        loadServices();
    });

    async function loadServices() {
        try {
            const res = await fetch(`${API_URL}?action=get_services&country=${COUNTRY_CODE}&lang=${LANG_CODE}`);
            if (!res.ok) throw new Error('Failed to fetch services.');
            const responseData = await res.json();
            
            const services = responseData.services || responseData;
            if (responseData.settings) {
                adminSettings = responseData.settings;
            }
            if (responseData.locale) {
                i18n = responseData.locale;
                applyLocalization();
            }
            
            const select = document.getElementById('serviceSelect');
            select.innerHTML = '';
            
            if (!Array.isArray(services)) {
                throw new Error('Invalid services catalog format.');
            }
            
            services.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id || s.service_id;
                opt.textContent = `${s.name} (${s.duration || 45} min - ${s.price || 0} ${s.currency || 'SEK'})`;
                select.appendChild(opt);
            });

            if (services.length > 0) {
                loadAvailabilityRange();
            } else {
                document.getElementById('rangeContainer').innerHTML = `<p style="color: #dc2626;">${i18n.no_services_found || 'No active services found.'}</p>`;
            }
        } catch (e) {
            console.error(e);
            document.getElementById('serviceSelect').innerHTML = '<option value="">Error loading services</option>';
            document.getElementById('rangeContainer').innerHTML = '<p style="color: #dc2626;">Failed to load catalog.</p>';
        }
    }

    function applyLocalization() {
        document.getElementById('lblService').textContent = i18n.select_service || 'Select Service:';
        document.getElementById('lblTimeline').textContent = i18n.timeline_title || 'Available Time Slots:';
        document.getElementById('lblCustomerDetails').textContent = i18n.customer_details || 'Your Details';
        document.getElementById('lblName').textContent = i18n.name || 'Name:';
        document.getElementById('lblEmail').textContent = i18n.email || 'Email:';
        document.getElementById('lblPhone').textContent = i18n.phone || 'Phone:';
        document.getElementById('btnSubmit').textContent = i18n.confirm_booking || 'Confirm Booking';
        document.getElementById('successTitle').textContent = i18n.success_title || 'Thank you for your booking!';
        document.getElementById('successMessage').textContent = i18n.success_message || 'A confirmation has been sent to your email.';
    }

    async function loadAvailabilityRange() {
        const serviceId = document.getElementById('serviceSelect').value;
        if (!serviceId) return;

        const now = new Date();
        const todayStr = now.toISOString().split('T')[0];
        const container = document.getElementById('rangeContainer');
        container.innerHTML = `<p class="status-msg">${i18n.loading_timeline || 'Loading availability...'}</p>`;

        try {
            const res = await fetch(`${API_URL}?action=check_availability&date=${todayStr}&days=${DAYS_RANGE}&service_id=${serviceId}&country=${COUNTRY_CODE}&lang=${LANG_CODE}`);
            if (!res.ok) throw new Error('Failed to fetch availability.');
            
            const data = await res.json();
            container.innerHTML = '';
            const range = data.range;

            let hasVisibleSlotsAnywhere = false;
            const currentTimeMinutes = now.getHours() * 60 + now.getMinutes();

            for (const [date, info] of Object.entries(range)) {
                const isToday = (date === todayStr);
                
                let slotsToDisplay = info.available_slots;
                if (isToday && !adminSettings.show_past_slots) {
                    slotsToDisplay = slotsToDisplay.filter(time => {
                        const [hours, minutes] = time.split(':').map(Number);
                        return (hours * 60 + minutes) >= currentTimeMinutes;
                    });
                }

                const dayCard = document.createElement('div');
                dayCard.className = 'day-card';
                
                const header = document.createElement('div');
                header.className = 'day-header';
                
                const titleSpan = document.createElement('span');
                titleSpan.textContent = formatDateLabel(date);
                header.appendChild(titleSpan);

                const badge = document.createElement('span');
                badge.className = 'day-badge';
                
                if (info.is_closed) {
                    badge.className = 'day-badge closed';
                    badge.textContent = i18n.closed || 'Closed';
                } else if (slotsToDisplay.length === 0) {
                    badge.className = 'day-badge booked';
                    badge.textContent = i18n.fully_booked || 'Fully Booked';
                } else {
                    badge.textContent = `${slotsToDisplay.length} ${i18n.free_slots || 'available slots'}`;
                }
                header.appendChild(badge);
                dayCard.appendChild(header);

                if (info.is_closed) {
                    const closedMsg = document.createElement('div');
                    closedMsg.className = 'status-msg';
                    closedMsg.textContent = i18n.closed || 'Closed';
                    dayCard.appendChild(closedMsg);
                } else if (slotsToDisplay.length === 0) {
                    const bookedMsg = document.createElement('div');
                    bookedMsg.className = 'status-msg';
                    bookedMsg.textContent = i18n.fully_booked || 'Fully Booked';
                    dayCard.appendChild(bookedMsg);
                } else {
                    hasVisibleSlotsAnywhere = true;

                    const morningSlots = [];
                    const afternoonSlots = [];

                    slotsToDisplay.forEach(time => {
                        const [hours] = time.split(':').map(Number);
                        if (hours < 12) {
                            morningSlots.push(time);
                        } else {
                            afternoonSlots.push(time);
                        }
                    });

                    if (morningSlots.length > 0) {
                        const catDiv = document.createElement('div');
                        catDiv.className = 'time-category';
                        
                        const catLabel = document.createElement('div');
                        catLabel.className = 'category-label';
                        catLabel.textContent = i18n.morning || 'Morning';
                        catDiv.appendChild(catLabel);

                        const grid = document.createElement('div');
                        grid.className = 'slots-grid';

                        morningSlots.forEach(time => {
                            const pill = createSlotPill(date, time, isToday, currentTimeMinutes);
                            grid.appendChild(pill);
                        });

                        catDiv.appendChild(grid);
                        dayCard.appendChild(catDiv);
                    }

                    if (afternoonSlots.length > 0) {
                        const catDiv = document.createElement('div');
                        catDiv.className = 'time-category';
                        if (morningSlots.length > 0) catDiv.style.marginTop = '10px';
                        
                        const catLabel = document.createElement('div');
                        catLabel.className = 'category-label';
                        catLabel.textContent = i18n.afternoon || 'Afternoon';
                        catDiv.appendChild(catLabel);

                        const grid = document.createElement('div');
                        grid.className = 'slots-grid';

                        afternoonSlots.forEach(time => {
                            const pill = createSlotPill(date, time, isToday, currentTimeMinutes);
                            grid.appendChild(pill);
                        });

                        catDiv.appendChild(grid);
                        dayCard.appendChild(catDiv);
                    }
                }

                container.appendChild(dayCard);
            }

            if (!hasVisibleSlotsAnywhere) {
                container.innerHTML = `<p style="color: #dc2626; text-align: center; padding: 1rem;">${i18n.no_slots_found || 'No available slots found for the coming week.'}</p>`;
            }
        } catch (e) {
            console.error(e);
            container.innerHTML = '<p style="color: #dc2626;">Error loading availability.</p>';
        }
    }

    function createSlotPill(date, time, isToday, currentTimeMinutes) {
        const pill = document.createElement('div');
        pill.className = 'slot-pill';
        pill.textContent = time;

        let isPast = false;
        if (isToday) {
            const [hours, minutes] = time.split(':').map(Number);
            if ((hours * 60 + minutes) < currentTimeMinutes) {
                isPast = true;
            }
        }

        if (isPast) {
            pill.classList.add('past');
            if (!adminSettings.show_past_slots) {
                pill.style.display = 'none';
            }
        } else {
            pill.onclick = () => selectSlot(date, time, pill);
        }

        return pill;
    }

    function formatDateLabel(dateStr) {
        const options = { weekday: 'long', month: 'short', day: 'numeric' };
        const date = new Date(dateStr);
        return date.toLocaleDateString(COUNTRY_CODE === 'se' ? 'sv-SE' : undefined, options);
    }

    function selectSlot(date, time, element) {
        if (element.classList.contains('past')) return;
        
        document.querySelectorAll('.slot-pill').forEach(b => b.classList.remove('selected'));
        element.classList.add('selected');
        
        document.getElementById('selectedDate').value = date;
        document.getElementById('selectedTime').value = time;
        document.getElementById('customerDetails').classList.remove('hidden');
    }

    async function submitBooking(e) {
        e.preventDefault();
        const payload = {
            service_id: document.getElementById('serviceSelect').value,
            date: document.getElementById('selectedDate').value,
            time: document.getElementById('selectedTime').value,
            customer_name: document.getElementById('name').value,
            customer_email: document.getElementById('email').value,
            customer_phone: document.getElementById('phone').value,
            country: COUNTRY_CODE
        };

        try {
            const res = await fetch(`${API_URL}?action=book`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('bookingForm').classList.add('hidden');
                document.getElementById('confirmationMsg').classList.remove('hidden');
            } else {
                alert((i18n.booking_failed || 'Booking failed: ') + (data.error || 'Unknown error'));
            }
        } catch (e) {
            alert('A network error occurred.');
        }
    }
</script>

</body>
</html>