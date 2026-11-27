# Pickleball Lite

A Laravel 12 and MySQL app for a single pickleball venue with multiple courts. Players reserve a court from a public link and pay at the venue. Staff use one calendar for online reservations, walk-ins, payment status, and court blocks.

## Run locally

Requires PHP 8.4 with PDO MySQL, Composer 2, and MySQL 8 with InnoDB. See [Laravel's extension requirements](https://laravel.com/docs/12.x/deployment#server-requirements).

1. Create an empty MySQL database and a database user with access to it.
2. From the repository root, run:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

3. Set the `DB_*` values in `.env` to match your database. Then run:

   ```bash
   php artisan migrate --seed
   php artisan venue:create-owner owner@example.com --name="Venue Owner"
   php artisan serve
   ```

   The owner command prompts for a password. The seed adds one venue in Asia/Manila with three courts and sample rates and hours.

4. Open [public booking](http://localhost:8000/) or [staff sign in](http://localhost:8000/staff/login). Sign in as the owner and open **Settings** to set the venue's details, hours, base court rates, and staff accounts. Open **Booking setup** for branding, time-based rates, holiday closures, booking rules, and a downloadable booking QR code.

## Booking rules

- By default, public reservations can be made up to 30 days ahead, in durations from one to five hours in 30-minute increments. Owners can configure the booking window, minimum notice, and allowed durations. Start times are spaced by 30 minutes.
- The app stores times in UTC and displays them in the venue's chosen timezone. Prices are in Philippine pesos and stored in centavos. Changes to court rates apply to new reservations. A court-specific time-based rate overrides an all-court time-based rate, followed by the court's base rate. Bookings crossing a rate boundary are priced in 30-minute segments.
- Confirmed bookings and court blocks reserve time on that court. A cancellation releases time and the booking remains in the staff calendar. A staff member changing a booking cancels the original and creates a replacement. The app records payments made at the venue.
- The confirmation page belongs to the browser session that created the booking. Booking references and customer contact details are available to staff in the calendar.

## QR payments and Messenger verification

Under **Settings → Booking setup → Payments**, save your venue’s PHP EMV merchant payload and its Facebook Messenger link. Confirm with your payment provider that the base code supports locally generated, amount-prefilled payments. Test the resulting QR in the actual wallet or banking apps the venue will accept before sharing it with players. A valid EMV checksum alone does not establish payment-provider compatibility.

When enabled, new online and staff reservations require QR payment. The app uses the booking’s exact total and preserves the base transaction marker, merchant routing, and optional fields. Players submit their receipt and booking reference in Messenger. Staff match it against the payment received in the venue’s merchant account, then record the exact amount and the provider’s unique transaction reference under **Verify Messenger payment**.

An unpaid hold expires after 20 minutes or when the booking starts, whichever comes first. Expired holds release the slot and cannot be marked paid. If funds arrive late, reconcile the payment with the player in Messenger before arranging a refund or a different slot. The hold expiry controls the court reservation and does not revoke a QR image already saved by the player. Existing reservations and their payment workflow are preserved.

## Tests

Run `php artisan test` with PDO SQLite for the default feature suite. To also exercise InnoDB's behavior, create a **dedicated, disposable** MySQL test database and run the suite with `DB_CONNECTION=mysql` and the corresponding `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` exported in your shell. The test suite runs fresh migrations against the configured test database.

For hosting, point the web root at `public/`, set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` to the HTTPS hostname. Run `php artisan migrate --force` during deployment.


## Open Play

- Staff can publish a one-time session or a bounded series of up to 30 daily or weekly occurrences, assign active courts, and set a per-player price, capacity and skill level. Publishing checks the venue hours, existing reservations, holds and blocks for every occurrence; a conflicting occurrence rejects the entire series.
- Assigned courts are blocked until the session ends or is cancelled. Staff must cancel a session through Open Play, not remove individual court blocks.
- Players choose a date and session and register individually. The venue must enable QR payments and configure its merchant QR and Messenger link. An unpaid player spot is held up to 20 minutes, bounded by the session start for online registrations and the session end for staff walk-ins.
- Staff match the actual incoming payment with the Messenger receipt, verify the exact fee and record its unique reference. The same reference cannot be reused for a court booking or an Open Play registration. Paid players can be checked in manually.
- Staff can cancel upcoming sessions and individual registrations; any refunds or late payments must be reconciled manually in Messenger. Existing payment records are retained. No automatic player rotations or refunds are included.
- Do not run a destructive migration rollback against a production database containing Open Play registrations. Roll back application code only after accounting for published sessions and their linked court blocks.


## Venue website

The public root URL displays the branded venue website. The direct booking URL is `/book`, where players choose a private court reservation or Open Play. Recognized legacy booking query parameters on the old root URL redirect to `/book`; existing payment and Open Play URLs are unchanged.

Owners can edit website copy, confirmed facilities, up to five FAQs, SEO metadata and six gallery photos in Staff > Website settings. The website reads existing logo and cover images, venue details, active courts, weekly hours, closures, effective court-specific and time-based rates, booking rules and upcoming Open Play sessions. Rates and session availability are checked again at booking.

### Gallery and FAQs

A new venue with no uploaded gallery photos displays five clearly labeled AI-generated demo images (1200×900 WebP). Once an owner uploads a real gallery photo, the entire illustrative gallery is replaced by the actual photos. Owners can also disable the fallback in Website settings. The image viewer supports previous/next buttons, arrow keys and Escape. Default FAQs adapt to QR payment setup, booking durations, advance notice and cancellation policy; owners can add five custom FAQs. The demo assets are stored as base64 WebP resources and served as binary WebP responses through an allowlisted route.


## Management authentication

The canonical sign-in page is `https://app.picklelite.cloud/login`. The original staff login URL remains available for compatibility. Platform admins open the platform dashboard; a member of exactly one venue opens its calendar; multiple or zero venue memberships open the venue selector. Same-origin protected workspace and invitation links take priority after sign-in. The optional Remember me checkbox on the management host issues a 30-day host-only remember cookie. Normal logout revokes the remember token.

Password recovery is disabled by default. To enable it, configure and verify SMTP on **pickle-app** using `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` and the provider's appropriate `MAIL_SCHEME`; then set `PICKLE_PASSWORD_RESET_ENABLED=true` and redeploy. Do not enable the flag with the log mailer. Reset links are issued on the management host, expire after 60 minutes and are single-use. Successful resets revoke the remembered token and stored management sessions. Public bookings do not require player accounts. New staff and owners still join by private invitation.


## Legal policies and privacy requests

The platform administrator can open `/admin/legal` on the management domain. Four initial policy drafts are created by the legal migration; they are not public or effective by default. Review and save each draft, enter the actual registered operator and contact information, retention periods and approved commercial conditions, and explicitly authorize publication. The system publishes an immutable version of each document as one bundle. Current policies are served on the marketing domain at `/terms`, `/privacy`, `/cookies`, and `/subscription-terms`; append `?version=N` to view archived published versions.

Before publication, new bookings and existing accounts remain operational. After publication, online bookings and Open Play registrations require an unchecked terms checkbox plus an exact current-version fingerprint that includes the venue cancellation policy. Acceptance and notice-presentation evidence is recorded transactionally, without recording privacy notice presentation as marketing consent. Existing reservations preserve their own cancellation text and published document versions. Existing management users review new platform terms; owner invitations additionally collect venue subscription acceptance for that venue. Existing subscription agreements are not silently replaced.

Venue-specific policies are at `/{venueSlug}/policies` on the public booking domain. Update cancellation text under Booking setup. The super admin and venue owners can manually record and track privacy requests under `/admin/privacy-requests` and `/venues/{venueSlug}/privacy-requests`. Identity verification, access/correction/export/deletion, incident handling, and actual retention enforcement are operational procedures, not automated by the tracker. Do not enter unnecessary personal information. Before publishing, confirm applicable Philippine legal requirements and the actual operator/venue controller and processor arrangements with a qualified professional. No tracking or cookie advertising opt-in is introduced.

Only management runs the additive legal migration; the web and booking services wait for schema readiness via `platform:check-schema`. Historical payment links are not gated on acceptance.


## Self-service SaaS rollout

Registration is disabled by default. Enable only after reviewing and publishing all four legal policies (including the new free trial commercial draft), configuring working SMTP on pickle-app, and verifying actual email delivery. Set PICKLE_SELF_REGISTRATION_ENABLED=true on pickle-app and pickle-web after those steps. Do not enable registration with the log mailer. New owners verify their email using an expiring signed link; staff and manager invitations are unchanged.

Trial access belongs to a venue. Verified signup starts 30 days; owners can extend by 15 days during each last-five-day window, up to three times (75 days total). The endpoint uses a transactional expected extension count to reject duplicate submissions. Existing venues retain existing access. Expiry blocks new reservations and Open Play sessions without deleting records or blocking existing payment and confirmation links. Paid conversion is a manual request and verified subscription ledger operation, not automatic charging. The standard plan is PHP 1,000 per venue per month, with free account creation, standard setup and activation. No new setup payments can be recorded. The forward-only pricing migration sets the legacy setup charge to zero for existing and new subscriptions and updates known stock legal draft wording, without changing historical payments, published policy versions or acceptance records. Before enabling registration or inviting new owners under the revised offer, review `/admin/legal`, remove any obsolete pricing in customized drafts and commercial conditions, and explicitly publish the revised legal bundle.

Run php artisan platform:notifications every five minutes in a private Railway cron service sharing the database. It reconciles trial expiry, renewal reminders and recent expired payment holds. In-app notifications are recorded within business transactions or generated by the scheduled sweep and deduplicated. Email delivery beyond verification is not enabled. Only management runs migrations.
