# Vingrošanas Studija

A full-featured fitness studio booking and membership management platform built with Laravel, Livewire, and Stripe.
Designed for a Latvian fitness facility, it provides public-facing class booking, membership purchases, check-in, and a
complete admin dashboard.

## Features

### Public

- **Class Booking** — Multi-step wizard to browse services by category, pick a date/time slot, enter details, and pay
  via Stripe Checkout
- **Membership Purchases** — Select a membership tier, build a session schedule, and pay in a single checkout flow
- **Dynamic Pricing** — Price tiers based on participant count per service
- **Capacity Management** — Real-time availability checks with support for exclusive (private) and shared sessions
- **Check-In** — Self-service attendance registration by email on the day of class
- **Booking Cancellation** — Customers can self-cancel and receive automatic Stripe refunds (24h+ before class)
- **Email Notifications** — Booking confirmations, refund notices, and coach notifications
- **Contact Form** — With honeypot spam protection
- **Cookie Consent** — GDPR-compliant cookie banner

### Admin Dashboard

- **Coaches** — CRUD with image uploads and ordering
- **Services** — Manage service types, pricing tiers, exclusive/shared mode, membership eligibility
- **Schedules** — Recurring (day-of-week) and one-time schedules with capacity limits
- **Bookings** — Full management with search, filtering, payment/attendance status tracking, and refunds
- **Memberships** — View and manage customer memberships and linked sessions
- **Content Management** — Hero section, quotes, about page, media gallery, SEO metadata, contact info
- **Settings** — Profile, password, and two-factor authentication (TOTP)

### Payments

- **Stripe Checkout** — Secure hosted payment pages for bookings and memberships
- **Webhook Processing** — Handles `checkout.session.completed`, `checkout.session.expired`, and `charge.refunded`
  events with idempotency guards
- **30-Minute Payment Window** — Pending bookings auto-expire if not paid, freeing up capacity
- **Refunds** — Both admin-initiated and Stripe dashboard refunds are handled

## Tech Stack

| Layer           | Technology                                    |
|-----------------|-----------------------------------------------|
| Framework       | Laravel 12                                    |
| Frontend        | Livewire 4, Flux UI Pro v2                    |
| Styling         | Tailwind CSS v4                               |
| Payments        | Laravel Cashier (Stripe) v16                  |
| Auth            | Laravel Fortify (login, 2FA, password reset)  |
| Testing         | Pest 4                                        |
| Build           | Vite 7                                        |
| Database        | SQLite (default), MySQL/PostgreSQL compatible |
| Carousel        | Fancyapps UI                                  |
| Spam Protection | Spatie Laravel Honeypot                       |
| Code Style      | Laravel Pint, Prettier (Blade + Tailwind)     |

## Architecture

The application follows Laravel 12's streamlined structure:

```
app/
├── Actions/           # Stripe checkout sessions, refunds
├── Console/Commands/  # Expire pending bookings/memberships
├── Enums/             # PaymentStatus, AttendanceStatus, DayOfWeek
├── Exceptions/        # RefundNotAllowedException
├── Http/Controllers/  # Stripe webhook handler
├── Livewire/          # Settings components, shared form concerns
├── Mail/              # Booking/membership confirmation & refund emails
├── Models/            # Eloquent models
├── Providers/         # App, Fortify, Cookie providers
├── Services/          # Schedule availability logic
└── helpers.php        # Global site() helper for cached settings

resources/views/
├── components/        # Livewire inline components (⚡ prefix)
├── livewire/auth/     # Authentication views
├── mail/              # Email templates
└── welcome.blade.php  # Public homepage
```

Livewire components use the **inline component pattern** — PHP logic and Blade template live in a single
`⚡component-name.blade.php` file. Shared form logic is extracted into traits under `app/Livewire/Concerns/`.

### Key Business Logic

- **ScheduleAvailabilityService** — Calculates unavailable dates and available time slots based on schedule type,
  capacity, and existing bookings
- **CreateStripeCheckoutSession / CreateMembershipCheckoutSession** — Build Stripe Checkout sessions with proper
  metadata for webhook processing
- **RefundBooking** — Validates refund eligibility (24h rule, paid status) and processes via Stripe API
- **SiteSetting** — Key-value content store with group-based caching (`Cache::rememberForever`) for admin-managed
  content

### Scheduled Tasks

| Command                      | Interval    | Purpose                                                      |
|------------------------------|-------------|--------------------------------------------------------------|
| `bookings:expire-pending`    | Every 5 min | Deletes pending bookings past their 30-min payment window    |
| `memberships:expire-pending` | Every 5 min | Deletes pending memberships past their 30-min payment window |

## Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm
- [Laravel Herd](https://herd.laravel.com/) (recommended) or any PHP server
- Stripe account with API keys and webhook endpoint

## Setup

1. **Clone the repository**

   ```bash
   git clone <repository-url>
   cd vingrosanas-studija
   ```

2. **Run the setup script**

   ```bash
   composer setup
   ```

   This installs dependencies, copies `.env.example` to `.env`, generates the app key, runs migrations, and builds
   frontend assets.

3. **Configure environment variables**

   Edit `.env` and set:

   ```env
   STRIPE_KEY=pk_...
   STRIPE_SECRET=sk_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   CONTACT_EMAIL=your@email.com
   MAIL_MAILER=smtp
   # ... configure your mail driver
   ```

4. **Create an admin user**

   ```bash
   php artisan app:create-user
   ```

5. **Start the development server**

   ```bash
   composer run dev
   ```

   This runs the Laravel server, queue worker, log viewer (Pail), and Vite dev server concurrently.

6. **Configure Stripe webhook**

   Point your Stripe webhook endpoint to `https://your-domain/stripe/webhook` and subscribe to:
    - `checkout.session.completed`
    - `checkout.session.expired`
    - `charge.refunded`

7. **Set up the scheduler** (production)

   Add to your crontab:

   ```
   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
   ```

## Development

```bash
# Run tests
php artisan test --compact

# Run a specific test
php artisan test --compact --filter=BookingPaymentTest

# Format PHP code
vendor/bin/pint

# Format Blade/CSS
npm run format
```

## License

MIT
