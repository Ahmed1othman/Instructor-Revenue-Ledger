# Architecture: Instructor Revenue Ledger

This document explains how the financial core works — written to be **interview-friendly** and practical.

## Business problem

A learning platform sells monthly subscriptions. Students watch lessons from multiple instructors. The platform must:

1. Recognize subscription revenue over time (not all at payment date)
2. Split each period’s instructor pool by engagement
3. Record immutable financial events
4. Pay instructors safely without duplicate payouts

This project implements that **financial core** only — not the full LMS.

## Subscription payment model

- Plans have `price_minor` (integer), `currency`, `duration_days`, and `instructor_share_bps` (basis points)
- Example: 30,000 minor units = 300.00 EGP; 6000 bps = 60% to instructors, 40% to platform
- A successful `payments` row records what the student paid
- `subscriptions` define the active window (`starts_at`, `ends_at`)

Money is never stored as floats. Display may format `10800` → `108.00 EGP` for humans only.

## Revenue recognition over time

`RevenueRecognitionService` prorates payment amount across calendar days the subscription overlaps each settlement month.

- Uses **day-based overlap** between subscription period and settlement month
- Last period absorbs rounding remainder so lifetime recognized sum equals `payment_amount_minor`
- Integer arithmetic only (`intdiv`, modulo)

A student paying 30,000 for January gets 30,000 recognized in January if the subscription covers the full month.

## Monthly settlement periods

Settlement is **calendar-month** based (`year`, `month`).

```
revenue:allocate --month=2026-01
```

Creates or reuses a `settlement_periods` row and runs allocation for that month.

## Engagement weighting: `valid_watched_seconds`

Within a subscription and settlement month, instructor share is weighted by summed `valid_watched_seconds` from `lesson_consumptions`.

Demo weights:

| Instructor | Seconds | Share of 6000 total |
|------------|---------|---------------------|
| A | 3600 | 60% |
| B | 1800 | 30% |
| C | 600 | 10% |

**No engagement → no allocation** for that instructor in that period.

## Instructor pool after platform cut

For a period:

```
recognized_revenue_minor × instructor_share_bps / 10000 = instructor_pool_minor
```

Demo: 30,000 × 6000 / 10000 = **18,000** minor units instructor pool.

Platform keeps the remainder (12,000 minor).

## Largest Remainder Method

Raw proportional shares often produce fractions. We allocate integer minor units using the **Largest Remainder Method**:

1. Compute floor shares for each instructor
2. Distribute leftover minor units to instructors with largest fractional remainders
3. Tie-break by stable ordering (e.g. instructor id)

**Guarantee:** sum of allocations equals instructor pool exactly — no drift.

Demo result on 18,000 pool:

- A: 10,800 (108.00 EGP)
- B: 5,400 (54.00 EGP)
- C: 1,800 (18.00 EGP)

## Append-only instructor ledger

`instructor_ledger_entries` is **append-only**:

| Field | Role |
|-------|------|
| `type` | e.g. `earning_credit`, `payout_debit` |
| `direction` | `credit` or `debit` |
| `amount_minor` | Integer |
| `idempotency_key` | Unique — duplicate inserts are no-ops |
| `occurred_at` | Business timestamp |

Corrections (e.g. future refunds) would be **new entries**, not updates.

## Instructor balances as projections

`instructor_balances` is a **read-optimized projection**, not the source of truth:

- `total_earned_minor`, `total_paid_minor`, `outstanding_minor`
- Updated when ledger entries are applied (`lockForUpdate` on balance row)
- Tests can rebuild balances from ledger sums to verify consistency

## Idempotency keys

| Domain | Example key pattern |
|--------|---------------------|
| Payment | `demo:payment:jan-2026` |
| Earning credit | `ledger:earning:{period}:{subscription}:{instructor}` |
| Payout debit | `ledger:payout_debit:{payout_id}` |
| Provider call | `provider_idempotency_key` on payout |

