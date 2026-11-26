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

- By default, public reservations can be made up to 30 days ahead, in one- or two-hour durations. Owners can configure the booking window, minimum notice, and allowed durations. Start times are spaced by 30 minutes.
- The app stores times in UTC and displays them in the venue's chosen timezone. Prices are in Philippine pesos and stored in centavos. Changes to court rates apply to new reservations. A court-specific time-based rate overrides an all-court time-based rate, followed by the court's base rate. Bookings crossing a rate boundary are priced in 30-minute segments.
- Confirmed bookings and court blocks reserve time on that court. A cancellation releases time and the booking remains in the staff calendar. A staff member changing a booking cancels the original and creates a replacement. The app records payments made at the venue.
- The confirmation page belongs to the browser session that created the booking. Booking references and customer contact details are available to staff in the calendar.

## QR payments and Messenger verification

Under **Settings → Booking setup → Payments**, save your venue’s reusable PHP EMV merchant payload and its Facebook Messenger link. Confirm with your payment provider that the base code supports locally generated, amount-prefilled payments. Test the resulting QR in the actual wallet or banking apps the venue will accept before sharing it with players. A valid EMV checksum alone does not establish payment-provider compatibility.

When enabled, new online and staff reservations require QR payment. The app uses the booking’s exact total and preserves the base merchant routing and optional fields. Players submit their receipt and booking reference in Messenger. Staff match it against the payment received in the venue’s merchant account, then record the exact amount and the provider’s unique transaction reference under **Verify Messenger payment**.

An unpaid hold expires after 20 minutes or when the booking starts, whichever comes first. Expired holds release the slot and cannot be marked paid. If funds arrive late, reconcile the payment with the player in Messenger before arranging a refund or a different slot. The hold expiry controls the court reservation and does not revoke a QR image already saved by the player. Existing reservations and their payment workflow are preserved.

## Tests

Run `php artisan test` with PDO SQLite for the default feature suite. To also exercise InnoDB's behavior, create a **dedicated, disposable** MySQL test database and run the suite with `DB_CONNECTION=mysql` and the corresponding `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` exported in your shell. The test suite runs fresh migrations against the configured test database.

For hosting, point the web root at `public/`, set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` to the HTTPS hostname. Run `php artisan migrate --force` during deployment.
