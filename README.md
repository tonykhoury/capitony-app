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

- **Live viewer presence**: separate from chat — shows admin/captain a
  live count and named list of who's actually watching a broadcast right
  now, so the captain can call people out by name during the stream
  ("thanks for tuning in, Sarah!"). Deliberately broader than chat
  participation: any visitor with the live page open counts, not just
  people who've typed something. Reuses the same visitor-identity cookie
  as chat (`get_visitor_chat_name()`) — a name set for chat automatically
  shows up here too, no separate identity system. Visitors heartbeat via
  `presence-ping.php` every 20 seconds; "active" means seen within the
  last 60 seconds (`LIVE_VIEWER_ACTIVE_WINDOW_SECONDS`). Shown on both
  `/captain/chat.php` and the admin dashboard, both polling the shared
  `/live-viewers-data.php` endpoint (staff-only — checks the session role
  server-side, never trusts the page it's called from).
- **Live session chat (visitor ↔ captain, text and voice)**: shown on
  the homepage live player for visitors, and on the captain's
  catch-posting page and dedicated `/captain/chat.php`. Persists for the
  **whole trip**, not just while actively streaming — a captain often
  starts/stops streaming multiple times during one trip, and the chat
  conversation shouldn't vanish between bursts. Visibility is based on
  `trips.status != 'completed'`, not `live_sessions.status`; the video
  player itself still only shows while a session's `status = 'live'`
  (shows a "not streaming right now, chat below" placeholder otherwise).
  Marking the trip complete is what actually resets things for the next
  trip. Polling-based (checks for new messages every 4 seconds — no
  websocket/SSE server needed for this message volume). Voice notes
  recorded in-browser via the `MediaRecorder` API, uploaded through the
  same safe-storage pattern as photos/videos (outside `public_html`,
  served via `media.php`) — accepts `video/webm` in addition to
  `audio/webm` since libmagic frequently misidentifies audio-only WebM
  recordings that way. Visitor identity (name) is remembered via a
  cookie, no account needed; captain identity requires BOTH a verified
  captain session AND the widget explicitly declaring captain context
  (`as_captain=1`) — checking the session alone let an ambient captain
  login in one browser tab bleed into the public visitor widget in
  another. `chat-send.php` skips CSRF checking deliberately — it's
  called from a logged-out page via `fetch()`, and a same-origin chat
  message has low enough stakes that the UX cost of a token roundtrip
  isn't worth it; revisit if spam becomes a real issue. **Nothing is
  ever deleted** — full permanent history across every session, browsable
  at `/admin/chat-history.php`.
- **Social media presence**: Instagram feed embedded on the homepage
  ("Follow the Boat" section) via Elfsight — deliberately a widget, not
  a custom Meta API integration. Matches how comparable fishing charter
  businesses actually operate: post natively to Instagram first (fits
  the real on-boat workflow), let the website auto-reflect it. No OAuth
  tokens, no App Review, no maintenance on our side — the vendor handles
  all of that. A full custom build (auto-publish uploads + webhook-based
  comment sync) was researched and deliberately not pursued; git history
  around this decision has the design notes if it's ever revisited.
- **Zoho Books invoice automation — PAYMENT LINK FIRST**: fires when an
  order is marked **"confirmed"** (admin or captain), not at checkout.
  Reversed from the original design on purpose: **the real invoice is
  never sent up front.** Instead:
  1. A **draft** invoice is created in Zoho (never auto-emailed at this
     stage) and its own hosted payment-page URL is sent to the customer
     via WhatsApp — `order_groups.zoho_payment_url`.
  2. `scripts/zoho-payment-poll.php` (CLI-only, meant to run every ~15
     min via **Hostinger's Cron Jobs feature** — hPanel → Advanced →
     Cron Jobs) checks every order still awaiting payment. The moment
     Zoho shows an invoice as `paid`, it triggers Zoho to actually
     **email the real invoice**, flips `zoho_invoice_delivered`, and
     sends a WhatsApp payment confirmation.
  - **Polling, not a webhook** — Zoho's webhook support for Books
    specifically is genuinely unclear from current public docs (sources
    conflict); polling is what we can be certain works. Revisit with a
    webhook later only if confirmed reliable in testing.
  - **Unverified field name, flagged deliberately**: the exact Zoho API
    field for an invoice's payment-page URL couldn't be confirmed
    without live access to a real response. The code tries the most
    likely field name and falls back to storing the full raw response in
    `zoho_raw_response` — check that column after the first real test
    order if the WhatsApp link doesn't show up, same troubleshooting
    pattern that resolved the WhatsApp template fields earlier.
  - Uses a **Self Client** (Zoho's recommended pattern for a backend job
    acting on your own account, no live user present) with the
    Authorization Code flow — the alternative Client Credentials flow
    doesn't issue a refresh token at all.
  - Matches or creates a Zoho contact by email, then the draft invoice
    uses ad-hoc line items (fish + clean/cook fees + delivery, no
    pre-mapped item catalog needed).
  - Idempotent throughout — a retry, or confirming twice, never
    double-invoices or double-sends the payment link.
  - Silently does nothing if `ZOHO_CLIENT_ID` is still the placeholder
    value, so it's safe to deploy before setup is finished.
- **Engine hours tracking**: captain enters starting engine hours when
  clicking "Start Trip" and ending hours when clicking "Mark Trip
  Complete" (`trips.start_engine_hours` / `end_engine_hours`, both
  required to proceed — validates ending hours can't be less than
  starting). Visible on the captain's live trip card as a running
  reference, and on `/admin/trips.php` showing total hours used per
  trip — groundwork for maintenance-interval tracking later.
