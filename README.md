# Instructor Revenue Ledger

Financial core for a multi-instructor learning platform (Career 180 Hiring Quest). Students pay subscription fees upfront; revenue is recognized over the access period; instructor shares are allocated from engagement; payouts run through a queued, idempotent pipeline with an unreliable mock provider; standard unused-days refunds are supported; and Filament provides read-only financial visibility plus a controlled refund action.

## What this project is

- **Subscription payments** recorded in integer minor units (MySQL source of truth)
- **Daily revenue allocation** as the official earning path (`revenue:allocate --date`)
- **Engagement-weighted** instructor pool split using `valid_watched_seconds` and Largest Remainder Method rounding
- **Append-only instructor ledger** with balance projections (`earning_credit`, `payout_debit`)
- **Safe monthly payouts** via Artisan command + Redis queue jobs, with duplicate prevention and timeout reconciliation
- **Standard unused-days refunds** (CLI + Filament **Refund Unused Days**)
- **Filament admin** (admin-only): instructor balances, subscription financial summary, financial dashboard widgets
- **Student portal** placeholder at `/student` (out of scope — no financial screens)
- Pest tests covering money math, idempotency, refunds, payouts, admin access, and dashboard widgets

## What this project is not

- Not a full LMS (no course catalog, video player, or heartbeat tracking)
- Not a student dashboard or enrollment portal (`/student` is a placeholder only; financial screens are **admin-only**)
- Not a real payment gateway integration
- Not a real payout provider integration
- Not a tax / VAT module
- Not multi-currency FX conversion
- Not Laravel Sail — uses **custom Docker Compose**
- Not event sourcing — balances are projections; ledger is append-only

## Tech stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 11, PHP 8.3 |
| Database | MySQL 8 (financial source of truth) |
| Queue / cache | Redis (jobs and cache only) |
| Admin UI | Filament v3 (Livewire v3 under the hood) |
| Tests | Pest 3 |
| Containers | Custom Docker Compose (`app`, `nginx`, `mysql`, `redis`, `node`) |

## Core financial concepts

| Term | Meaning |
|------|---------|
| **Student paid amount** | Total succeeded payment for a subscription (cash received on day one) |
| **Earned revenue** | Portion of payment recognized for elapsed access days (via daily or legacy monthly allocation) |
| **Instructor pool** | Share of earned revenue for instructors (`earned × instructor_share_bps / 10000`) |
| **Allocated amount** | Instructor pool split by engagement weights → `revenue_allocations` + `earning_credit` ledger entries |
| **Paid amount** | Instructor cash paid out after **confirmed** provider success (`payout_debit`) |
| **Outstanding amount** | Allocated earnings not yet paid (`total_earned − total_paid` on balance projection) |
| **Unearned amount** | Payment not yet earned or refunded (`paid − earned − refunded`, floor 0) |
| **Refunded amount** | Completed standard refund total for unused future days |
| **Remaining refundable** | Preview of unused future days still refundable (0 after refund or after subscription ends) |
| **Unallocated instructor pool** | Contractual instructor pool for elapsed days with no engagement allocation |
| **Platform contractual share** | Platform share of earned revenue (`earned − instructor pool`) |
| **Total platform retained** | `platform contractual share + unallocated instructor pool` |

All stored amounts are **integer minor units**. Display formatting (e.g. `300.00 EGP`) is for UI only — no floats in business logic.

## Main business assumptions

