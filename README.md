# Capitony — Web App

Full database schema, admin/captain auth, trip & species & boat management,
and the visitor-facing Catch of the Day shop with cart/checkout.

## Folder structure

```
capitony-app/                (this whole repo = your document root)
├── index.php                 ← redirects to /shop.php
├── shop.php                  ← visitor: browse & add catch to cart
├── cart.php                  ← visitor: set services, review cart
├── checkout.php               ← visitor: place order
├── .htaccess                 ← blocks dotfiles, .sql/.md/.env, directory listing
├── admin/                     staff pages (dashboard, trips, species, boats, captains)
├── captain/                   staff pages (dashboard, catch posting)
├── assets/
│   ├── css/app.css
│   └── uploads/               species/boat/catch photos — .htaccess blocks script execution
├── config/
│   ├── .htaccess             ← blocks ALL direct web access to this folder
│   ├── config.example.php    ← copy to config.php, fill in real values
│   └── database.php
├── includes/
│   └── .htaccess             ← blocks ALL direct web access to this folder
├── scripts/
│   └── .htaccess             ← blocks ALL direct web access to this folder
└── sql/
    ├── .htaccess              ← blocks ALL direct web access to this folder
    ├── schema.sql             ← full schema, only for a brand-new database
    └── migrations/            ← run these in order on an existing database
```

**This repo root is deployed as-is into `public_html`.** Every folder that
must never be served directly (`config/`, `includes/`, `scripts/`, `sql/`)
carries its own `.htaccess` with `Require all denied`, so even though those
folders are technically inside `public_html`, Apache refuses to serve
anything from them.

### Hostinger Git deploy settings

- **Root directory**: `public_html` (the default)
- **Branch**: `main`

## Setup — brand-new database

1. Create the database, import `sql/schema.sql` (phpMyAdmin → Import, or
   `mysql -u user -p dbname < sql/schema.sql` if you have SSH).
2. `cp config/config.example.php config/config.php` and fill in real values.
3. Create the first admin: `php scripts/create_admin.php` via SSH, **or**,
   if you don't have SSH, temporarily restore `setup-admin.php` at the repo
   root (ask for it — it's a one-time web-based bootstrap that disables
   itself after the first admin is created), visit
   `https://yourdomain/setup-admin.php`, then delete the file again.
4. Log in at `/admin/login.php`, add captains under **Captains**, add a
   boat under **Boats** before scheduling any trips.

## Setup — applying a new migration to an existing database

Each file under `sql/migrations/` is numbered and safe to run once. Since
most Hostinger plans don't include SSH by default:

1. hPanel → Databases → phpMyAdmin → select your database → **SQL** tab
2. Open the migration file (e.g. `sql/migrations/002_species_boats_cart.sql`)
   in File Manager's editor, copy its contents
3. Paste into phpMyAdmin's SQL tab and click **Go**
4. If a statement errors with "duplicate column" or "table already exists,"
   that piece already applied — safe to ignore and continue with the rest

## What's wired up

- **Roles & auth**: session-based login, bcrypt hashing, CSRF protection,
  brute-force lockout (5 failed attempts → 15 minute lock)
- **Admin**: trips (create/edit/delete while still scheduled), species
  (name, Arabic name, latin name, price, photo — create/edit/delete/hide),
  boats (name, photo — create/edit/delete/hide), captain accounts
- **Captain**: start a trip, post Catch of the Day listings (species,
  weight, price, photo), pull a listing, go live (DB record only — see
  below), mark trip complete
- **Visitor shop**: browse live catch listings with photos and Arabic
  names, add to cart with a quantity
- **Cart**: session-based (no visitor accounts). Set pickup/delivery +
  clean/cook once and apply to every item in the cart with a clear warning
  about what that does, or override any single item afterward
- **Checkout**: re-verifies stock inside a DB transaction (so two people
  can't both buy the last kilo of grouper), creates an `order_groups`
  receipt plus one `orders` line per fish, decrements remaining stock
- **Service pricing**: admin sets Clean/Cook price per kg and a flat
  Delivery fee per order under **Service Pricing**. Recalculated
  server-side at checkout from current settings (never trusted from the
  cart session), and the actual amount charged is stored on the order —
  changing a price later never rewrites past orders.

## What's stubbed, not fully built yet

- **Live video**: "Go Live" creates a database record with a stream key,
  but nothing is actually receiving video yet — needs an RTMP ingest
  server (`nginx-rtmp-module`, or a hosted alternative) on the VPS.
- **WhatsApp catch alerts**: `catch_alerts` / `alert_notifications` tables
  exist; the matching + Twilio send logic hasn't been written yet.
- **Live session chat** (text/voice notes during a broadcast): schema
  exists (`chat_messages`), no UI yet.
- **Trip requests** (visitors asking to join a fishing trip): schema
  exists (`trip_requests`), no visitor-facing page yet.
- **Password change / forgot password** for staff accounts.
- **Admin order management view** — orders are created correctly by
  checkout, but there's no admin page yet to see/confirm/fulfill them.

## Security notes worth knowing

- Sessions use `httponly`, `samesite=Lax`, and `secure` cookies once
  `APP_ENV` is `production` (requires active HTTPS).
- All SQL goes through PDO prepared statements throughout.
- Uploaded images (species/boats/catch photos) are re-encoded via GD
  before saving — this strips any non-image payload someone might try to
  disguise as a photo — and the uploads folder blocks `.php` execution
  as a second layer of defense.
- `config.php` is never committed (`.gitignore`) and is blocked at the
  web server level via `.htaccess`, same as `includes/`, `scripts/`, `sql/`.
- Checkout stock checks happen inside a transaction with `FOR UPDATE`
  row locking, so concurrent buyers can't oversell a limited catch.