- **Captain expense logging + Zoho sync**: `/captain/expenses.php` —
  fuel/bait/gear/maintenance/other, amount, optional description and
  receipt photo (same safe-storage upload pattern as everything else).
  Auto-links to the captain's current live trip if there is one.
  Syncs to Zoho Books as a real Expense record immediately on logging.
  **Account mapping is fetched live from Zoho's own Chart of Accounts**
  (`/admin/zoho-expense-settings.php`) rather than requiring anyone to
  dig up raw account IDs — admin picks real account names from a
  dropdown, one per category, plus a single "paid through" account
  (e.g. Cash or Petty Cash). Won't sync until that mapping exists —
  fails with a clear, specific error rather than guessing at an account,
  since posting to the wrong one is worse than not posting. Receipt
  photos get attached to the Zoho expense too, best-effort. Full
  overview with sync status and manual retry at `/admin/expenses.php`.
- **Captain order confirmation**: `/captain/orders.php` was previously
  view-only; captains can now confirm an order (verified server-side
  against their own trips before allowing it, never trusting a
  submitted order id alone) directly from the harbor-sorting view,
  triggering the same Zoho invoice flow as an admin confirming it.
- **Admin order management**: `/admin/orders.php` (list, filterable by
  status) and `/admin/order-detail.php` (line items, status updates —
  pending/confirmed/fulfilled/cancelled, kept in sync between
  `order_groups` and its `orders` line items).