1. **Upfront payment** — Students pay for monthly, 3-month, or annual plans; cash is received when payment succeeds.
2. **Revenue over time** — Payment ≠ fully earned on day one; revenue is earned gradually over the subscription access period.
3. **Daily allocation (official)** — `revenue:allocate --date=YYYY-MM-DD` allocates one **completed elapsed** calendar day.
4. **Monthly allocation (legacy only)** — `revenue:allocate --month=YYYY-MM` retained for feature 001 backward compatibility; **must not** be mixed with daily allocation in the same calendar month.
5. **Allocation ≠ payout frequency** — Allocation can run daily; payouts remain batch/monthly via `payouts:run`.
6. **Elapsed days only** — Future unearned days are never allocated to instructors.
7. **Standard refunds** — Apply only to **unused future days**; cancellation day counts as **used**; refund period starts the **next** calendar day.
8. **Pre-refund allocation** — Before refund, all unallocated elapsed days through cancellation day (inclusive) are daily-allocated.
9. **No instructor reversal (standard)** — Future unused days were never allocated, so standard refunds do not create `earning_reversal` or `clawback` entries.
10. **Exceptional refunds (future)** — Chargebacks, goodwill on used days, disputes → append-only `earning_reversal` or `clawback` (documented, not implemented).
11. **Payouts pay allocated outstanding only** — Provider success is the **only** event that moves outstanding → paid.
12. **Timeout = unknown** — `pending_confirmation` payouts are reconciled later; no `payout_debit` until confirmed.

## Architecture overview

```
Payment (cash) → Subscription active
    → Daily allocation (elapsed day only)
        → Revenue recognition + engagement split
        → revenue_allocations (idempotent)
        → earning_credit ledger entry (append-only)
        → instructor balance projection (outstanding ↑)
    → payouts:run (monthly batch)
        → ProcessInstructorPayoutJob (Redis queue)
        → MockPayoutProvider (success / failure / timeout)
        → On confirmed success only: payout_debit → paid ↑, outstanding ↓
    → Standard refund (optional)
        → Ensure elapsed days allocated (daily)
        → Refund unused future days only
        → refunds record (no ledger reversal)
```

**Key safety mechanisms**

| Mechanism | Role |
|-----------|------|
| **Append-only ledger** | Financial history is never updated or deleted |
| **Balance projections** | `instructor_balances` derived from ledger; not source of truth |
| **Idempotency keys** | Payments, allocations, ledger entries, refunds deduplicated |
| **AllocationModeGuardService** | Blocks daily + monthly allocation in the same calendar month |
| **`active_snapshot_key`** | MySQL unique nullable column prevents duplicate active payouts per balance snapshot |
| **Provider outside transactions** | External payout calls never hold DB locks |
| **`payouts:reconcile`** | Resolves `pending_confirmation` without re-sending payout |

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for deeper design notes.

## Setup

**Prerequisites:** Docker and Docker Compose.

```bash
docker compose up -d --build
docker compose exec app git config --global --add safe.directory /var/www/html
docker compose exec app composer install
cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec node npm install
docker compose exec node npm run build
```

### Environment (inside Docker)

| Variable | Value |
|----------|-------|
| `DB_HOST` | `mysql` |
| `DB_DATABASE` | `instructor_ledger` |
| `QUEUE_CONNECTION` | `redis` |
| `CACHE_STORE` | `redis` |
| `REDIS_HOST` | `redis` |
| `REDIS_PORT` | `6379` (container network) |
| `APP_URL` | `http://localhost:8080` |

**Note:** Host-exposed ports may differ (`nginx` → `8080`, MySQL → `3307`, Redis → `6380`). Inside the `app` container always use `mysql` and `redis:6379`.

All `php artisan` and `composer` commands run inside the **app** container:

```bash
docker compose exec app php artisan ...
```

### Demo seed data

`DemoFinancialCoreSeeder` creates:

| Entity | Details |
|--------|---------|
| Admin | `admin@demo.local` / `password` |
| Student | `student@demo.local` / `password` |
| Plan | Monthly Pro — 30,000 minor (300.00 EGP), 60% instructor share |
| Subscription | 2026-01-01 → 2026-01-30 |
| Payment | 30,000 EGP succeeded |
| Instructors | A, B, C with `valid_watched_seconds` 3600 / 1800 / 600 |

