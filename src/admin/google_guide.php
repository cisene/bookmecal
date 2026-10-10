<?php
// src/admin/google_guide.php - Step-by-step illustrated Google Calendar setup guide
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Calendar Setup Guide - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; color: #007bff; }
        h3 { color: #495057; margin-top: 25px; }
        .illustration { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px; margin: 10px 0; overflow-x: auto; border: 1px solid #444; }
        .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        ol li { margin-bottom: 12px; line-height: 1.6; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Google Calendar Integration Guide</h2>
        <p>Follow this step-by-step visual walkthrough to create your Google Cloud project, obtain your OAuth credentials, and locate your Calendar ID for seamless synchronization.</p>

        <p style="margin-top: 20px;">
            <a href="setup_google.php" class="btn">&larr; Return to Enrollment Wizard</a>
        </p>

        <hr style="border:0; border-top:1px solid #dee2e6; margin: 30px 0;">

        <h3>Part 1: Obtaining your Client ID & Client Secret</h3>
        <p>To allow Book Me Calendar to sync with your appointments securely, you must register an OAuth app in the Google Cloud Console.</p>

        <ol>
            <li>
                <strong>Step 1: Go to Google Cloud Console</strong><br>
                Navigate to <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a> and sign in with your Google account.
            </li>
            <li>
                <strong>Step 2: Create or Select a Project</strong><br>
                Click the project dropdown at the top of the page and click <strong>New Project</strong>. Name your project (e.g., <code>Book Me Calendar</code>) and click Create.
            </li>
            <li>
                <strong>Step 3: Enable the Google Calendar API</strong><br>
                In the left navigation sidebar, go to <strong>APIs & Services</strong> &gt; <strong>Library</strong>. Search for <code>Google Calendar API</code> and click <strong>Enable</strong>.
            </li>
            <li>
                <strong>Step 4: Configure OAuth Consent Screen</strong><br>
                Go to <strong>APIs & Services</strong> &gt; <strong>OAuth consent screen</strong>. Select <strong>External</strong> (or Internal if using Google Workspace), fill out your App name and support email, and save.
            </li>
            <li>
                <strong>Step 5: Generate OAuth Client ID</strong><br>
                Navigate to <strong>Credentials</strong> &gt; Click <strong>+ Create Credentials</strong> &gt; Choose <strong>OAuth client ID</strong>.
                <div class="illustration">
[ Google Cloud Console: Credentials ]
+---------------------------------------------------+
| Application type: [ Web application             v ] |
| Name:             [ Book Me Calendar Web Client     ] |
|                                                   |
| Authorized redirect URIs:                         |
| [+ ADD URI] https://yourdomain.com/admin/setup_google.php?step=2 |
+---------------------------------------------------+
[ CREATE ]
                </div>
            </li>
            <li>
                <strong>Step 6: Copy your Credentials</strong><br>
                Once created, a popup will display your <strong>Client ID</strong> and <strong>Client Secret</strong>. Copy both values into Step 1 of your enrollment wizard.
            </li>
        </ol>

        <hr style="border:0; border-top:1px solid #dee2e6; margin: 30px 0;">

        <h3>Part 2: Locating your Calendar ID</h3>
        <p>The Calendar ID dictates which specific calendar your booking slots and appointments are written to.</p>

        <ol>
            <li>
                <strong>Step 1: Open Google Calendar</strong><br>
                Go to <a href="https://calendar.google.com/" target="_blank">calendar.google.com</a> in your web browser.
            </li>
            <li>
                <strong>Step 2: Access Calendar Settings</strong><br>
                On the left sidebar under <strong>My calendars</strong>, hover over your target calendar, click the <strong>three vertical dots (Options)</strong>, and select <strong>Settings and sharing</strong>.
            </li>
            <li>
                <strong>Step 3: Copy the Calendar ID</strong><br>
                Scroll down the settings page until you find the <strong>Integrate calendar</strong> section. Look for the <strong>Calendar ID</strong> field.
                <div class="illustration">
[ Google Calendar Settings ]
+---------------------------------------------------+
| Integrate calendar                                |
| ...                                               |
| Calendar ID:                                      |
| [ primary or abc123xyz@group.calendar.google.com ]|
+---------------------------------------------------+
                </div>
                <p><em>Note: For your default personal calendar, this is your email address or <code>primary</code>. For shared or secondary calendars, it is a long alphanumeric string ending in <code>@group.calendar.google.com</code>.</em></p>
            </li>
        </ol>

        <div style="margin-top: 30px; text-align: center;">
            <a href="setup_google.php" class="btn">Proceed to Enrollment Wizard &rarr;</a>
        </div>
    </div>
</div>
</body>
</html>