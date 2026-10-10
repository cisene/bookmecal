<?php
// index.php - Frontend calendar interface using FullCalendar and Book Me Calendar API

$config = require_once __DIR__ . '/config.php';
$appName = $config['app']['name'] ?? 'Book Me Calendar';
$defaultLang = $config['app']['default_lang'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($defaultLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?></title>
    <!-- FullCalendar CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        h1 {
            text-align: center;
            color: #2c3e50;
        }
        #calendar {
            margin-top: 20px;
        }
        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 6px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: #000; }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .btn-submit {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        .btn-submit:hover { background-color: #218838; }
        #message {
            margin-top: 15px;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <h1><?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p style="text-align: center; color: #666;">Click on an available green slot to book your appointment.</p>
    
    <div id="calendar"></div>
</div>

<!-- Booking Modal -->
<div id="bookingModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeModal">&times;</span>
        <h2>Book Time Slot</h2>
        <form id="bookingForm">
            <input type="hidden" id="slotStart">
            <input type="hidden" id="slotEnd">
            
            <div class="form-group">
                <label for="name">Your Name:</label>
                <input type="text" id="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" required>
            </div>
            
            <div class="form-group">
                <label>Selected Time:</label>
                <p id="slotDisplay" style="margin: 0; color: #555;"></p>
            </div>
            
            <button type="submit" class="btn-submit">Confirm Booking Request</button>
        </form>
        <div id="message"></div>
    </div>
</div>

<!-- FullCalendar JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var modal = document.getElementById('bookingModal');
    var closeModal = document.getElementById('closeModal');
    var bookingForm = document.getElementById('bookingForm');
    var messageEl = document.getElementById('message');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: 'api.php?action=get_slots',
        eventClick: function(info) {
            // Populate modal with selected slot details
            document.getElementById('slotStart').value = info.event.startStr;
            document.getElementById('slotEnd').value = info.event.endStr;
            
            var options = { dateStyle: 'full', timeStyle: 'short' };
            document.getElementById('slotDisplay').innerText = 
                info.event.start.toLocaleString([], {dateStyle: 'medium', timeStyle: 'short'}) + 
                ' - ' + info.event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            messageEl.innerText = '';
            bookingForm.reset();
            modal.style.display = 'block';
        }
    });

    calendar.render();

    closeModal.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    bookingForm.onsubmit = function(e) {
        e.preventDefault();
        
        var payload = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            start: document.getElementById('slotStart').value,
            end: document.getElementById('slotEnd').value
        };

        fetch('api.php?action=book', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageEl.style.color = 'green';
                messageEl.innerText = data.message;
                setTimeout(function() {
                    modal.style.display = 'none';
                    calendar.refetchEvents();
                }, 2000);
            } else {
                messageEl.style.color = 'red';
                messageEl.innerText = data.error || 'An error occurred.';
            }
        })
        .catch(error => {
            messageEl.style.color = 'red';
            messageEl.innerText = 'Network error. Please try again.';
        });
    };
});
</script>

</body>
</html>