After **legacy monthly** allocation for January 2026, expected instructor earnings: A=10,800, B=5,400, C=1,800 minor (pool 18,000). Official demos should use **daily** allocation instead.

### Rich demo seed (optional)

The default seed (`migrate:fresh --seed`) is **lightweight** — one student, one subscription, January 2026 dates, EGP currency. Use it for quick setup and tests.

For dashboard screenshots, video demos, and manual exploration, load the optional rich dataset:

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan db:seed --class=RichFinancialDemoSeeder
```

`RichFinancialDemoSeeder` adds:

- Multiple students and instructors
- Monthly, quarterly, and annual plans (EGP)
- Subscriptions anchored around the **current real date** (recent months)
- Active, expired, cancelled, and refunded subscriptions
- At least one no-engagement subscription and one multi-instructor subscription
- Enough consumption and payout variety for top-instructor widgets and pipeline stats

After seeding, run daily allocation for any new elapsed days:

```bash
docker compose exec app php artisan revenue:allocate --date=YYYY-MM-DD
```

## Running tests

```bash
docker compose exec app php artisan test
```

**Current result:** 54 tests passing, 186 assertions.

Tests bind `FakePayoutProvider` (deterministic). The demo app uses `MockPayoutProvider` (random outcomes) via `AppServiceProvider`.

## Official daily allocation

Allocates **one completed elapsed calendar day**:

```bash
docker compose exec app php artisan revenue:allocate --date=2026-01-04
```

- Rejects today and future dates (day must be fully elapsed)
- Defaults to **yesterday** when `--date` is omitted
- Idempotent — rerunning the same date does not duplicate allocations or ledger entries
- Engagement filtered to `DATE(consumed_at) = date`
- `AllocationModeGuardService` blocks if monthly allocations exist for that month

## Legacy monthly allocation

Backward compatibility from feature 001 — **not** the official demo path:

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
```

Do **not** run daily and monthly allocation for the same calendar month. `AllocationModeGuardService` enforces mutual exclusion in both directions.

## Refund flow

**Filament (primary):** Finance → **Subscriptions** → view → **Refund Unused Days**

- Uses **today** automatically as the cancellation date (no date picker)
- Shows read-only preview: cancellation date, refund starts on, used/unused days, amount
- Hidden when the subscription period has ended or no unused future days remain

**CLI** (explicit cancellation date for deterministic tests / automation):

```bash
docker compose exec app php artisan refunds:process 1 --cancel-date=2026-01-10
```

Rules:

- Cancellation day counts as **used**; refund starts the **next** calendar day
- System daily-allocates missing elapsed days first (through yesterday when cancelling today)
- **Cancellation day:** counts as used for refund math; refund starts tomorrow; that day's engagement is allocatable **after the day closes** (even if the subscription is already marked refunded)
- Refund amount is based on **subscription dates**, not prior allocation state
- **Not allowed** after the subscription access period has fully ended
- Creates `refunds` record; updates subscription status
- **No** `earning_reversal` or `clawback` for standard refunds
- Idempotent per `refund:{subscription_id}:{cancellation_date}`

### No-engagement days

If an elapsed day has **zero** `valid_watched_seconds`, no instructor allocation or ledger credit is created. Earned revenue still accrues; the instructor pool for that day remains **unallocated instructor pool** retained by the platform (included in **total platform retained**).

## Payout flow

```bash
docker compose exec app php artisan payouts:run
docker compose exec app php artisan queue:work redis --stop-when-empty --tries=3
```

When the mock provider returns timeout (outcome unknown):

```bash
docker compose exec app php artisan payouts:reconcile
```

| Step | Behavior |
|------|----------|
| `payouts:run` | Creates payout batch + payout rows for `outstanding_minor > 0`; dispatches jobs to Redis |
| Queue worker | Runs `ProcessInstructorPayoutJob`; calls provider |
| Provider **success** | One `payout_debit` ledger entry; `active_snapshot_key` cleared |
| Provider **timeout** | `pending_confirmation` — no debit until reconcile confirms |
| `payouts:reconcile` | Status check only — no duplicate provider send |

