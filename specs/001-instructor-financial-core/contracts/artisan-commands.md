# Contract: Artisan Commands

**Date**: 2026-06-10 | **Plan**: [plan.md](../plan.md)

All commands run inside the app container:

```bash
docker compose exec app php artisan <command>
```

## revenue:allocate

Allocate instructor earnings for a calendar-month settlement period.

### Signature

```text
revenue:allocate {--month= : Settlement month as YYYY-MM (defaults to previous calendar month)}
```

### Behavior

1. Resolve or create `settlement_periods` row for the given month.
2. Find succeeded payments whose subscriptions overlap the period.
3. For each subscription/payment:
   - Calculate earned amount (day-based proration).
   - Calculate instructor pool (basis points).
   - Load engagement grouped by `subscription_id` + `instructor_id`.
   - If total `valid_watched_seconds` is 0 → skip (log unallocated pool).
   - Else allocate via Largest Remainder Method.
   - Create `revenue_allocations` + earning ledger entries (idempotent).
   - Update balance projections.
4. Mark settlement period `allocated`.

### Exit codes

| Code | Meaning |
|------|---------|
| 0 | Success (including no-op when already allocated) |
| 1 | Invalid month format or fatal error |

### Example

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
```

## payouts:run

Create payout batch and dispatch jobs for instructors with outstanding balance.

### Signature

```text
payouts:run
```

### Behavior

1. Find `instructor_balances` where `outstanding_minor > 0`.
2. For each instructor/currency:
   - Compute `balance_snapshot_hash`.
   - Skip if active payout exists for same snapshot.
   - Create `payouts` record with `amount_minor = outstanding_minor`.
3. Create `payout_batches` record grouping new payouts.
4. Dispatch `ProcessInstructorPayoutJob` per payout.

### Idempotency

Second run with unchanged balances and existing active/succeeded payout for snapshot → no new payout.

### Example

```bash
docker compose exec app php artisan payouts:run
```

## payouts:reconcile

Dispatch status checks for payouts in `pending_confirmation`.

### Signature

```text
payouts:reconcile
```

### Behavior

1. Find payouts with `status = pending_confirmation`.
2. Dispatch `CheckPayoutStatusJob` for each.
3. Job calls provider status check with `payout:{payout_id}` idempotency key.
4. On confirmed success → one payout debit ledger entry.
5. On confirmed failure → mark failed, no debit.

### Example

```bash
docker compose exec app php artisan payouts:reconcile
```

## queue:work (operational prerequisite)

Not a feature command but required for async payout processing:

```bash
docker compose exec app php artisan queue:work redis --tries=3
```
