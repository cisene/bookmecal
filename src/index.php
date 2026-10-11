<?php
// index.php - Clinic Homepage & Booking Interface
// Any backend initialization or localization loading can happen here.
$countryCode = 'se';
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
            max-width: 600px;
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        h1 { font-size: 1.5rem; margin-top: 0; color: var(--primary-dark); }
        .step { margin-bottom: 1.5rem; }
        label { display: block; font-weight: 600; margin-bottom: 5px; }
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .slot-btn {
            padding: 10px;
            background: #e0f2fe;
            border: 1px solid var(--primary);
            color: var(--primary-dark);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-align: center;
        }
        .slot-btn:hover { background: var(--primary); color: white; }
        .slot-btn.selected { background: var(--primary-dark); color: white; }
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
        }
        button.submit-btn:hover { background: var(--primary-dark); }
        .hidden { display: none; }
    </style>
</head>
<body>

<div class="container">
    <h1>Boka behandling</h1>
    
    <form id="bookingForm" onsubmit="submitBooking(event)">
        <!-- Step 1: Service Selection -->
        <div class="step">
            <label for="serviceSelect">Välj tjänst:</label>
            <select id="serviceSelect" required onchange="fetchAvailability()">
                <!-- Populated dynamically -->
            </select>
        </div>

        <!-- Step 2: Date Selection -->
        <div class="step">
            <label for="bookingDate">Välj datum:</label>
            <input type="date" id="bookingDate" required onchange="fetchAvailability()">
        </div>

        <!-- Step 3: Available Slots -->
        <div class="step">
            <label>Tillgängliga tider:</label>
            <div id="slotsContainer" class="slots-grid">
                <p style="grid-column: 1 / -1; color: #64748b;">Välj datum för att se tider.</p>
            </div>
            <input type="hidden" id="selectedTime" required>
        </div>

        <!-- Step 4: Customer Details -->
        <div id="customerDetails" class="hidden">
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
    let selectedSlot = null;

    document.addEventListener('DOMContentLoaded', () => {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('bookingDate').min = today;
        document.getElementById('bookingDate').value = today;

        loadServices();
    });

    async function loadServices() {
        try {
            const res = await fetch(`${API_URL}?action=get_services&country=${COUNTRY_CODE}`);
            const services = await res.json();
            const select = document.getElementById('serviceSelect');
            select.innerHTML = '';
            services.exports = services; // safety
            services.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = `${s.name} (${s.duration_minutes} min - ${s.price} ${s.currency})`;
                select.appendChild(opt);
            });
            fetchAvailability();
        } catch (e) {
            console.error('Failed to load services', e);
        }
    }

    async function fetchAvailability() {
        const date = document.getElementById('bookingDate').value;
        const serviceId = document.getElementById('serviceSelect').value;
        if (!date || !serviceId) return;

        const container = document.getElementById('slotsContainer');
        container.innerHTML = '<p style="grid-column: 1 / -1; color: #64748b;">Laddar tider...</p>';

        try {
            const res = await fetch(`${API_URL}?action=check_availability&date=${date}&service_id=${serviceId}&country=${COUNTRY_CODE}`);
            const data = await res.json();

            container.innerHTML = '';
            if (!data.available_slots || data.available_slots.length === 0) {
                container.innerHTML = '<p style="grid-column: 1 / -1; color: #dc2626;">Inga lediga tider detta datum (helgdag eller fullbokat).</p>';
                return;
            }

            data.available_slots.forEach(time => {
                const btn = document.createElement('div');
                btn.className = 'slot-btn';
                btn.textContent = time;
                btn.onclick = () => selectTime(time, btn);
                container.appendChild(btn);
            });
        } catch (e) {
            container.innerHTML = '<p style="grid-column: 1 / -1; color: #dc2626;">Kunde inte hämta tillgänglighet.</p>';
        }
    }

    function selectTime(time, element) {
        document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
        element.classList.add('selected');
        selectedSlot = time;
        document.getElementById('selectedTime').value = time;
        document.getElementById('customerDetails').classList.remove('hidden');
    }

    async function submitBooking(e) {
        e.preventDefault();
        const payload = {
            service_id: document.getElementById('serviceSelect').value,
            date: document.getElementById('bookingDate').value,
            time: selectedSlot,
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