# Quickstart: Daily Allocation, Refunds, and Admin Visibility

**Date**: 2026-06-10 | **Plan**: [plan.md](./plan.md)

Prerequisites: feature 001 complete; Docker running; `migrate` applied.

## 1. Migrate

```bash
docker compose exec app php artisan migrate
```

## 2. Seed demo

```bash
docker compose exec app php artisan db:seed --class=DemoFinancialCoreSeeder
```

Login: `admin@demo.local` / `password` at `http://localhost:8080/admin`

## 3. Daily allocation (official path)

Allocate elapsed days in January 2026. **Do not** also run monthly allocation for 2026-01.

```bash
docker compose exec app php artisan revenue:allocate --date=2026-01-04
```

Repeat for other elapsed days as needed (through cancellation day before refund, or full month for payout demo).

**Idempotency check:**

```bash
docker compose exec app php artisan revenue:allocate --date=2026-01-04
# Second run: no new ledger entries
```

**Cross-mode guard** (must fail if monthly already allocated for January):

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
# Error: daily allocations already exist for this month
```

## 4. Refund

**Filament (primary):**

1. Finance → **Subscriptions**
2. Open subscription view
3. **Refund Unused Days** → cancellation date e.g. `2026-01-10`
4. Confirm

**CLI:**

```bash
docker compose exec app php artisan refunds:process 1 --cancel-date=2026-01-10
```

**Expected:** Pre-allocates Jan 1–10; refund amount covers Jan 11–30; no instructor reversals.

## 5. Monthly payout (unchanged)

```bash
docker compose exec app php artisan queue:work redis --tries=3
docker compose exec app php artisan payouts:run
```

Pays `outstanding_minor > 0` only. Provider success moves outstanding → paid.

`payouts:run` may log an **allocation completeness warning** if elapsed days in the prior month were not daily-allocated — warning only, does not block payout.

## 6. Dashboard

Open Filament dashboard — verify widgets: payments, earned, unearned, refunds, instructor split, payout pipeline, subscription counts, top instructors.

## 7. Tests

```bash
docker compose exec app php artisan test
```

**Expected:** 54 tests pass (feature 001 + feature 002).

## Legacy monthly allocation (compatibility only)

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
```

Use only in isolation from daily allocation for that month. Feature 001 tests use this path.
