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

- **Live session chat (visitor ↔ captain, text and voice)**: shown on
  the homepage live player for visitors, and on the captain's
  catch-posting page whenever a live session is active. Polling-based
  (checks for new messages every 4 seconds — no websocket/SSE server
  needed for this message volume). Voice notes recorded in-browser via
  the `MediaRecorder` API, uploaded through the same safe-storage
  pattern as photos/videos (outside `public_html`, served via
  `media.php`). Visitor identity (name) is remembered via a cookie, no
  account needed; captain identity comes from their login session, never
  a client-supplied field. `chat-send.php` skips CSRF checking
  deliberately — it's called from a logged-out page via `fetch()`, and
  a same-origin chat message has low enough stakes that the UX cost of
  a token roundtrip isn't worth it; revisit if spam becomes a real issue.
- **Photo album**: admin uploads/removes photos and videos (`/admin/gallery.php`),
  shown publicly at `/album.php`. Photos are re-encoded via GD like every
  other upload; videos are validated by real MIME type (not just file
  extension) since there's no video equivalent of GD re-encoding. Both go
  through the same redeploy-safe `UPLOADS_STORAGE_DIR` storage.

## Setup — brand-new database

## ⚠️ Required action if you deployed before this fix

If your `config.php` doesn't already have `UPLOADS_STORAGE_DIR` defined,
**add this line now, before your next deploy**:
```php
define('UPLOADS_STORAGE_DIR', dirname(__DIR__, 2) . '/private_uploads');
```
Without it, `handle_image_upload()` will fatally error on the next photo
upload. This also means any photos uploaded before this fix are gone —
Hostinger's Git deploy wiped them on a prior redeploy, since they lived
inside the deployed folder. They'll need to be re-uploaded (species
photos, boat photos, catch listings) once this is live — sorry about
that; there was no way to recover them after the fact.

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
  weight, price, photo), pull a listing, go live, mark trip complete
- **Live streaming**: captain streams via RTMP (e.g. Larix Broadcaster) to
  a dedicated VPS running nginx-rtmp in Docker (see
  `docs/live-streaming-setup.md`); homepage plays it back live via
  hls.js, with a "starting soon" retry state and automatic fallback for
  Safari's native HLS support
- **WhatsApp catch alerts**: visitors set an alert at `/alerts.php` —
  any number of species (checkboxes, none selected = any species, via
  the `catch_alert_species` junction table), and a weight condition of
  "any," "at least X kg," or "between X and Y kg" (`min_weight_kg` /
  `max_weight_kg`, either or both nullable). The moment a captain posts a matching catch, `trigger_catch_alerts()` sends a
  WhatsApp message via Twilio — non-blocking, so a Twilio failure never
  breaks catch posting. Uses the `notifications_order_update_template`
  Quick Reply template (Content Template Builder → find the Quick Reply
  variant, not the Media one — no image required), with `{{date}}` and
  `{{time}}` stretched to carry the catch details and shop link. Set
  `TWILIO_WHATSAPP_JOIN_CODE` and `TWILIO_WHATSAPP_TEMPLATE_SID` in
  `config.php` (both server-side only, never in git). Sandbox mode
  requires every subscriber to text the join code once before they can
  receive anything — the signup page explains this.
  Move to a real WhatsApp Business sender + a custom-written template
  before real launch; the current mapping (`ContentVariables`
  in `includes/whatsapp.php`) is a rough fit, not the real message copy.
- **Trip booking**: upcoming trips shown on the homepage and a full
  `/trips.php` listing, with a request-to-join form (name, phone, seat
  count). Seats remaining accounts for pending *and* confirmed requests,
  so the site never shows more availability than actually exists. Admin
  manages incoming requests (confirm/decline) at `/admin/trip-requests.php`.
- **Visitor shop**: browse live catch listings with photos and Arabic
  names, add to cart with a quantity
- **Cart**: session-based (no visitor accounts). Set pickup/delivery +
  clean/cook once and apply to every item in the cart with a clear warning
  about what that does, or override any single item afterward
