<?php
/**
 * index.php
 * Main client-facing booking interface with timeline block view and past slot handling.
 */
$countryCode = 'se';$defaultDaysRange = 7;
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boka Tid - Medicinsk Fotvård</title>
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #cbd5e1;
            --slot-bg: #e0f2fe;
            --slot-hover: #bae6fd;
            --slot-past-bg: #f1f5f9;
            --slot-past-border: #94a3b8;
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
            max-width: 800px;
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
        
        .timeline-day {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }
        .timeline-header {
            font-weight: bold;
            color: var(--primary-dark);
            margin-bottom: 10px;
            text-transform: capitalize;
            font-size: 1rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
        }
        .timeline-track {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            align-items: center;
        }
        .slot-block {
            background: var(--slot-bg);
            border: 1px solid var(--primary);
            color: var(--primary-dark);
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        .slot-block:hover {
            background: var(--slot-hover);
            transform: translateY(-1px);
        }
        .slot-block.selected {
            background: var(--primary-dark);
            color: white;
            border-color: var(--primary-dark);
        }
        
        /* Past / Expired Slots Styling */
        .slot-block.past {
            background: var(--slot-past-bg);
            border-color: var(--slot-past-border);
            color: var(--slot-past-text);
            cursor: not-allowed;
            opacity: 0.6;
            text-decoration: line-through;
        }
        .slot-block.past:hover {
            background: var(--slot-past-bg);
            transform: none;
        }

        .status-msg {
            color: #64748b;
            font-size: 0.9rem;
            font-style: italic;
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
    <h1>Boka behandling</h1>
    
    <form id="bookingForm" onsubmit="submitBooking(event)">
        <div class="step">
            <label for="serviceSelect">Välj tjänst:</label>
            <select id="serviceSelect" required onchange="loadAvailabilityRange()">
                <option value="">Laddar tjänster...</option>
            </select>
        </div>

        <div class="step">
            <label>Tidslinje över tillgängliga tider:</label>
            <div id="rangeContainer">
                <p class="status-msg">Laddar tidslinje...</p>
            </div>
            <input type="hidden" id="selectedDate" required>
            <input type="hidden" id="selectedTime" required>
        </div>

        <div id="customerDetails" class="hidden">
            <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">
            <h3 style="font-size: 1.1rem; color: var(--primary-dark);">Dina uppgifter</h3>
            <div class="step">
                <label for="name">Namn:</label>
                <input type="text" id="name" required>
            </div>
            <div class="step">
                <label for="email">E-post:</label>
                <input type="email" id="email" required>
            </div>
            <div class="step">
                <label for="phone">Telefonnummer:</label>
                <input type="tel" id="phone" required>
            </div>
            <button type="submit" class="submit-btn">Bekräfta bokning</button>
        </div>
    </form>
    
    <div id="confirmationMsg" class="hidden" style="text-align:center; padding: 2rem;">
        <h2 style="color: #16a34a;">Tack för din bokning!</h2>
        <p>En bekräftelse har skickats till din e-post.</p>
    </div>
</div>

<script>
    const API_URL = 'api.php';
    const COUNTRY_CODE = '<?php echo $countryCode; ?>';
    const DAYS_RANGE = <?php echo $defaultDaysRange; ?>;
    
    let adminSettings = { show_past_slots: true };

    document.addEventListener('DOMContentLoaded', () => {
        loadServices();
    });

    async function loadServices() {
        try {
            const res = await fetch(`${API_URL}?action=get_services&country=${COUNTRY_CODE}`);
            if (!res.ok) throw new Error('Kunde inte hämta tjänster.');
            const responseData = await res.json();
            
            // Extract services and settings from unified endpoint response
            const services = responseData.services || responseData;
            if (responseData.settings) {
                adminSettings = responseData.settings;
            }
            
            const select = document.getElementById('serviceSelect');
            select.innerHTML = '';
            
            if (!Array.isArray(services)) {
                throw new Error('Ogiltigt format på tjänstekatalogen.');
            }
            
            services.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id || s.service_id;
                opt.textContent = `${s.name} (${s.duration_minutes || 45} min - ${s.price || 0} ${s.currency || 'SEK'})`;
                select.appendChild(opt);
            });

            if (services.length > 0) {
                loadAvailabilityRange();
            } else {
                document.getElementById('rangeContainer').innerHTML = '<p style="color: #dc2626;">Inga aktiva tjänster hittades.</p>';
            }
        } catch (e) {
            console.error(e);
            document.getElementById('serviceSelect').innerHTML = '<option value="">Kunde inte ladda tjänster</option>';
            document.getElementById('rangeContainer').innerHTML = '<p style="color: #dc2626;">Tjänstekatalogen kunde inte laddas.</p>';
        }
    }

    async function loadAvailabilityRange() {
        const serviceId = document.getElementById('serviceSelect').value;
        if (!serviceId) return;

        const now = new Date();
        const todayStr = now.toISOString().split('T')[0];
        const container = document.getElementById('rangeContainer');
        container.innerHTML = '<p class="status-msg">Laddar tidslinje...</p>';

        try {
            const res = await fetch(`${API_URL}?action=check_availability&date=${todayStr}&days=${DAYS_RANGE}&service_id=${serviceId}&country=${COUNTRY_CODE}`);
            if (!res.ok) throw new Error('Kunde inte hämta tillgänglighet.');
            
            const data = await res.json();
            container.innerHTML = '';
            const range = data.range;

            let hasVisibleSlotsAnywhere = false;
            const currentTimeMinutes = now.getHours() * 60 + now.getMinutes();

            for (const [date, info] of Object.entries(range)) {
                const isToday = (date === todayStr);
                
                // Filter slots if admin configured to hide past slots entirely on today
                let slotsToDisplay = info.available_slots;
                if (isToday && !adminSettings.show_past_slots) {
                    slotsToDisplay = slotsToDisplay.filter(time => {
                        const [hours, minutes] = time.split(':').map(Number);
                        return (hours * 60 + minutes) >= currentTimeMinutes;
                    });
                }

                const dayRow = document.createElement('div');
                dayRow.className = 'timeline-day';
                
                const header = document.createElement('div');
                header.className = 'timeline-header';
                header.textContent = formatDateLabel(date);
                dayRow.appendChild(header);

                const track = document.createElement('div');
                track.className = 'timeline-track';

                if (info.is_closed || slotsToDisplay.length === 0) {
                    const closedMsg = document.createElement('span');
                    closedMsg.className = 'status-msg';
                    closedMsg.textContent = 'Stängt / Fullbokat';
                    track.appendChild(closedMsg);
                } else {
                    hasVisibleSlotsAnywhere = true;
                    slotsToDisplay.forEach(time => {
                        const block = document.createElement('div');
                        block.className = 'slot-block';
                        block.textContent = time;

                        // Check if this slot is in the past on today
                        let isPast = false;
                        if (isToday) {
                            const [hours, minutes] = time.split(':').map(Number);
                            if ((hours * 60 + minutes) < currentTimeMinutes) {
                                isPast = true;
                            }
                        }

                        if (isPast) {
                            block.classList.add('past');
                            if (!adminSettings.show_past_slots) {
                                return; // Skip entirely if hidden via config
                            }
                        } else {
                            block.onclick = () => selectSlot(date, time, block);
                        }

                        track.appendChild(block);
                    });
                }

                dayRow.appendChild(track);
                container.appendChild(dayRow);
            }

            if (!hasVisibleSlotsAnywhere) {
                container.innerHTML = '<p style="color: #dc2626;">Inga lediga tider tillgängliga.</p>';
            }
        } catch (e) {
            console.error(e);
            container.innerHTML = '<p style="color: #dc2626;">Kunde inte hämta tillgänglighet.</p>';
        }
    }

    function formatDateLabel(dateStr) {
        const options = { weekday: 'long', month: 'short', day: 'numeric' };
        const date = new Date(dateStr);
        return date.toLocaleDateString('sv-SE', options);
    }

    function selectSlot(date, time, element) {
        if (element.classList.contains('past')) return;
        
        document.querySelectorAll('.slot-block').forEach(b => b.classList.remove('selected'));
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
                alert('Bokningen misslyckades: ' + (data.error || 'Okänt fel'));
            }
        } catch (e) {
            alert('Ett nätverksfel uppstod.');
        }
    }
</script>

</body>
</html>