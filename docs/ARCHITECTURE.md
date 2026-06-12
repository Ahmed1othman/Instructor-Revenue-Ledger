# Architecture: Instructor Revenue Ledger

This document explains how the financial core works — written to be **interview-friendly** and practical.

## Business problem

A learning platform sells monthly subscriptions. Students watch lessons from multiple instructors. The platform must:

1. Recognize subscription revenue over time (not all at payment date)
2. Split each period’s instructor pool by engagement
3. Record immutable financial events
4. Pay instructors safely without duplicate payouts

This project implements that **financial core** only — not the full LMS.

The sections below include **locked lifecycle rules** and the **implemented** feature 002 paths: **daily allocation** (official), **legacy monthly allocation**, **standard refunds**, Filament subscription visibility, and financial dashboard widgets.

## Financial lifecycle rules (locked)

These rules define cash collection, earning, allocation, payout, and refund behavior. They must be preserved in any future implementation.

### Cash collection vs earned revenue

Students pay **upfront** for monthly, 3-month, or annual subscriptions.

| Concept | Meaning |
|---------|---------|
| **Cash received** | Payment succeeds on day one — full plan price is collected immediately |
| **Earned revenue** | Revenue is recognized **gradually** over the subscription access period |
| **Unearned revenue** | Future unelapsed access days — not yet earned |

Payment records cash. Recognition and allocation move value into earned instructor pools only as access days elapse. Future days must never be treated as already earned.

### Allocation frequency vs payout frequency

Allocation frequency and payout frequency are **separate** concerns.

| Mode | What it does |
|------|----------------|
| **Daily allocation** | Allocate revenue for one **completed elapsed** calendar day |
| **Monthly allocation** | Allocate revenue for one **completed** settlement month |
| **Monthly payout** | Pay instructors from allocated outstanding balances |

Payout can remain **monthly** even when allocation runs **daily**. A platform may allocate daily for operational visibility but still batch payouts once per month.

### Daily vs monthly allocation — mutual exclusion

Daily and monthly allocation must **never overlap** for the same settlement month. This prevents the same subscription-period slice from being allocated twice.

| Condition | Rule |
|-----------|------|
| Month has any daily allocations | **Block** monthly allocation for that month |
| Month has a monthly allocation | **Block** daily allocation for any date inside that month |

Enforcement is a hard guard — not a warning.

### Allocation scope

Only **elapsed / completed** access periods may be allocated.

- A day is allocatable only after that calendar day has fully ended (for daily mode), or after the settlement month is complete (for monthly mode).
- The system must **never** allocate instructor earnings for future unearned days.
- Engagement (`valid_watched_seconds`) is evaluated only within the elapsed window being settled.

### Payout ordering and cutoff

**Earned / allocated ≠ paid.** These are distinct states.

| State | Meaning |
|-------|---------|
| **Earned / allocated** | `earning_credit` ledger entry written; `outstanding_minor` increased |
| **Paid** | Confirmed provider success; `payout_debit` written; `total_paid_minor` increased; `outstanding_minor` decreased |

Rules:

1. Only **allocated** outstanding balances are eligible for payout.
2. Payout must **not** run ahead of allocation.
3. **Monthly payout:** the target period must have **complete** allocation before payout runs.
4. **Daily allocation path:** every day in the payout period must be allocated before paying that period.
5. **Provider success** is the **only** event that converts outstanding to paid. Timeout, failure, or pending states do not change paid balances.

### Standard refund policy

Standard refunds apply only to **unused future days**.

**Cancellation day rule:** the cancellation day counts as a **used / elapsed** access day. Refund calculation starts from the **next** day.

**Example:**

- Subscription: Jan 1 → Jan 30
- Cancel / refund requested: Jan 10
- **Used days:** Jan 1 through Jan 10 (inclusive)
- **Refundable days:** Jan 11 through Jan 30

**Before calculating a standard refund:**

