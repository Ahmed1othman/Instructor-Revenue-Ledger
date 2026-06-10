# Refund Flow Contract

**Date**: 2026-06-10

## Standard unused-days refund

### Trigger

- **Primary**: Filament view action `Refund Unused Days` on subscription resource
- **Secondary**: `refunds:process` artisan command (tests/demo)

### Inputs

| Input | Rule |
|-------|------|
| subscription_id | Must be active or cancellable |
| cancellation_date | Defaults to today; must be `starts_at <= date <= ends_at` |

### Steps (ordered)

1. **Idempotency check** — key `refund:{subscription_id}:{cancellation_date}`; return existing if found
2. **EnsureElapsedDaysAllocated** — for each calendar day `starts_at .. cancellation_date`, run daily allocation if not already done (idempotent)
3. **Calculate** — `RefundCalculationService`:
   - `used_days` = days from `starts_at` through `cancellation_date` inclusive
   - `refund_starts_on` = `cancellation_date + 1 day`
   - `unused_days` = days from `refund_starts_on` through `ends_at` inclusive
   - `amount_minor` = sum of daily earned amounts for unused days only
4. **Persist** — create `refunds` row (`status=completed` for demo; no gateway call)
5. **Update subscription** — `cancelled_at`, `refunded_at`, `status=refunded`

### Prohibited

- No `earning_reversal` ledger entries
- No `clawback` entries
- No mutation of existing ledger rows
- No instructor balance decreases

### Output

Refund record with `amount_minor`, `used_days`, `unused_days`, `currency`.

## Example

Subscription Jan 1–Jan 30, payment 30,000 minor, cancel Jan 10:

| Field | Value |
|-------|-------|
| used_days | 10 |
| refund_starts_on | Jan 11 |
| unused_days | 20 |
| amount_minor | intdiv(30000 * 20, 30) = 20000 (if uniform daily proration) |

(Exact amount follows last-day remainder rules across full subscription lifetime.)
