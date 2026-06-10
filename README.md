# Instructor Revenue Ledger

A Laravel 11 hiring-challenge project that models **subscription revenue recognition**, **engagement-weighted instructor allocation**, an **append-only financial ledger**, and **safe idempotent payouts** — with a read-only Filament admin for audit visibility.

## What this project is

- A **financial core** for a multi-instructor learning platform
- Monthly settlement and revenue allocation driven by `valid_watched_seconds`
- Integer **minor-unit** money handling (no floats in business logic)
- Append-only instructor ledger with balance projections
- Payout batching with duplicate prevention via `active_snapshot_key`
- Mock payout provider for demo; deterministic fake provider in tests
- Read-only Filament screen for balances, payout history, and ledger entries

## What this project is not

- Not a full LMS (no course catalog UI, video player, or heartbeat tracking)
- Not a student dashboard
- Not a real payment gateway or payout provider integration
- Not daily allocation or refund flows in v1 code (documented policy only — see lifecycle rules below)
- Not Laravel Sail — this project uses **custom Docker Compose**
- Not event sourcing — MySQL is the financial source of truth

## Tech stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 11, PHP 8.3 |
| Database | MySQL 8 (financial source of truth) |
| Queue / cache | Redis (jobs and cache only — not authoritative for money) |
| Admin UI | Filament v3 (read-only) |
| Tests | Pest |
| Containers | Custom Docker Compose (`app`, `nginx`, `mysql`, `redis`, `node`) |

## Docker setup

**Prerequisites:** Docker and Docker Compose.

```bash
docker compose up -d --build
```

Services:

| Service | Purpose | Host port |
|---------|---------|-----------|
| `nginx` | Web server | `8080` |
| `app` | PHP / Artisan | — |
| `mysql` | Database | `3307` |
| `redis` | Queue & cache | `6380` |
| `node` | Frontend assets | — |

## Installation

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec node npm install
docker compose exec node npm run build
cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
```

## Environment setup

Copy `.env.example` to `.env`. Key values (defaults work with Docker):

- `DB_HOST=mysql`, `DB_DATABASE=instructor_ledger`, `DB_USERNAME=instructor`, `DB_PASSWORD=secret`
- `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST=redis`
- `APP_URL=http://localhost:8080`

All `php artisan` and `composer` commands run inside the **app** container.

## Migrations and seeders

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Or seed demo data only:

```bash
docker compose exec app php artisan db:seed --class=DemoFinancialCoreSeeder
```

### Demo scenario

The seeder creates:

| Entity | Details |
|--------|---------|
| Admin | `admin@demo.local` / `password` (Filament login) |
| Student | `student@demo.local` / `password` |
| Plan | **Monthly Pro** — 30,000 minor units (300.00 EGP), 30 days, 6000 bps instructor share (60%) |
| Subscription | January 2026 (`2026-01-01` → `2026-01-30`) |
| Payment | 30,000 EGP succeeded |
| Instructors | A (Laravel APIs), B (Livewire & Filament), C (Career Skills) |
| Engagement | `valid_watched_seconds`: A=3600, B=1800, C=600 |

After allocation for January 2026, expected instructor earnings:

| Instructor | Minor units | Display |
|------------|-------------|---------|
| A | 10,800 | 108.00 EGP |
| B | 5,400 | 54.00 EGP |
| C | 1,800 | 18.00 EGP |

Instructor pool = 18,000 minor (60% of 30,000). Platform retains 12,000 minor (40%).

## Financial lifecycle rules (locked)

These rules define how cash, earning, allocation, payout, and refunds relate. Full detail is in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

**Cash vs earned:** Students pay upfront (monthly, 3-month, or annual plans). Cash is received on day one, but revenue is **earned gradually** over the access period. Future unelapsed days are unearned.

**Allocation vs payout:** Separate frequencies. The system supports **daily** allocation (one elapsed day) or **monthly** allocation (one completed month). Payout can stay **monthly** even if allocation is daily. v1 implements monthly allocation only.

**Daily/monthly mutual exclusion:** Never allocate the same month twice — if a month has daily allocations, monthly allocation for that month is blocked, and vice versa.