1. Allocate all **unallocated allocatable elapsed days** through the cancellation day (when cancelling **today**, only days through **yesterday** are allocatable — the current calendar day cannot be allocated until it ends).
2. This ensures instructor engagement on completed elapsed days is counted before any refund.

**Cancellation-day allocation after refund:** if cancellation happens today, today counts as **used** for refund math (refund starts tomorrow) but is **not** allocated until the calendar day completes. After day close, `revenue:allocate --date=` for that cancellation date still works even when the subscription is already marked `refunded` — daily allocation keys off the subscription access window, not status. No `earning_reversal` or `clawback` is created for standard refunds.

**Why standard refunds need no earning reversals:**

- Standard refunds cover only **unused future days**.
- Future days were **never allocated** to instructors.
- Therefore no instructor `earning_credit` needs to be reversed for a standard refund.

### Exceptional refunds and chargebacks (future extension)

For cases that affect **already elapsed / allocated / paid** periods:

- Goodwill refunds for used days
- Chargebacks
- Payment disputes
- Fraud or manual corrections

The system must **not** mutate old ledger records. Use **append-only** entries:

| Situation | Ledger entry type |
|-----------|-------------------|
| Earnings allocated but **not yet paid** | `earning_reversal` |
| Earnings **already paid** to instructor | `clawback` / negative adjustment |

This is explicitly **out of scope** for v1 but required for a complete production system.

### End-to-end lifecycle flow

```
Student pays upfront
  → subscription active
  → elapsed days become earned over time
  → allocation runs (daily OR monthly) for elapsed periods only
  → instructor earning_credit entries written
  → instructor outstanding increases
  → monthly payout runs only after target allocation period is complete
  → provider success writes payout_debit
  → instructor paid increases, outstanding decreases
  → standard refund: allocate through cancellation day, then refund unused future days only
```

### Why this design is safe (interview summary)

1. **No future unearned allocation** — instructors are never paid for days the student has not yet consumed.
2. **Simple standard refunds** — refund only unallocated future days; no clawbacks for the common case.
3. **No double allocation** — daily/monthly mutual exclusion blocks overlapping settlement for the same month.
4. **No payout-before-allocation** — period cutoffs ensure outstanding balances reflect fully settled slices before money leaves.
5. **Append-only corrections** — exceptional cases add ledger entries; history stays auditable.

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

## Daily allocation (official — feature 002)

```
revenue:allocate --date=2026-01-04
```

- Allocates **one completed elapsed calendar day** (rejects today and future dates)
- Creates a `settlement_periods` row with `granularity=daily`
- Idempotency key: `allocation:daily:{date}:{subscription}:{instructor}`
- Engagement filtered to `DATE(consumed_at) = date`
- `AllocationModeGuardService` blocks daily allocation if monthly allocations exist for that month

## Monthly settlement periods (legacy)

```
revenue:allocate --month=2026-01
```

Calendar-month settlement retained for feature 001 tests and backward compatibility. **Not** the official path for refund demos. Guard blocks monthly allocation if any daily allocations exist in that month.

## Standard refunds (implemented)

`CreateSubscriptionRefundAction` orchestrates:

1. Idempotency check (`refund:{subscription_id}:{cancellation_date}`)
2. `SubscriptionRefundEligibilityService` — rejects ended subscriptions and zero unused-day refunds
3. `EnsureElapsedDaysAllocatedAction` — daily allocation through allocatable elapsed days
4. `RefundCalculationService` — unused future days amount from **subscription dates** (integer minor units)
5. Persist `refunds` row; update subscription status — **no ledger reversals**

**Filament:** **Refund Unused Days** uses **today** as cancellation date (read-only preview modal; no date picker). Hidden when the subscription has ended or no refundable days remain.

**CLI:** `refunds:process {subscription} --cancel-date=` accepts an explicit date for deterministic tests.

## Subscription financial summary

`SubscriptionFinancialSummaryService` computes per-subscription metrics from MySQL:

