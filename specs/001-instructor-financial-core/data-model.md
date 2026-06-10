# Data Model: Instructor Financial Core

**Date**: 2026-06-10 | **Plan**: [plan.md](./plan.md)

All money columns use **integer minor units** (`bigInteger`). Currency stored as `char(3)` (e.g. `USD`).
Shares stored as **basis points** (`unsignedSmallInteger`).

## Entity Relationship Summary

```text
plans 1──* subscriptions 1──* payments
subscriptions *──1 users (student)
subscriptions 1──* lesson_consumptions
courses *──1 instructors
lesson_consumptions *──1 courses, instructors, subscriptions, users

settlement_periods 1──* revenue_allocations
revenue_allocations *──1 subscriptions, instructors

instructors 1──* instructor_ledger_entries
instructors 1──* instructor_balances (unique per currency)
instructors 1──* payouts

payout_batches 1──* payouts
payouts 1──* payout_attempts
```

## Tables

### users (existing — extend as needed)

Laravel default users table. Students referenced by `subscriptions.user_id`.

### plans

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| price_minor | bigInteger | Upfront subscription price |
| currency | char(3) | |
| instructor_share_bps | unsignedSmallInteger | Default 6000 |
| duration_days | unsignedInteger | Subscription access length |
| timestamps | | |

### subscriptions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK users | Student |
| plan_id | FK plans | |
| status | string/enum | `SubscriptionStatus` |
| starts_at | datetime | Active range start |
| ends_at | datetime | Active range end |
| currency | char(3) | From plan |
| timestamps | | |

**Indexes**: `(status)`, `(starts_at, ends_at)`

### payments

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| subscription_id | FK subscriptions | |
| amount_minor | bigInteger | |
| currency | char(3) | |
| status | string/enum | `PaymentStatus` — only `succeeded` allocated |
| paid_at | datetime | |
| idempotency_key | string unique | External payment idempotency |
| timestamps | | |

**Indexes**: `(subscription_id, status)`

### instructors

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| user_id | FK users nullable | Optional link |
| timestamps | | |

### courses

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| instructor_id | FK instructors | |
| title | string | |
| timestamps | | |

### lesson_consumptions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| subscription_id | FK subscriptions | Required for allocation grouping |
| student_id | FK users | |
| course_id | FK courses | |
| instructor_id | FK instructors | Denormalized for allocation queries |
| valid_watched_seconds | unsignedInteger | Engagement weight unit |
| consumed_at | datetime | Must fall in settlement period |
| timestamps | | |

**Indexes**: `(subscription_id, instructor_id, consumed_at)`, `(consumed_at)`

### settlement_periods

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| year | unsignedSmallInteger | |
| month | unsignedTinyInteger | 1–12 |
| period_start | date | First day of month |
| period_end | date | Last day of month |
| status | string/enum | `SettlementPeriodStatus` |
| timestamps | | |

**Unique**: `(year, month)`

### revenue_allocations

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| settlement_period_id | FK | |
| subscription_id | FK | |
| instructor_id | FK | |
| instructor_pool_minor | bigInteger | Pool before split (audit) |
| engagement_weight | unsignedInteger | Sum valid_watched_seconds |
| allocated_amount_minor | bigInteger | Instructor share |
| currency | char(3) | |
| idempotency_key | string unique | `allocation:{period}:{sub}:{instructor}` |
| timestamps | | |

**Indexes**: `(settlement_period_id, subscription_id)`, `(instructor_id)`

### instructor_ledger_entries (append-only)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| instructor_id | FK instructors | |
| subscription_id | FK nullable | Set for earnings |
| settlement_period_id | FK nullable | Set for earnings |
| payout_id | FK nullable | Set for payout debits |
| type | string/enum | `LedgerEntryType` |
| direction | string/enum | `LedgerDirection` credit/debit |
| amount_minor | bigInteger | Always positive; direction applies sign |
| currency | char(3) | |
| idempotency_key | string unique | |
| metadata | json nullable | Audit context |
| occurred_at | datetime | |
| timestamps | | created_at only used; no updates |