**Allocation scope:** Only **elapsed / completed** periods may be allocated. Never allocate future unearned days.

**Earned ≠ paid:** Allocation writes `earning_credit` and increases outstanding. **Paid** changes only after **confirmed provider success** writes `payout_debit`. Payout must not run ahead of allocation; the target period must be fully allocated first.

**Standard refunds:** Apply only to **unused future days**. The cancellation day counts as used; refund starts the next day. Before refunding, allocate all elapsed days through the cancellation day. Because future days were never allocated, standard refunds do **not** require instructor earning reversals.

**Exceptional refunds (future):** Chargebacks, goodwill on used days, disputes — use append-only `earning_reversal` or `clawback` entries; never mutate old ledger rows.

**Lifecycle flow:**

```
Pay upfront → subscription active → elapsed days earned
→ allocate (daily or monthly, elapsed only) → earning_credit → outstanding up
→ payout after period fully allocated → provider success → payout_debit → paid up
→ standard refund: allocate through cancel day → refund future days only
```

## Running tests

```bash
docker compose exec app php artisan test
```

Tests use `FakePayoutProvider` (deterministic). The demo app binds `MockPayoutProvider` (random outcomes) via `AppServiceProvider`.

## Revenue allocation

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
```

Idempotent — running twice does not duplicate ledger entries.

## Running payouts

**Start a queue worker first** (separate terminal):

```bash
docker compose exec app php artisan queue:work redis --tries=3
```

Then:

```bash
docker compose exec app php artisan payouts:run
```

Or process all pending jobs once:

```bash
docker compose exec app php artisan queue:work redis --stop-when-empty --tries=3
```

`payouts:run` dispatches jobs to Redis. Without a worker, payouts stay pending.

## Payout reconciliation

When the mock provider returns a timeout (outcome unknown):

```bash
docker compose exec app php artisan payouts:reconcile
```

Resolves `pending_confirmation` payouts via status check — no duplicate provider send.

## Full demo flow

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan revenue:allocate --month=2026-01
docker compose exec app php artisan payouts:run
docker compose exec app php artisan queue:work redis --stop-when-empty --tries=3
docker compose exec app php artisan test
```

Open Filament: **http://localhost:8080/admin**

Login: `admin@demo.local` / `password` → **Finance → Instructor Balances**

## Important financial guarantees

- **Integer minor units** — all stored amounts are integers; display formatting only divides for strings
- **Cash ≠ earned** — upfront payment does not mean same-day full earning; only elapsed access is allocatable
- **Earned / allocated ≠ paid** — outstanding increases on allocation; paid increases only on provider success
- **No future allocation** — instructor earnings only for completed elapsed periods
- **No daily/monthly overlap** — mutual exclusion prevents double allocation in the same month
- **Payout after allocation** — payout cutoffs require the target period to be fully allocated first
- **Largest Remainder Method** — allocation rounding preserves exact pool sums
- **Append-only ledger** — exceptional corrections are new entries (`earning_reversal`, `clawback`), not updates
- **Standard refunds without reversals** — refund unused future days only; those days were never allocated
- **Idempotency keys** — ledger entries and payments deduplicated by unique keys
- **`active_snapshot_key`** — prevents duplicate active payouts for the same balance snapshot (MySQL unique index)
- **Provider outside transactions** — external calls never hold DB locks
- **Timeout = unknown** — no debit until status is confirmed

## Known limitations / out of scope (v1 code)

- Daily allocation command and daily/monthly exclusion guards (documented, not implemented)
- Standard refund processing (policy documented; allocate-through-cancel-day rule applies when built)
- Exceptional refunds, chargebacks, and clawbacks (append-only ledger extension)
- Real payment gateway or payout provider
- Multi-currency conversion
- Tax / VAT
- Full LMS UI, student dashboard, video player, heartbeat tracking
- Payout triggers from Filament (read-only audit view only)
- Role-based authorization beyond basic Filament panel access

## Documentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — design decisions and interview notes
- [docs/AI_USAGE.md](docs/AI_USAGE.md) — AI assistance disclosure
- [specs/001-instructor-financial-core/quickstart.md](specs/001-instructor-financial-core/quickstart.md) — validation checklist

## License

MIT
