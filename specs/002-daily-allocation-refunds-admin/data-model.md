# Data Model: Daily Allocation, Refunds, and Financial Admin Visibility

**Date**: 2026-06-10 | **Plan**: [plan.md](./plan.md) | **Extends**: [001 data-model](../001-instructor-financial-core/data-model.md)

All money columns remain **integer minor units**. Additive migrations only — do not edit feature 001 migration files.

## New / Modified Entities

### settlement_periods (ALTER)

| Column | Type | Notes |
|--------|------|-------|
| granularity | string(16) | `monthly` (default) \| `daily` |
| | | Monthly rows: existing `(year, month)` unique |
| | | Daily rows: `period_start = period_end`, `granularity=daily` |

**New unique index**: `(granularity, period_start)` — short name e.g. `sp_granularity_period_start_uniq`

**Migration note**: Backfill existing rows with `granularity = monthly`.

### revenue_allocations (ALTER)

| Column | Type | Notes |
|--------|------|-------|
| allocation_date | date nullable | Set for daily allocations; NULL for legacy monthly |

**Idempotency keys**:
- Monthly (legacy): `allocation:{settlement_period_id}:{subscription_id}:{instructor_id}`
- Daily: `allocation:daily:{YYYY-MM-DD}:{subscription_id}:{instructor_id}`

**Index**: `(allocation_date, subscription_id)`

### subscriptions (ALTER)

| Column | Type | Notes |
|--------|------|-------|
| cancelled_at | date nullable | Cancellation / refund request day (used day) |
| refunded_at | datetime nullable | When refund record finalized |

**SubscriptionStatus enum**: add `Refunded = 'refunded'` (keep `Cancelled` for pre-refund if needed; refund flow sets `refunded`).

### refunds (NEW)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| subscription_id | FK subscriptions | |
| payment_id | FK payments nullable | Primary succeeded payment |
| student_id | FK users | Denormalized from subscription |
| amount_minor | unsignedBigInteger | Refund amount |
| currency | char(3) | |
| cancellation_date | date | Used through this day inclusive |
| refund_starts_on | date | cancellation_date + 1 day |
| used_days | unsignedInteger | Count Jan 1–Jan 10 → 10 |
| unused_days | unsignedInteger | Refundable day count |
| status | string(32) | `RefundStatus` enum: pending, completed, failed |
| reason | string nullable | e.g. `standard_unused_days` |
| idempotency_key | string unique | `refund:{subscription_id}:{cancellation_date}` |
| processed_at | datetime nullable | |
| timestamps | | |

**Indexes**: `(subscription_id)`, `(student_id)`, `(status)`

### instructor_ledger_entries (no schema change)

Daily earning credits continue to use `settlement_period_id` (daily period row) and `subscription_id`. Optional: set `metadata` JSON with `allocation_date` for audit (application-level, not required).

## Derived / Read Models (not persisted)

### SubscriptionFinancialSummary

Computed by `SubscriptionFinancialSummaryService`:

| Field | Source |
|-------|--------|
| original_payment_minor | `payments` succeeded sum |
| earned_minor | Sum daily recognition for allocated days + monthly legacy if any |
| unearned_minor | `payment - earned - refunded` (floor 0) |
| refunded_minor | `refunds` completed sum |
| remaining_refundable_minor | `RefundCalculationService::preview()` |
| platform_earned_minor | earned − instructor_pool_allocated |
| instructor_pool_allocated_minor | `revenue_allocations` sum for subscription |
| instructor_paid_minor | Sum `instructor_balances.total_paid_minor` for instructors with allocations on subscription |
| instructor_outstanding_minor | Sum `instructor_balances.outstanding_minor` for same instructor set |

## State Transitions

### Refund

```text
(none) → pending → completed
                 → failed (rare demo)
```

Duplicate `idempotency_key` → no-op return existing refund.

### Subscription (refund flow)

```text
active → refunded (cancelled_at set, refunded_at set)
```

## Relationships (additions)

```text
subscriptions 1──* refunds
payments 1──* refunds (optional FK)
subscriptions 1──* revenue_allocations (via existing)
settlement_periods 1──* revenue_allocations (daily + monthly)
```

## Non-Goals (schema)

- No `earning_reversal` / `clawback` ledger types in this feature
- No payment gateway refund transaction IDs
- No multi-currency conversion tables
