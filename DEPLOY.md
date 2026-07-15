# Hosting Jamin's Nutrition Consult online

This is a self-contained PHP + SQLite app. There is no build step, no Composer, no
npm, and no separate database server to set up — SQLite is a single file the app
creates itself. Any ordinary PHP web host will run it.

**Security checklist before go-live**

1. Put a **bcrypt hash** of a strong password in `config.local.php` (`php scripts/hash-password.php '…'`).
2. Point the document root at **`public/`** only.
3. Confirm `data/` is not web-readable (`.htaccess` is included).
4. Turn on **HTTPS**. If TLS terminates at a reverse proxy, set `'trust_proxy' => true` in
   `config.local.php` so session cookies can be marked Secure correctly.
5. Keep SMS keys only in `config.local.php` (never in git).
6. Optionally schedule `scripts/backup-db.sh` daily.
7. Schedule day-before SMS reminders (adjust the PHP path for your host):

```cron
0 8 * * * /usr/bin/php /home/you/event-booking/scripts/send-reminders.php >> /home/you/event-booking/data/reminders.log 2>&1
```

---

## 1. What the host must provide

| Requirement | Notes |
|---|---|
| **PHP 8.1 or newer** | 8.2 recommended (what it was built on). |
| **PDO SQLite** extension (`pdo_sqlite`) | Almost always on by default. |
| **cURL** extension | Needed to send SMS through mNotify. |
| **mbstring** extension | Text handling. |
| **HTTPS / SSL** | Free with Let's Encrypt / AutoSSL on most hosts. Turn it on. |

You do **not** need MySQL, Node, or shell access. Standard cPanel shared hosting is
enough.

---

## 2. Upload the files

Upload the whole `event-booking` folder to your account (via cPanel File Manager or
FTP). It contains, at the top level:

```
public/            <-- the ONLY folder the web should serve
src/  views/       application code
config.php         base settings (no secrets)
config.local.php   YOUR secrets (password, SMS key)  <-- see step 4
data/              database + sessions (created automatically)
```

> **Do not upload `data/bookings.sqlite` from your test machine** if you want a clean
> start — delete it first and the site rebuilds an empty database with the price list
> on first visit. Uploading it keeps your existing bookings.

---

## 3. Point the domain at `public/` (recommended)

The single most important step. **The web must serve the `public/` folder, not the
project root** — everything else (the database, your SMS key, the code) sits alongside
`public/` specifically so it can never be downloaded.

- **cPanel:** create the site as an *addon domain* or *subdomain* and set its
  "Document Root" to `.../event-booking/public`.
- **VPS (Apache/Nginx):** set the virtual host root to `.../event-booking/public`.
  For Nginx, copy [`deploy/nginx.conf.example`](deploy/nginx.conf.example) and adjust.

The `public/.htaccess` needed for Apache is already included. Nginx ignores it; use
the sample config in `deploy/` instead.

**If your host truly won't let you point the document root at `public/`**, and you must
serve the project root itself, create a `.htaccess` in the project root with the contents
below. Do this ONLY in that situation — if the document root is already `public/`, an
`.htaccess` in the project root will be read as a parent directory and cause a 403.

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(config\.php|config\.local\.php|config\.local\.example\.php) - [F,L]
    RewriteRule ^(src|views|data)/ - [F,L]
    RewriteCond %{DOCUMENT_ROOT}/public%{REQUEST_URI} -f
    RewriteRule ^(.*)$ public/$1 [L]
    RewriteRule ^ public/index.php [L]
</IfModule>
```

---

## 4. Set your secrets in `config.local.php`

Open `config.local.php` and set:

```php
'admin_password' => 'a-strong-password-you-choose',   // CHANGE THIS

'sms' => [
    'driver'           => 'mnotify',                  // or 'log' to keep SMS off
    'api_key'          => 'your-mnotify-api-key',
    'sender_id'        => 'JAMINSNUTCO',
    'admin_recipients' => ['+233249601468'],
],
```

- This file is **not** served to the web and is git-ignored. Keep it off any public
  repo or shared link. If it ever leaks, rotate the mNotify key and change the password.
- If this file is missing, the site still runs but with **SMS off** and login disabled —
  a safe failure, not a broken one.
- Every value can also be set with an environment variable instead
  (`EVENT_ADMIN_PASSWORD`, `MNOTIFY_API_KEY`, `SMS_DRIVER`, `SMS_SENDER_ID`,
  `SMS_ADMIN_PHONE`), which is handy on hosts that manage secrets that way.

---

## 5. Make `data/` writable

The app writes the database, session files and an error log into `data/`. Give that
folder write permission for the web server:

- **cPanel File Manager:** select `data/`, *Permissions*, set to **755** (some hosts
  need **775**).
- **Command line:** `chmod -R 755 data`

If bookings fail to save or logins won't stick, this is almost always the cause.

---

## 6. Turn on HTTPS

Enable SSL for the domain (AutoSSL / Let's Encrypt). The app already:

- redirects plain HTTP to HTTPS (via `.htaccess`), and
- marks its login cookie **Secure** once you're on HTTPS.

Do not skip this — the admin panel shows every client's name and phone number.

---

## 7. First visit

Open the site. On the first request the app creates `data/bookings.sqlite`, seeds the
14 services, and you're live. Log in at **`/admin`** with the password from step 4.

---

## Going-live checklist

- [ ] Document root points at `public/`.
- [ ] HTTPS works and HTTP redirects to it.
- [ ] `config.local.php` has a **strong** admin password (a bcrypt hash, not a weak default).
- [ ] `data/` is writable — a test booking saves and appears in `/admin`.
- [ ] A test booking sends the SMS (check `/admin/messages`).
- [ ] mNotify has enough **SMS credits** (each booking = 2 messages; each
      confirm/reschedule/cancel = 1 more).
- [ ] Visiting `/config.php`, `/config.local.php` or `/data/bookings.sqlite` in a
      browser gives **403/404**, not the file. (If it shows the file, your document
      root is wrong — fix step 3 immediately.)

---

## Looking after it

- **Backups:** the entire system is one file — `data/bookings.sqlite`. Download it
  regularly. To restore, upload it back.
- **Errors:** anything that goes wrong is logged to `data/php-error.log`. Visitors only
  ever see a polite "something went wrong" page.
- **Debugging:** to see full errors on screen while diagnosing, set the environment
  variable `APP_DEBUG=true` (or add `'app' => ['debug' => true]` to `config.local.php`).
  **Turn it off again** before real clients use the site.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Blank page / 500 error | `data/` not writable, or PHP older than 8.1. Check `data/php-error.log`. |
| "Session expired" on every booking | `data/sessions/` not writable. |
| Browser downloads the code instead of running it | PHP not enabled for the folder, or wrong document root. |
| You can see `config.php` / the database in a browser | Document root is the project root, not `public/`. Point it at `public/`. |
| 403 Forbidden on every page | A stray `.htaccess` in the project root (above `public/`) is being read as a parent directory. Delete it — with docroot at `public/` you only need `public/.htaccess`. |
| SMS never arrives | Driver still `log`, wrong API key, or no mNotify credits — check `/admin/messages`, each row shows the gateway's response. |