**Indexes**: `(instructor_id, occurred_at)`, `(payout_id)`

### instructor_balances (projection)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| instructor_id | FK instructors | |
| currency | char(3) | |
| total_earned_minor | bigInteger | default 0 |
| total_paid_minor | bigInteger | default 0 |
| outstanding_minor | bigInteger | default 0 |
| last_ledger_entry_id | FK nullable | Snapshot aid |
| timestamps | | |

**Unique**: `(instructor_id, currency)`

### payout_batches

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| status | string/enum | `PayoutBatchStatus` |
| initiated_at | datetime | |
| completed_at | datetime nullable | |
| timestamps | | |

### payouts

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| payout_batch_id | FK | |
| instructor_id | FK | |
| amount_minor | bigInteger | Payout amount |
| currency | char(3) | |
| status | string/enum | `PayoutStatus` |
| balance_snapshot_hash | string(64) | sha256 hex |
| active_snapshot_key | string nullable | `{instructor_id}:{currency}:{balance_snapshot_hash}` when active; null when terminal |
| provider_idempotency_key | string unique | `payout:{payout_id}` |
| timestamps | | |

**Indexes**: `(instructor_id, status)`, `(balance_snapshot_hash)`, `UNIQUE(active_snapshot_key)`

**Active payout guard (MySQL 8)**: Non-null `active_snapshot_key` on active statuses (`pending`, `processing`, `pending_confirmation`). Set to `null` when status becomes `succeeded` or `failed` in the same transaction. MySQL unique index allows multiple NULLs for historical terminal payouts.

### payout_attempts

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| payout_id | FK payouts | |
| type | string | `send` or `status_check` |
| status | string/enum | `PayoutAttemptStatus` |
| provider_result | string/enum nullable | `ProviderResultStatus` |
| provider_reference | string nullable | |
| idempotency_key | string | Same as payout provider key for send/check |
| attempted_at | datetime | |
| response_payload | json nullable | |
| timestamps | | |

**Indexes**: `(payout_id, type)`, `(idempotency_key)`

## Enum Values (PHP backed enums)

### SubscriptionStatus

`active`, `cancelled`, `expired`

### PaymentStatus

`pending`, `succeeded`, `failed`

### SettlementPeriodStatus

`open`, `allocating`, `allocated`, `closed`

### LedgerEntryType

`earning_credit`, `payout_debit` (+ future: `earning_reversal`, `payout_reversal`, `manual_adjustment`)

### LedgerDirection

`credit`, `debit`

### PayoutBatchStatus

`pending`, `processing`, `completed`, `failed`

### PayoutStatus

`pending`, `processing`, `pending_confirmation`, `succeeded`, `failed`

### PayoutAttemptStatus

`pending`, `succeeded`, `failed`, `timeout`

### ProviderResultStatus

`success`, `permanent_failure`, `timeout_unknown`

## State Transitions

### Payout lifecycle

```text
pending → processing → succeeded
                    → failed
                    → pending_confirmation → succeeded (via reconcile)
                                         → failed (via reconcile)
```

- `processing` → provider call in flight
- `pending_confirmation` → timeout unknown; only status_check allowed
- Retry job on `pending`/`processing` (crash recovery) re-checks status first
- No transition from `succeeded` or `failed` back to send

### Settlement period

```text
open → allocating → allocated → closed
```

## Idempotency Keys

| Operation | Key pattern |
|-----------|-------------|
| Revenue allocation | `allocation:{settlement_period_id}:{subscription_id}:{instructor_id}` |
| Earning ledger | `ledger:earning:{settlement_period_id}:{subscription_id}:{instructor_id}` |
| Provider send | `payout:{payout_id}` |
| Payout debit ledger | `ledger:payout_debit:{payout_id}` |

## Balance Projection Rules

On **earning credit** (credit):

- `total_earned_minor += amount`
- `outstanding_minor += amount`

On **payout debit** (debit):

- `total_paid_minor += amount`
- `outstanding_minor -= amount`

Always update `last_ledger_entry_id` after successful ledger insert + balance update in same transaction.