- **SKU per catch**: every posted fish gets a unique SKU (`CAP-000123`,
  derived from its own database ID, so uniqueness is free) the moment a
  captain posts it. Shown on the captain's postings table, the shop PLP,
  cart, and checkout — and stored directly on `orders.sku` too
  (denormalized on purpose) so future automation/fulfillment tooling can
  query by SKU without a join. Captains can print a label per fish
  (species, weight, SKU, and a QR code encoding the SKU) from
  `/captain/print-label.php?id=X`, linked from the postings table.
- **Checkout**: re-verifies stock inside a DB transaction (so two people
  can't both buy the last kilo of grouper), creates an `order_groups`
  receipt plus one `orders` line per fish, decrements remaining stock
- **Service pricing**: admin sets Clean/Cook price per kg and a flat
  Delivery fee per order under **Service Pricing**. Recalculated
  server-side at checkout from current settings (never trusted from the
  cart session), and the actual amount charged is stored on the order —
  changing a price later never rewrites past orders.

## What's stubbed, not fully built yet

- **Social media auto-publish (PARKED — Instagram + Facebook)**: every
  photo/video uploaded to the site also gets published to Instagram and
  Facebook automatically. Design notes:
  - Instagram requires a Business/Creator account linked to a Facebook
    Page, and publishing is a "container" flow: `POST /{ig-user-id}/media`
    with a public `image_url`/`video_url` (our `media.php` URLs already
    satisfy this), poll `GET /{container-id}?fields=status_code` until
    `FINISHED` for video, then `POST /{ig-user-id}/media_publish`.
    Facebook Page posting is similar but simpler for photos.
  - Since this only ever needs to post to **Capitony's own accounts**
    (not third-party accounts), Meta's full App Review process likely
    isn't required — a Meta developer app in Development Mode can
    publish to accounts added as testers/admins on the app itself. Worth
    confirming this still holds at setup time, but it avoids the
    multi-week review cycle that a real third-party integration would need.
  - Needs OAuth setup once (long-lived Page access token + connected IG
    user ID stored in `config.php`, same pattern as Twilio/Zoho creds).
  - Open design question: does "every image/video uploaded" mean the
    curated Photo Album only, or also every individual Catch of the Day
    photo? The latter could mean multiple posts per trip (one per fish),
    which may be too frequent for a following — worth deciding before
    building, not after.
  - Needs `instagram_post_id` / `facebook_post_id` columns on
    `gallery_items` for idempotency (never double-post the same upload),
    and — like Zoho — should be non-blocking: a failed social post should
    never block the upload itself from succeeding.

- **Zoho Books invoice automation (PARKED)**: auto-generate an invoice in
  Zoho Books for every completed order. Design notes based on how Zoho's
  API actually works:
  - Zoho Books has **no programmatic webhook subscription** — outbound
    webhooks can only be configured manually through Zoho's own workflow
    rules UI, not via API. So the integration has to run the other
    direction: **our checkout.php pushes to Zoho** right after an order
    commits successfully, rather than Zoho pushing to us.
  - Auth is OAuth 2.0 with a refresh-token flow (register an API client
    in Zoho's API console, store the refresh token in `config.php`
    alongside the Twilio credentials — same sensitivity level).
  - Every API call needs an `organization_id`, and Zoho runs **separate
    regional API domains** (`.com`, `.eu`, `.in`, `.com.au`, `.jp`,
    `.ca`) — need to confirm which data center this Zoho account is on
    before writing the integration, or requests will silently fail.
  - Flow: on successful checkout, match-or-create the customer contact
    in Zoho by phone/email, map each order line to a Zoho invoice line
    item (species + weight + services as line items — simplest to use
    ad-hoc line items with description/rate/quantity rather than
    pre-mapping every species into Zoho's item catalog), then create the
    invoice with the `order_groups.id` as an external reference for
    idempotency (so a retried request never creates a duplicate
    invoice). Needs a `zoho_invoice_id` column added to `order_groups`
    to track what's already been synced.
  - Failures need to be non-blocking — if Zoho's API is down, the order
    itself must still complete; the invoice sync should be a
    best-effort follow-up step (or a small retry queue) rather than part
    of the checkout transaction itself.

- **Admin operations dashboard (PARKED — metrics overview)**: the admin
  dashboard currently just lists upcoming trips. A real metrics view
  would pull from data that already exists: revenue by day/week from
  `orders`/`order_groups`, best-selling species from `catch_items`,
  sell-through rate (posted vs. sold weight) per trip, and captain
  activity (trips run, catches posted). No new schema needed — this is
  purely new admin-side query/UI work whenever it's prioritized.
- **Checkout enhancements (PARKED — three related changes)**:
  1. **UAE-standard address structure**: replace the single free-text
     `delivery_address` field with structured fields matching how UAE
     addresses actually work — Emirate (dropdown: Abu Dhabi, Dubai,
     Sharjah, Ajman, Umm Al Quwain, Ras Al Khaimah, Fujairah), City/Area,
     Neighborhood, Street, Building name/number, Apartment or Villa
     number, and a Landmark field (very commonly used here since formal
     addressing is inconsistent). Worth also considering a **Makani
     number** field — UAE's official geo-addressing system, which many
     delivery services rely on for precise location. This needs new
     columns on `order_groups` (and probably `orders`, since that's
     where `delivery_address` currently lives per-line) — a straight
     schema migration, not a big structural change.
  2. **Make email mandatory at checkout**: currently only name + phone
     are collected. Needs a new `email` column on `order_groups` (doesn't
     exist yet) plus making it `required` in the checkout form.
  3. **Visitor accounts for returning customers**: bigger change — the
     app is currently deliberately account-free for visitors (session
     cart, guest checkout only). Real accounts need: a new `customers`
     table (kept separate from `users`, since customers are a different
     concept from staff/admin/captain — different auth flow, no role
     overlap), registration + login + password reset pages, an optional
     "create an account" checkbox at checkout (standard e-commerce
     pattern — account creation shouldn't block guest checkout), and
     linking `order_groups` to a `customer_id` when signed in so order
     history becomes possible. This is the largest of the three and
     probably deserves its own planning pass rather than being bundled
     in with the address/email changes.
- **Password change / forgot password** for staff accounts.
- **CSV export (PARKED)** — admin pages that list data (Customers, Trip
  Requests, and eventually Orders once that view exists) currently
  browse-only, no download. Straightforward to add: a plain PHP endpoint
  per page that sets `Content-Type: text/csv` headers and streams the
  same query already used for the on-screen table — no new dependency
  needed. Customers is the most immediately useful one, since it's what
  unlocks actually using the subscriber list in an external email/WhatsApp
  broadcast tool.
- **Admin order management view** — orders are created correctly by
  checkout, but there's no admin page yet to see/confirm/fulfill them.

## Security notes worth knowing

- Sessions use `httponly`, `samesite=Lax`, and `secure` cookies once
  `APP_ENV` is `production` (requires active HTTPS).
- All SQL goes through PDO prepared statements throughout.
- **Uploaded images (species/boats/catch photos) are stored OUTSIDE
  `public_html` entirely — at `UPLOADS_STORAGE_DIR`, defined in
  `config.php`.** This is critical, not optional: Hostinger's Git deploy
  resets `public_html` to exactly match the repository on every deploy,
  silently deleting any file that isn't tracked in git. Uploaded photos
  are never tracked in git (they're created live, by whoever uploads
  them) — so if they lived inside `public_html`, every redeploy would
  wipe them. This actually happened once during development before the
  fix. Images are re-encoded via GD before saving (strips any non-image
  payload someone might try to disguise as a photo) and served through
  `media.php`, which validates the requested filename against a strict
  allowlist before reading it.
- `config.php` is never committed (`.gitignore`) and is blocked at the
  web server level via `.htaccess`, same as `includes/`, `scripts/`, `sql/`.
- Checkout stock checks happen inside a transaction with `FOR UPDATE`
  row locking, so concurrent buyers can't oversell a limited catch.