- **Admin metrics dashboard**: `/admin/metrics.php` — revenue by day
  (last 30 days, grouped in Dubai local time so a late-night order
  doesn't land on the wrong calendar day), best-selling species,
  sell-through rate by trip (posted vs. sold weight), captain activity.
- **CSV export**: Customers, Trip Requests, and Orders each have an
  "Export CSV" button on their admin page, streaming the same query
  already used for the on-screen table.
- **Staff password management**: self-service change for both admin
  and captain (`/admin/change-password.php`, `/captain/change-password.php`),
  plus admin-assisted reset for a locked-out captain from `/admin/captains.php`.
- **UAE-standard checkout address + mandatory email**: Emirate, City,
  Neighborhood, Street, Building, Apartment/Villa, optional Landmark and
  Makani number — collected only when at least one cart item is set to
  deliver. Email is required on every order regardless of delivery method.
- **Visitor accounts (optional — guest checkout still fully works)**:
  register/login at `/account/`, with an order history dashboard. At
  checkout, a logged-in customer's details are pre-filled and their
  order is linked via `customer_id`; a guest can optionally check
  "create an account" to register using the same details they just
  typed, without it ever blocking guest checkout if they skip it.
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

## Setup — scheduled tasks (Hostinger Cron Jobs)

`scripts/zoho-payment-poll.php` needs to run automatically on a schedule
for the payment-link-first Zoho flow to actually deliver invoices once
paid. In hPanel → Advanced → **Cron Jobs**, add a new job:
- **Command**: `php /home/YOUR_USERNAME/domains/capitony.live/public_html/scripts/zoho-payment-poll.php`
  (adjust the path to match your actual account username/domain path)
- **Frequency**: every 15 minutes is reasonable — frequent enough that
  customers aren't waiting long after paying, not so frequent it wastes
  API calls checking orders that haven't changed

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
  Safari's native HLS support. **Stream key belongs to the boat, not the
  session** (`boats.stream_key`, set once in Boats admin) — "Go Live" is
  a pure database toggle that never regenerates the key, so Larix (often
  running on separate hardware from whatever the captain clicks "Go
  Live" on) only ever needs configuring once, not before every trip.
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
  **Self-service management**: `/my-alerts.php` — look up by phone
  number (no login needed, matching the guest-first pattern everywhere
  else), pause/resume/remove. Every action re-verifies phone ownership
  server-side before touching a row — never trusts an id shown on the
  page alone. Editing species/weight goes through `/edit-alert.php?token=...`,
  reusing the same `unsubscribe_token` already generated at signup as a
  lightweight per-alert access key.
- **Trip booking**: upcoming trips shown on the homepage and a full
  `/trips.php` listing, with a request-to-join form (name, phone, seat
  count). Seats remaining accounts for pending *and* confirmed requests,
  so the site never shows more availability than actually exists. Admin
  manages incoming requests (confirm/decline) at `/admin/trip-requests.php`.
- **Icebreaker roster (consent-gated)**: the trip request form also
  collects optional hobbies, fishing style, years of experience, and
  countries fished. A clearly-worded, standalone consent checkbox
  (unchecked by default — never assumed) controls whether that info +
  name gets shared with *other* confirmed guests on the same trip;
  leaving it unchecked never blocks joining the trip itself, it just
  excludes that person from what fellow guests see. Admin triggers
  **Send Roster** per trip once enough people are confirmed — generates
  a per-trip token (`trips.roster_token`), builds `/trip-roster.php?token=...`
  (shows only confirmed *and* consenting attendees, never anyone else),
  and sends each of them just the link via WhatsApp — same "send a link,
  not the content" pattern as catch alerts and payment links, and a much
  better fit than trying to cram a multi-person roster into WhatsApp's
  2-variable template constraint.
- **Visitor shop**: browse live catch listings with photos and Arabic
  names, add to cart with a quantity
- **Cart**: session-based (no visitor accounts). Set pickup/delivery +
  clean/cook once and apply to every item in the cart with a clear warning
  about what that does, or override any single item afterward
- **SKU per catch**: format is `{BOAT-CODE}-{YYMMDD}-{sequence}`, e.g.
  `TN2-260722-01` — meaningful, not just unique. The sequence counts
  across ALL of that boat's trips on that calendar day (not just one
  trip), specifically to avoid two same-day trips both starting at `01`
  and colliding. Boat codes are set in **Boats** admin (2-10
  letters/numbers, unique). Shown on the captain's postings table, the
  shop PLP, cart, checkout, and stored directly on `orders.sku` too
  (denormalized on purpose) so fulfillment tooling can query by SKU
  without a join. Captains can print a label per fish (species, weight,
  SKU, QR code) from `/captain/print-label.php?id=X`. Older catches
  posted before this format existed keep their original `CAP-000123`
  style SKU — never retroactively renumbered, since some may already be
  on printed labels.
- **Captain order view** (`/captain/orders.php`): shows every order
  against a captain's own trips for a chosen date (defaults to today),
  sorted by SKU — built specifically so a captain can match the SKU
  printed on each fish's label against this list back at the harbor and
  know immediately what needs pickup, delivery, cleaning, or cooking.
- **Checkout**: re-verifies stock inside a DB transaction (so two people
  can't both buy the last kilo of grouper), creates an `order_groups`
  receipt plus one `orders` line per fish, decrements remaining stock
- **Service pricing**: admin sets Clean/Cook price per kg and a flat
  Delivery fee per order under **Service Pricing**. Recalculated
  server-side at checkout from current settings (never trusted from the
  cart session), and the actual amount charged is stored on the order —
  changing a price later never rewrites past orders.

## What's stubbed, not fully built yet

- **Card payment integration (PARKED)**: currently no payment collection
  anywhere in the app — checkout just places the order. Researched two
  real options:
  1. **(Recommended) Leverage Zoho Books' native payment gateways** —
     Zoho Books has built-in integrations with UAE-native gateways
     (**Telr**, **PayTabs** — both process AED directly, local support)
     plus international ones (Stripe, Authorize.Net). Once connected in
     Zoho Books → Settings → Online Payments, **every invoice
     automatically gets a "Pay Now" button**, and Zoho auto-reconciles
     the invoice as paid when the customer pays — no new payment code on
     our side. Only remaining build: when `sync_order_to_zoho()` creates
     an invoice, grab its payment link from the API response and send it
     to the customer via WhatsApp (reuses the existing Twilio
     integration). Small, contained addition, not a new subsystem.
  2. **Direct gateway integration into checkout.php** — the traditional
     e-commerce pattern (customer pays *before* the order is reviewed,
     via a gateway's hosted checkout embedded at the point of placing
     the order). Bigger build, and changes the business flow — payment
     would happen before admin/captain confirmation rather than after,
     which conflicts with the current confirm-then-invoice model unless
     that model changes too.
  - Either way: **card numbers should never touch our own PHP app** —
    always a gateway-hosted page/redirect, never a custom capture form.
    That's what keeps this out of full PCI-DSS scope, which is a real
    and heavy burden for a business this size to take on directly.
  - Unrelated side-finding, not urgent: UAE is introducing mandatory
    e-invoicing (PINT AE format via Peppol) starting January 2027, but
    only for businesses with revenue ≥ AED 50 million — nowhere near
    relevant at current scale, just worth knowing exists.
- **WhatsApp-native live chat (PARKED)**: let visitors chat with the
  boat directly via WhatsApp instead of (or alongside) the web widget on
  `/shop.php`. Confirmed feasible via Twilio's actual docs — design notes:
  - Twilio Sandbox supports a "When a Message Comes In" webhook,
    configured in Sandbox settings, pointing at a URL on our server.
    Every inbound WhatsApp message to the sandbox number POSTs there
    with sender phone, WhatsApp profile name (free name capture — nicer
    than asking visitors to type one), message text, and a media URL to
    download for voice notes.
  - Single shared sandbox number = no way to know which live session a
    message is about. Simplest approach: attribute every inbound message
    to whichever `live_sessions` row is currently `status = 'live'` —
    valid *only* because there's one boat and the app already enforces
    just one session live at a time (see the fix in `captain/dashboard.php`'s
    `go_live` handler). Revisit if that assumption ever changes.
  - Only one webhook URL per number — if this gets built, it needs to be
    a general inbound router from day one (any future two-way traffic on
    this number, like replies to catch alerts, lands in the same place).
  - One genuine simplification vs. catch alerts: WhatsApp doesn't require
    a pre-approved template for *replies within 24 hours of the customer
    messaging first* — only for business-*initiated* contact. Since chat
    is inherently visitor-initiated, the captain's replies could go out
    as plain freeform text via Twilio's regular Messages API, no
    Content SID / template mapping needed like the alerts flow has.
  - Still needs the sandbox join-code opt-in step, same friction as
    catch alerts today, until a real WhatsApp Business sender exists.
  - Open decision for whenever this is picked up: replace the web widget
    entirely, or run both channels into the same `chat_messages` feed
    (would need a `channel` column to know where to send captain replies
    back to — WhatsApp API call vs. just leaving it in the web feed).

- **Forgot-password for visitor accounts**: staff (admin/captain) get
  admin-assisted resets since there's already a human on the other end.
  Customers don't have that — a real "forgot password" flow needs email
  sending (a reset-token link), which means setting up SMTP or a
  transactional email service (Postmark, SES, etc.) — nothing in the app
  sends email at all yet. Worth bundling with any future email-based
  feature (order confirmation emails, etc.) rather than building the
  mail infrastructure just for this one flow.

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
