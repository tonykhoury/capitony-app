# Capitony — Web App (Stage 1: Database + Auth + Roles)

This is the first coded increment: the full database schema, and working
login/dashboards for the **admin** and **captain** roles. Visitor-facing
pages (catch alerts, ordering, live view) come next.

## Folder structure

```
capitony-app/
├── config/
│   ├── config.example.php   ← copy to config.php, fill in real values
│   └── database.php         ← PDO connection helper
├── includes/                ← shared PHP (auth, csrf, nav partials) — NOT web-accessible
├── public/                  ← this is your web server's document root
│   ├── admin/
│   ├── captain/
│   └── assets/css/app.css
├── scripts/
│   └── create_admin.php     ← CLI-only, bootstraps the first admin
└── sql/
    └── schema.sql
```

**Important:** point your web server (Apache/Nginx vhost) at `public/` as
the document root — `config/` and `includes/` must sit *outside* the
publicly served folder. On Hostinger's hPanel, set the domain's document
root to `capitony-app/public`.

## Setup steps

1. **Create the database** in hPanel (or `mysql` CLI), then import the schema:
   ```
   mysql -u your_db_user -p your_db_name < sql/schema.sql
   ```

2. **Configure the app**:
   ```
   cp config/config.example.php config/config.php
   ```
   Edit `config/config.php` with your real DB credentials and (later) Twilio keys.

3. **Create the first admin account** via SSH:
   ```
   php scripts/create_admin.php
   ```
   Follow the prompts. This is the only way to create the first admin —
   there's no public sign-up form, on purpose.

4. **Log in**:
   - Admin: `https://capitony.live/admin/login.php`
   - Captain: `https://capitony.live/captain/login.php`

5. As admin, go to **Captains** and create an account for each captain.
   They should change their password after first login (password-change
   flow is a small addition for the next pass — flag if you want it
   prioritized).

## What's wired up in this stage

- **Roles & auth**: session-based login, bcrypt password hashing, CSRF
  protection on every form, brute-force lockout (5 failed attempts → 15
  minute lock).
- **Admin**: schedule trips (date, boat, seats, price, assign a captain),
  manage species + default pricing.
- **Captain**: see assigned trips, start a trip, create a live session
  record, mark a trip complete.

## What's stubbed, not fully built yet

- **Live video**: "Go Live" creates a database record with a stream key,
  but nothing is actually receiving video yet. Since you're on a VPS/Cloud
  plan, the next step is installing an RTMP ingest server (`nginx` with
  the `nginx-rtmp-module`, or a hosted alternative like Cloudflare Stream)
  that the captain's phone/OBS pushes to using that stream key, and that
  serves it back out as HLS for the visitor-facing player.
- **WhatsApp catch alerts**: the `catch_alerts` and `alert_notifications`
  tables exist, but the code that checks new catch listings against open
  alerts and sends via Twilio hasn't been written yet — that's the
  logical next piece.
- **Captain catch-posting page** (`/captain/catch.php`) is linked from the
  dashboard but not built yet — needed before alerts can trigger off it.
- **Visitor-facing pages** (browsing trips, requesting a seat, buying the
  catch, subscribing to alerts, live view + chat) — none of this exists
  yet; everything so far is admin/captain tooling.
- **Password change / forgot password** for staff accounts.

## Security notes worth knowing

- Sessions use `httponly`, `samesite=Lax` cookies, and `secure` cookies
  once `APP_ENV` is `production` (requires HTTPS — get this from
  Hostinger's free SSL before going live).
- All SQL goes through PDO prepared statements — no raw string
  concatenation into queries anywhere in this codebase; keep it that way
  as we add more pages.
- `config.php` must never be committed or made web-accessible — it's in
  `.gitignore`, and living outside `public/` means even a misconfigured
  server can't serve it as plain text by direct URL.