- Paid, earned, unearned, refunded, remaining refundable (0 after subscription ends)
- Platform contractual share, instructor pool (contractual), instructor allocated, **unallocated instructor pool**, **total platform retained**
- Instructor paid, instructor outstanding

Earned revenue is computed from **elapsed subscription days** (recognition), not from whether allocation rows already exist.

Displayed on **Finance → Subscriptions** (read-only list/view).

## Filament financial dashboard

DB-backed widgets grouped into business sections (no Redis cache for money totals):

| Section | Widget | Metrics |
|---------|--------|---------|
| Revenue | FinancialOverviewStats | total student payments, earned, unearned liability, refunds, remaining refundable |
| Revenue Split | RevenueSplitStats | platform contractual share, instructor pool, allocated, unallocated pool, total platform retained |
| Payouts | PayoutPipelineStats | instructor paid/outstanding, pending, pending confirmation, failed |
| Subscriptions | SubscriptionStatusStats | active, expired, cancelled, refunded counts |
| Tables | TopInstructorsByEarned / TopInstructorsByOutstanding | top 5 by earned / outstanding |
| Tables | RecentRefundsWidget / RecentPayoutsWidget | latest refund and payout rows |

Money stat widgets resolve currency from succeeded payments: a single currency (e.g. EGP in demo seed) is shown on all amounts; multiple currencies show amounts without a misleading code plus a **mixed currencies — not FX-converted** description.

## Engagement weighting: `valid_watched_seconds`

Within a subscription and settlement month, instructor share is weighted by summed `valid_watched_seconds` from `lesson_consumptions`.

Demo weights:

| Instructor | Seconds | Share of 6000 total |
|------------|---------|---------------------|
| A | 3600 | 60% |
| B | 1800 | 30% |
| C | 600 | 10% |

**No engagement → no allocation** for that day: no fake allocation, no `earning_credit`, outstanding does not increase. The contractual instructor pool for that elapsed day becomes **unallocated instructor pool** retained by the platform (included in **total platform retained**). Earned revenue still accrues over time.

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

Standard refunds do not require earning reversals (future days were never allocated). Exceptional corrections use **new entries** (`earning_reversal`, `clawback`), never updates to existing rows.

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

Payout runs only against **allocated** outstanding balances, and only after the target allocation period is complete (see payout ordering rules above). Allocated does not mean paid — paid changes only on confirmed provider success.

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

`/student` shows a placeholder message only. Financial screens (dashboard, subscriptions, instructor balances, refunds) require `users.is_admin = true` and are served from `/admin`.

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

- **Exceptional refunds / chargebacks** — append-only `earning_reversal` and `clawback` entries
- **`revenue:allocate-range`** — convenience command for multi-day demo loops
- **Real payout provider** — replace mock with API integration, webhooks
- **Richer audit reporting** — export, period close workflows
- **Multi-currency** — FX rules and per-currency balances (already per-currency rows)
- **Tax / VAT** — jurisdictional withholding
- **Weighted engagement rules** — cap per lesson, minimum watch threshold, anti-fraud signals

## Key commands

```bash
docker compose exec app php artisan revenue:allocate --date=2026-01-04   # official daily
docker compose exec app php artisan revenue:allocate --month=2026-01     # legacy monthly
docker compose exec app php artisan refunds:process 1 --cancel-date=2026-01-10
docker compose exec app php artisan payouts:run
docker compose exec app php artisan queue:work redis --tries=3
docker compose exec app php artisan payouts:reconcile
docker compose exec app php artisan db:seed --class=RichFinancialDemoSeeder   # optional rich demo data
```

## Related docs

- [README.md](../README.md) — setup and demo
- [specs/001-instructor-financial-core/data-model.md](../specs/001-instructor-financial-core/data-model.md) — schema reference
- [.specify/memory/constitution.md](../.specify/memory/constitution.md) — governing principles