`payouts:run` may log an **allocation completeness warning** if elapsed days in the prior month were not daily-allocated. Warning only — does not block payout.

## Filament admin

**URL:** http://localhost:8080/admin

**Login:** `admin@demo.local` / `password` (requires `is_admin = true`)

Non-admin users (e.g. `student@demo.local`) cannot access `/admin` financial screens.

| Screen | Location | Notes |
|--------|----------|-------|
| **Dashboard** | Home | Sections: **Revenue**, **Revenue Split**, **Payouts**, **Subscriptions**, **Tables** (top instructors, recent refunds/payouts) |
| **Subscriptions** | Finance → Subscriptions | Per-subscription financial summary; **Refund Unused Days** on view |
| **Instructor Balances** | Finance → Instructor Balances | Read-only earned / paid / outstanding; payout and ledger history |

Payout triggers are **not** exposed in Filament — payouts run via Artisan only.

## Suggested demo walkthrough

1. **Reset and seed**
   ```bash
   docker compose exec app php artisan migrate:fresh --seed
   ```

2. **Daily allocation** (official path — repeat for elapsed days as needed)
   ```bash
   docker compose exec app php artisan revenue:allocate --date=2026-01-04
   ```

3. **Open dashboard** — verify payment, earned, allocation, and instructor widgets

4. **Open Subscriptions** — inspect financial summary fields

5. **Refund** — **Refund Unused Days** (uses today in Filament) or CLI with `--cancel-date`

6. **Payout**
   ```bash
   docker compose exec app php artisan payouts:run
   docker compose exec app php artisan queue:work redis --stop-when-empty --tries=3
   ```

7. **Inspect** Finance → Instructor Balances — earned, outstanding, payout history

8. **Reconcile** (if mock timeout occurred)
   ```bash
   docker compose exec app php artisan payouts:reconcile
   ```

9. **Run tests**
   ```bash
   docker compose exec app php artisan test
   ```

## Testing strategy

| Area | Test files / coverage |
|------|----------------------|
| Daily allocation | `DailyAllocateRevenueTest` |
| Legacy monthly allocation | `AllocateRevenueTest` |
| Cross-mode guard | `AllocationModeGuardTest` |
| Rounding (LRM) | `AllocationRoundingServiceTest` |
| Revenue recognition | `RevenueRecognitionServiceTest` |
| Refund calculation & cancellation day | `SubscriptionRefundTest` |
| Pre-refund allocation, duplicate refund | `SubscriptionRefundTest` |
| Ledger idempotency | `LedgerAndBalanceTest` |
| Payout duplicate prevention | `PayoutCommandTest` |
| Job retry safety | `PayoutJobRetryTest` |
| Provider timeout / reconcile | `PayoutTimeoutReconcileTest` |
| Filament instructor balances | `InstructorBalanceResourceTest` |
| Filament subscriptions + refund action | `SubscriptionResourceTest` |
| Dashboard widgets | `FinancialDashboardWidgetTest` |

## Known limitations / future improvements

- Real payment gateway integration (Stripe, etc.)
- Real payout provider integration with webhooks
- Exceptional refunds: chargebacks, goodwill on used days (`earning_reversal`, `clawback`)
- Full student portal and LMS features
- Tax / VAT withholding
- Multi-currency FX
- Advanced reporting and period-close workflows
- Production-grade RBAC beyond basic Filament login
- Automated multi-day allocation in seeder (run `--date` per day manually or via shell loop)

## Documentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — design decisions and interview notes
- [docs/AI_USAGE.md](docs/AI_USAGE.md) — AI assistance disclosure (Career 180 requirement)
- [specs/002-daily-allocation-refunds-admin/quickstart.md](specs/002-daily-allocation-refunds-admin/quickstart.md) — feature 002 validation checklist

## License

MIT
