# Quickstart: Instructor Financial Core

**Date**: 2026-06-10 | **Plan**: [plan.md](./plan.md) | **Data model**: [data-model.md](./data-model.md)

End-to-end validation guide for the hiring submission. All PHP commands use the **app** container.

## Prerequisites

- Docker Compose running (`app`, `nginx`, `mysql`, `redis`, `node`)
- `.env` configured for MySQL and Redis queue
- Filament admin user exists

## 1. Start environment

```bash
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=DemoFinancialCoreSeeder
```

## 2. Create admin user (if needed)

```bash
docker compose exec app php artisan make:filament-user
```

## 3. Start queue worker (separate terminal)

```bash
docker compose exec app php artisan queue:work redis --tries=3
```

## 4. Run revenue allocation

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
```

### Expected outcome

- `revenue_allocations` rows for 3 instructors
- Earning ledger credits: **10,800 / 5,400 / 1,800** minor units (demo seeder scenario)
- `instructor_balances.outstanding_minor` matches allocations
- Sum of allocations = instructor pool (18,000 for full-month demo)

## 5. Run payouts

```bash
docker compose exec app php artisan payouts:run
```

Wait for queue worker to process jobs.

### Expected outcome

- `payout_batches` and `payouts` created
- On provider success: payout debit ledger entries; `total_paid` increased; `outstanding` decreased
- Running command again: **no duplicate payouts** for same balance snapshot

## 6. Reconcile timeouts (when applicable)

If mock provider returns timeout:

```bash
docker compose exec app php artisan payouts:reconcile
```

### Expected outcome

- `pending_confirmation` payouts resolved via status check
- At most **one** payout debit per payout id

## 7. Run tests

```bash
docker compose exec app php artisan test
```

### Expected outcome

All financial tests pass including:

- 34/33/33 rounding
- Pool sum equality
- Allocation idempotency
- Payout command/job safety
- Timeout reconciliation

## 8. Filament read-only view

Open: `http://localhost:8080/admin`

Navigate to **Instructor Balances** (resource name may vary slightly).

### Expected outcome

- List shows earned, paid, outstanding, currency per instructor
- View page shows payout history
- No create, edit, delete, or payout buttons

## 9. Idempotency spot checks

```bash
# Allocation twice — no duplicate ledger entries
docker compose exec app php artisan revenue:allocate --month=2026-01

# Payout twice — no duplicate active payouts
docker compose exec app php artisan payouts:run
```

## Demo scenario reference

| Metric | Value |
|--------|-------|
| Payment | 30,000 minor units |
| Instructor share | 6000 bps (60%) |
| Instructor pool (full month) | 18,000 |
| Watched seconds | A=3600, B=1800, C=600 |
| Allocations | A=10800, B=5400, C=1800 |

## Troubleshooting

| Issue | Check |
|-------|-------|
| Payouts stuck pending | Queue worker running? Redis connected? |
| Migration fails | MySQL container healthy on port 3307 |
| Filament 404 | `php artisan filament:install --panels` already done; nginx on 8080 |

## Documentation

After implementation, see:

- `README.md` — setup and commands
- `docs/ARCHITECTURE.md` — design decisions
- `docs/AI_USAGE.md` — AI assistance disclosure
