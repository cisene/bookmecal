<?php
/**
 * src/index.php
 * Public client-facing booking interface featuring dual graphic timelines 
 * (Morning: 09:00-11:30, Afternoon: 13:00-17:00) with 15-minute tick increments.
 */
require_once __DIR__ . '/ResourceProvider.php';

$settings = ResourceProvider::getSettings();
$services = ResourceProvider::getServices();$defaultCountry = 'se';
$locale = ResourceProvider::getLocale($defaultCountry);
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boka Tid — Book Me Calendar</title>
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #cbd5e1;
            --slot-bg: #e0f2fe;
            --slot-border: #bae6fd;
            --slot-hover: #bae6fd;
            --slot-hover-text: #0369a1;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
            margin-bottom: 2rem;
            text-align: center;
        }
        h1 { color: var(--primary-dark); margin-top: 0; font-size: 1.8rem; }
        
        .card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        select, input[type="text"], input[type="email"], input[type="tel"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        /* Dual Timeline Styles */
        .timeline-day-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.03);
        }
        .timeline-date-title {
            font-weight: bold;
            color: var(--primary-dark);
            margin-bottom: 12px;
            font-size: 1.1rem;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        .timeline-section {
            margin-bottom: 12px;
        }
        .timeline-section:last-child {
            margin-bottom: 0;
        }
        .timeline-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .timeline-bar {
            position: relative;
            height: 50px;
            background: #f1f5f9;
            border-radius: 6px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            overflow-x: auto;
            padding: 0 4px;
        }
        .tick-hour {
            position: absolute;
            top: 2px;
            font-size: 9px;
            color: #94a3b8;
            border-left: 1px solid #cbd5e1;
            height: 46px;
            padding-left: 3px;
            pointer-events: none;
        }
        .tick-quarter {
            position: absolute;
            top: 30px;
            font-size: 8px;
            color: #cbd5e1;
            border-left: 1px dashed #e2e8f0;
            height: 18px;
            pointer-events: none;
        }
        .time-slot-pill {
            position: absolute;
            height: 36px;
            background: var(--slot-bg);
            border: 1px solid var(--slot-border);
            color: var(--primary-dark);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            z-index: 2;
            padding: 0 6px;
            white-space: nowrap;
        }
        .time-slot-pill:hover {
            background: var(--slot-hover);
            color: var(--slot-hover-text);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .time-slot-pill.selected {
            background: var(--primary-dark);
            color: white;
            border-color: var(--primary-dark);
            box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.3);
        }
        
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }
        .btn:hover { background: var(--primary-dark); }
        
        .alert-box {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            display: none;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Boka Tid</h1>
        <p>Välj önskad tjänst och klicka på en ledig tid i morgon- eller eftermiddagstidslinjen.</p>
    </div>

    <div id="successAlert" class="alert-box"></div>

    <div class="card">
        <div class="form-group">
            <label for="serviceSelect">Välj tjänst:</label>
            <select id="serviceSelect" onchange="loadAvailability()">
                <option value="">-- Välj en tjänst --</option>
                <?php foreach ($services as $sku =>$srv): ?>
                    <?php if (!empty($srv['is_active'])): ?>
                        <?php 
                        $serviceKey = !empty($srv['sku']) ? $srv['sku'] :$sku;
                        $duration = isset($srv['duration']) ? $srv['duration'] : (isset($srv['duration_minutes']) ? $srv['duration_minutes'] : 30);$cleanup = isset($srv['cleanup']) ?$srv['cleanup'] : 0;
                        $price = isset($srv['price_incl_vat']) ?$srv['price_incl_vat'] : (isset($srv['price']) ?$srv['price'] : 0);
                        ?>
                        <option value="<?php echo htmlspecialchars($serviceKey); ?>">
                            <?php echo htmlspecialchars($srv['name']); ?> (<?php echo (int)$duration; ?> min<?php echo $cleanup > 0 ? ' + ' . (int)$cleanup . ' min städ' : ''; ?>) - <?php echo number_format($price, 2); ?> kr
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="card">
        <h3>Tillgängliga tider kommande vecka</h3>
        <div id="timelineContainer">
            <p style="color: #64748b; font-style: italic;">Vänligen välj en tjänst ovan för att visa tidslinjer.</p>
        </div>
    </div>

    <div id="bookingFormCard" class="card" style="display: none;">
        <h3>Dina uppgifter</h3>
        <p>Vald tid: <strong id="selectedSlotLabel"></strong></p>
        <form id="bookingForm" onsubmit="submitBooking(event)">
            <input type="hidden" id="selectedDate" name="date">
            <input type="hidden" id="selectedTime" name="time">
            <input type="hidden" id="selectedServiceId" name="service_id">

            <div class="form-group">
                <label>Namn:</label>
                <input type="text" id="clientName" name="name" required placeholder="Ditt fullständiga namn">
            </div>
            <div class="form-group">
                <label>E-post:</label>
                <input type="email" id="clientEmail" name="email" required placeholder="din.epost@exempel.se">
            </div>
            <div class="form-group">
                <label>Telefonnummer:</label>
                <input type="tel" id="clientPhone" name="phone" placeholder="070-123 45 67">
            </div>
            <button type="submit" class="btn">Bekräfta bokning</button>
        </form>
    </div>
</div>

<script>
async function loadAvailability() {
    const serviceId = document.getElementById('serviceSelect').value;
    const container = document.getElementById('timelineContainer');
    document.getElementById('bookingFormCard').style.display = 'none';

    if (!serviceId) {
        container.innerHTML = '<p style="color: #64748b; font-style: italic;">Vänligen välj en tjänst ovan för att visa tidslinjer.</p>';
        return;
    }

    container.innerHTML = '<p>Laddar tillgängliga tider...</p>';

    try {
        const response = await fetch(`api.php?action=check_availability&service_id=${encodeURIComponent(serviceId)}&days=7`);
        const data = await response.json();

        if (!data.success) {
            container.innerHTML = `<p style="color: red;">Kunde inte ladda tider: ${data.error || 'Okänt fel'}</p>`;
            return;
        }

        renderTimelines(data.range);
    } catch (err) {
        container.innerHTML = `<p style="color: red;">Ett nätverksfel uppstod vid laddning.</p>`;
    }
}

function renderTimelines(rangeData) {
    const container = document.getElementById('timelineContainer');
    container.innerHTML = '';

    const dates = Object.keys(rangeData);
    if (dates.length === 0) {
        container.innerHTML = '<p>Inga tider tillgängliga.</p>';
        return;
    }

    dates.forEach(dateStr => {
        const dayInfo = rangeData[dateStr];
        const dayCard = document.createElement('div');
        dayCard.className = 'timeline-day-card';

        const dateHeader = document.createElement('div');
        dateHeader.className = 'timeline-date-title';
        dateHeader.innerHTML = `<span>${dateStr}</span> ${dayInfo.is_closed ? '<span style="color:#dc3545; font-size:0.85rem;">Stängt / Heldag</span>' : ''}`;
        dayCard.appendChild(dateHeader);

        if (!dayInfo.is_closed) {
            // Morning Track (09:00 - 11:30)
            dayCard.appendChild(createTimelineTrack('Förmiddag (09:00 – 11:30)', dayInfo.available_slots, '09:00', '11:30', dateStr));
            // Afternoon Track (13:00 - 17:00)
            dayCard.appendChild(createTimelineTrack('Eftermiddag (13:00 – 17:00)', dayInfo.available_slots, '13:00', '17:00', dateStr));
        } else {
            const closedMsg = document.createElement('p');
            closedMsg.style.cssText = 'color: #64748b; font-size: 0.9rem; font-style: italic; margin: 8px 0;';
            closedMsg.textContent = 'Inga öppettider denna dag.';
            dayCard.appendChild(closedMsg);
        }

        container.appendChild(dayCard);
    });
}

function createTimelineTrack(labelTitle, slots, startTimeStr, endTimeStr, dateStr) {
    const trackContainer = document.createElement('div');
    trackContainer.className = 'timeline-section';

    const lbl = document.createElement('div');
    lbl.className = 'timeline-label';
    lbl.textContent = labelTitle;
    trackContainer.appendChild(lbl);

    const bar = document.createElement('div');
    bar.className = 'timeline-bar';

    const [startH, startM] = startTimeStr.split(':').map(Number);
    const [endH, endM] = endTimeStr.split(':').map(Number);
    const startTotalMin = startH * 60 + startM;
    const endTotalMin = endH * 60 + endM;
    const rangeSpanMin = endTotalMin - startTotalMin;

    for (let m = startTotalMin; m <= endTotalMin; m += 15) {
        const percent = ((m - startTotalMin) / rangeSpanMin) * 100;
        if (m % 60 === 0) {
            const hourTick = document.createElement('div');
            hourTick.className = 'tick-hour';
            hourTick.style.left = `${percent}%`;
            hourTick.textContent = `${String(m / 60).padStart(2, '0')}:00`;
            bar.appendChild(hourTick);
        } else {
            const qTick = document.createElement('div');
            qTick.className = 'tick-quarter';
            qTick.style.left = `${percent}%`;
            bar.appendChild(qTick);
        }
    }

    const filteredSlots = (slots || []).filter(slotTime => {
        const [sh, sm] = slotTime.split(':').map(Number);
        const slotMin = sh * 60 + sm;
        return slotMin >= startTotalMin && slotMin < endTotalMin;
    });

    if (filteredSlots.length === 0) {
        const noSlot = document.createElement('div');
        noSlot.style.cssText = 'position: absolute; left: 15px; font-size: 0.8rem; color: #94a3b8; font-style: italic;';
        noSlot.textContent = 'Inga lediga tider';
        bar.appendChild(noSlot);
    } else {
        filteredSlots.forEach(slotTime => {
            const [sh, sm] = slotTime.split(':').map(Number);
            const slotMin = sh * 60 + sm;
            const leftPercent = ((slotMin - startTotalMin) / rangeSpanMin) * 100;
            const widthPercent = Math.max(8, (30 / rangeSpanMin) * 100);

            const pill = document.createElement('div');
            pill.className = 'time-slot-pill';
            pill.style.left = `${leftPercent}%`;
            pill.style.width = `${widthPercent}%`;
            pill.textContent = slotTime;

            pill.onclick = () => selectSlot(dateStr, slotTime, pill);
            bar.appendChild(pill);
        });
    }

    trackContainer.appendChild(bar);
    return trackContainer;
}

function selectSlot(date, time, element) {
    document.querySelectorAll('.time-slot-pill').forEach(p => p.classList.remove('selected'));
    element.classList.add('selected');

    document.getElementById('selectedDate').value = date;
    document.getElementById('selectedTime').value = time;
    document.getElementById('selectedServiceId').value = document.getElementById('serviceSelect').value;
    document.getElementById('selectedSlotLabel').textContent = `${date} kl ${time}`;
    document.getElementById('bookingFormCard').style.display = 'block';
    document.getElementById('bookingFormCard').scrollIntoView({ behavior: 'smooth' });
}

async function submitBooking(event) {
    event.preventDefault();
    const payload = {
        service_id: document.getElementById('selectedServiceId').value,
        date: document.getElementById('selectedDate').value,
        time: document.getElementById('selectedTime').value,
        name: document.getElementById('clientName').value,
        email: document.getElementById('clientEmail').value,
        phone: document.getElementById('clientPhone').value
    };

    try {
        const response = await fetch('api.php?action=book', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (result.success) {
            const alertBox = document.getElementById('successAlert');
            alertBox.textContent = 'Tack för din bokning! En bekräftelse har skickats.';
            alertBox.style.display = 'block';
            document.getElementById('bookingFormCard').style.display = 'none';
            loadAvailability();
        } else {
            alert('Bokningen misslyckades: ' + (result.error || 'Okänt fel'));
        }
    } catch (err) {
        alert('Ett nätverksfel uppstod.');
    }
}
</script>

</body>
</html>