# Quickstart: Daily Allocation, Refunds, and Admin Visibility

**Date**: 2026-06-10 | **Plan**: [plan.md](./plan.md)

Prerequisites: feature 001 complete; Docker running; `migrate` applied.

## 1. Migrate new schema

```bash
docker compose exec app php artisan migrate
```

## 2. Seed demo (existing seeder)

```bash
docker compose exec app php artisan db:seed --class=DemoFinancialCoreSeeder
```

## 3. Daily allocation (official path)

Allocate each elapsed day in January 2026 (example loop):

```bash
docker compose exec app php artisan revenue:allocate --date=2026-01-04
# Repeat for each day with consumption / through month end for full demo
```

Or allocate range via shell (implementation may add `revenue:allocate-range` later — not in v1).

**Idempotency check**:

```bash
docker compose exec app php artisan revenue:allocate --date=2026-01-04
# Second run: no new ledger entries
```

## 4. Refund via Filament

1. Open `http://localhost:8080/admin`
2. Login `admin@demo.local` / `password`
3. Navigate to **Subscriptions** (financial resource)
4. Open subscription view → **Refund Unused Days**
5. Confirm cancellation date (e.g. 2026-01-10)

**Expected**: Pre-allocates Jan 1–10; refund amount covers Jan 11–30; no instructor reversals.

## 5. Monthly payout (unchanged)

```bash
docker compose exec app php artisan queue:work redis --tries=3
docker compose exec app php artisan payouts:run
```

Outstanding reflects **allocated** earnings only.

## 6. Dashboard

Open Filament dashboard — verify widgets show payments, earned, unearned, refunds, payout counts.

## 7. Tests

```bash
docker compose exec app php artisan test
```

**Expected**: All feature 001 tests (30) + feature 002 tests pass.

## Legacy monthly allocation (compatibility only)

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
```

Documented as legacy; not the official business policy for new demos.