Re-running allocation or payout commands must not double-count.

## `active_snapshot_key` — duplicate active payout prevention

MySQL does not support partial unique indexes the way PostgreSQL does. We use a nullable unique column:

```
active_snapshot_key = "{instructor_id}:{currency}:{balance_snapshot_hash}"  // while active
active_snapshot_key = NULL                                                 // terminal states
```

`balance_snapshot_hash` = SHA-256 of `{instructor_id}:{currency}:{outstanding_minor}:{last_ledger_entry_id}`

**Effect:** only one active payout per balance snapshot. Terminal success/failure clears the key.

## Payout lifecycle

```
pending → processing → succeeded | failed | pending_confirmation
```

- `payouts:run` creates a batch and one payout per instructor with `outstanding_minor > 0`
- `ProcessInstructorPayoutJob` calls the provider
- Success → payout debit ledger entry, balance updated, `active_snapshot_key` cleared
- Failure → terminal failed, key cleared
- Timeout → `pending_confirmation`, key **retained** (still “active”)

## Provider timeout = unknown

If the provider times out, we **do not know** if money moved. Rules:

- No payout debit ledger entry yet
- Status → `pending_confirmation`
- `payouts:reconcile` checks status (no re-send)
- At most one debit per payout id

## Provider calls outside DB transactions

External I/O must not run inside a DB transaction holding row locks.

Pattern in `ProcessInstructorPayoutAction`:

1. Short transaction: validate status, mark processing
2. **Call provider** (outside transaction)
3. New transaction: persist attempt result, update payout, write ledger if succeeded

Prevents lock contention and inconsistent state if the provider hangs.

## Safe retries

- Jobs can retry; status gates prevent re-sending after success or `pending_confirmation`
- `active_snapshot_key` prevents duplicate payout rows for the same snapshot
- Ledger idempotency keys prevent duplicate debits
- Tests (`PayoutJobRetryTest`, `PayoutTimeoutReconcileTest`) enforce this

## Redis is not the financial source of truth

Redis handles:

- Queue jobs (`ProcessInstructorPayoutJob`, `CheckPayoutStatusJob`)
- Cache

All money state lives in **MySQL**. If Redis is flushed, financial data remains; jobs may need re-dispatch.

## Why this is not full event sourcing

We use an append-only ledger pattern but:

- Balances are materialized projections
- No event replay infrastructure
- No separate event store

Pragmatic middle ground: audit trail + fast reads without event-sourcing complexity.

## Why full LMS UI is out of scope

The challenge targets **financial correctness under concurrency and idempotency**, not UX for students. Lesson consumptions are seeded data representing engagement that would come from a future video/heartbeat pipeline.

## Filament admin

Read-only `InstructorBalanceResource`:

- List: earned, paid, outstanding, currency
- View: balance summary, payout history, ledger entries
- No create/edit/delete/payout actions

## Provider implementations

| Class | Use |
|-------|-----|
| `MockPayoutProvider` | Demo / local app (random success, failure, timeout) |
| `FakePayoutProvider` | Tests (deterministic forced outcomes) |

## Future improvements

- **Refunds and earning reversals** — debit entries with new idempotency keys
- **Real payout provider** — replace mock with API integration, webhooks
- **Richer audit reporting** — export, period close workflows
- **Multi-currency** — FX rules and per-currency balances (already per-currency rows)
- **Tax / VAT** — jurisdictional withholding
- **Weighted engagement rules** — cap per lesson, minimum watch threshold, anti-fraud signals

## Key commands

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
docker compose exec app php artisan payouts:run
docker compose exec app php artisan queue:work redis --tries=3
docker compose exec app php artisan payouts:reconcile
```

## Related docs

- [README.md](../README.md) — setup and demo
- [specs/001-instructor-financial-core/data-model.md](../specs/001-instructor-financial-core/data-model.md) — schema reference
- [.specify/memory/constitution.md](../.specify/memory/constitution.md) — governing principles
