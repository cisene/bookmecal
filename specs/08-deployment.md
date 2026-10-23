# 8. Deployment (trial server)

How to put Book Me Calendar on a real server for the trial. Files referred to here live in `deploy/` in the repository (not under `src/`, so they are never served).

## 8.1 Requirements
- PHP 8.3 or newer (developed on 8.5) with `mbstring` and `json`; `pcntl` only for running the tests.
- Apache 2.4 with `mod_rewrite` and `mod_headers`, `AllowOverride All` for the site (the `.htaccess` files hold the access rules).
- An SMTP account at the mail provider of the sender's domain (see §8.5), or a mail transfer agent for PHP `mail()`.
- HTTPS certificate, e.g. Let's Encrypt (`certbot --apache`).

## 8.2 Files and permissions
1. Clone or copy the repository to e.g. `/var/www/bookmecal` and point the virtual host's `DocumentRoot` at `/var/www/bookmecal/src`.
2. The web server user (e.g. `www-data`) needs write access to `src/data/` (including `pending/`, `ratelimit/`), `src/log/` and `src/cache/`, and to `src/admin/.htpasswd`. Nothing else needs to be writable.
3. Other users on the machine should not read `src/data/` or `src/admin/.htpasswd`: `chmod -R o-rwx src/data src/log src/admin/.htpasswd`.
4. Check from outside that these answer `403`: `/data/settings.json`, `/data/secrets.json`, `/log/security.log`, `/admin/.htpasswd`, `/classes/Input.php`, `/admin/session.php`.

## 8.3 HTTPS and the admin pages
1. Get a certificate and redirect HTTP to HTTPS in the virtual host.
2. Set `"admin_require_https": true` in `src/data/settings.json`. Admin pages then refuse plain HTTP, and the session cookie is `Secure`.
3. `Strict-Transport-Security` is sent automatically on HTTPS responses (`SecurityHeaders`).

## 8.4 First sign-in and settings
1. Create the first admin account: `htpasswd -B -c src/admin/.htpasswd <name>` (bcrypt). More accounts can be added on the Users page.
2. Sign in at `/admin/` and work through the "Before customers book" list on the dashboard (`SetupCheck`). It disappears when everything is set:
   - Treatments (Services page) and opening hours (Opening hours page).
   - Optional: employees (Employees page, with the treatments each one offers) and their working hours (Working hours page). Without employees the opening hours alone decide; an employee without saved hours follows the opening hours.
   - Business name, privacy contact and retention (Config page, Privacy).
   - Email on, sender name and address, alert address, booking site address (`public_url`, the address customers use, e.g. `https://boka.example.se/`), cancellation limit (Config page, Email).
3. Optional: turn on the calendar subscription (Config page) and add the link to the phone's calendar.

## 8.5 Email
Simplest: enter the mail provider's SMTP server on the Config page (Email, "Sending server"): server name, STARTTLS and port 587 (or SSL/TLS and 465), user name and password (an app password where the provider offers them). Save, then use "Send test email". The password is stored in `src/data/secrets.json`, which is not in git.

Without an SMTP server on the Config page, PHP `mail()` hands mails to the local `sendmail` program. Then `msmtp` relaying through the mail provider is the simplest:

```
# /etc/msmtprc (readable by www-data only)
account default
host smtp.example-provider.com
port 587
tls on
auth on
user booking@example.se
password <app password>
from booking@example.se
```

and in `php.ini`: `sendmail_path = "/usr/bin/msmtp -t"`.

- The sender address on the Config page should be on the business's own domain, and that domain needs SPF, DKIM and DMARC records for the mail provider, or the emails end up as spam.
- Test before the trial: make a booking on the public page and check that the receipt and the alert arrive, then approve it and check the confirmation with the calendar file.

## 8.6 Reminder cron job
`deploy/cron/bookmecal` runs `send_reminders.php` every 30 minutes as `www-data`; each approved booking gets one reminder in the 24 hours before it starts. Copy it to `/etc/cron.d/bookmecal` and adjust the path.

## 8.7 fail2ban
Optional, and only possible where the host gives root access; shared hosting usually does not. The application locks admin sign-in and rate limits the public pages itself. `fail2ban` adds a firewall block for clients that keep going:

1. Copy `deploy/fail2ban/bookmecal.conf` to `/etc/fail2ban/filter.d/` and `deploy/fail2ban/bookmecal.local` to `/etc/fail2ban/jail.d/`, set `logpath`.
2. Test the filter: `fail2ban-regex /var/www/bookmecal/src/log/security.log /etc/fail2ban/filter.d/bookmecal.conf`.
3. `sudo fail2ban-client reload`, then `sudo fail2ban-client status bookmecal`.

The filter matches failed sign-ins, lockouts, honeypot hits, bad form tokens, rate limits and wrong cancellation or calendar tokens. 10 of them within 10 minutes block the address for an hour. It does not match `csrf_failed` (also caused by an expired admin page) or `pending_limit`.

## 8.8 Backups
- Back up `src/data/` (bookings, settings, keys), `src/admin/.htpasswd` and `src/log/security.log`, e.g. nightly with `rsync` or `tar` to another machine.
- Backups contain customer data: encrypt them, keep only a few weeks, and remember that a booking deleted on request still exists in older backups until they expire (mention this if a customer asks).
- `src/data/secrets.json` holds the signing keys. If it is lost, a new one is created: open booking pages need a reload, old cancellation links stop working and the calendar subscription must be turned on again.

## 8.9 Updating
Some data files are in git with development values: `src/data/config.json`, `settings.json`, `services.json`, `openhours.json` and `credentials.json`. The admin pages change them on the server, so a plain `git pull` there would conflict with (or overwrite) the live settings.

- Simplest for the trial: update the code with `rsync` from a checkout and leave the data alone, e.g. `rsync -a --delete --exclude 'src/data/' --exclude 'src/log/' --exclude 'src/cache/' --exclude 'src/admin/.htpasswd' --exclude '.git/' ./ server:/var/www/bookmecal/`.
- Bookings (`src/data/pending/`), employees with their working hours (`src/data/employees/`, excluded with `src/data/`), keys (`secrets.json`), the audit log and the security log are never touched by an update.
- New settings get their defaults until they are saved on the Config page.
- Run `phpunit` before copying a larger update.

Later (with the SQL data layer, or before), the data files should leave git and be created from examples on install; see [05-todo-and-roadmap.md](05-todo-and-roadmap.md).
