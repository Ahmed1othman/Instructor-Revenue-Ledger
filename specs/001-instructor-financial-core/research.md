# Research: Instructor Financial Core

**Date**: 2026-06-10 | **Plan**: [plan.md](./plan.md)

## 1. Settlement period granularity

**Decision**: Calendar months as default settlement period.

**Rationale**: Matches spec clarification; aligns with common subscription revenue reporting;
simple `YYYY-MM` command argument.

**Alternatives considered**:

- Rolling 30-day windows — harder to explain and test
- Weekly periods — more allocation runs, no hiring benefit

## 2. Revenue recognition method

**Decision**: Linear day-based proration using subscription/settlement date-range overlap.

```text
overlap_days = days(subscription_active ∩ settlement_period)
total_subscription_days = days(subscription.starts_at → subscription.ends_at)
earned_amount_minor = intdiv(payment_amount_minor * overlap_days, total_subscription_days)
```

**Proration remainder rule**: When the multiplication has a remainder, add `remainder_minor` to
the earned amount for the **last settlement period** that overlaps the subscription (deterministic;
ensures full payment amount is recognized across subscription life with no float).

**Rationale**: Spec requires earned-over-time; integer `intdiv` with explicit remainder handling
avoids float drift and preserves total recognized = payment amount.

**Alternatives considered**:

- Recognize on payment day — rejected (violates spec)
- Monthly flat 1/12 split — inaccurate for partial months

## 3. Platform/instructor split

**Decision**: Apply basis points after earned amount calculation.

```text
instructor_pool_minor = intdiv(earned_amount_minor * instructor_share_bps, 10000)
platform_share_minor = earned_amount_minor - instructor_pool_minor
```

**Rationale**: Platform gets remainder so `instructor_pool + platform_share = earned` exactly.

## 4. Engagement weight

**Decision**: Sum `valid_watched_seconds` per `(settlement_period, subscription_id, instructor_id)`.

**Rationale**: Spec clarification; fair weighting of watch time vs record count.

**Alternatives considered**:

- Record count — rejected (60-min equals 1-min)
- Global instructor totals — rejected (must allocate per subscription first)

## 5. Allocation rounding

**Decision**: Largest Remainder Method with `instructor_id ASC` tie-break (constitution v1.0.1).

**Rationale**: Lossless pool distribution; constitution-mandated; canonical 34/33/33 test case.

**Alternatives considered**:

- Largest remainder without tie-break — non-deterministic
- Float percentages — rejected

## 6. No-engagement handling

**Decision**: Skip ledger/allocation for subscription/period; log unallocated instructor pool to
application log or optional `allocation_runs` metadata (no instructor payable credit).

**Rationale**: Spec requires no payable balance increase; pool is platform-retained.

## 7. Ledger and balance pattern

**Decision**: Append-only `instructor_ledger_entries`; `instructor_balances` updated via explicit
action with `lockForUpdate`.

**Rationale**: Constitution; rebuildable projections; interview-friendly explicit flow.

**Alternatives considered**:

- Balance-only without ledger — no audit trail
- Model observers for balance updates — rejected (hidden side effects)

## 8. Payout idempotency

**Decision**: `balance_snapshot_hash` from `(instructor_id, currency, outstanding_minor, last_ledger_entry_id)` plus unique constraint preventing duplicate active payouts.

**Rationale**: Spec requires no duplicate payouts for same balance snapshot when command runs twice.

**Alternatives considered**:

- Payout per command run only — fails on retry after crash mid-batch
- Idempotency on amount alone — insufficient when balance unchanged but ledger grew

## 9. Provider timeout semantics

**Decision**: Timeout → `pending_confirmation`; no debit; no re-send; resolve via `CheckPayoutStatusAction` with same `payout:{payout_id}` key.

**Rationale**: Constitution principle III; unknown ≠ failure.

## 10. Provider outside transaction

**Decision**: Pattern: (1) read payout status in TX, (2) commit, (3) call provider, (4) new TX persist result + ledger.

**Rationale**: Avoid holding locks during network I/O; constitution requirement.

## 11. Test provider strategy

**Decision**: `FakePayoutProvider` for all Pest tests with configurable outcomes; `MockPayoutProvider` for manual/demo only.

**Rationale**: Random mock unsuitable for assertions; spec says tests must not rely on randomness.

## 12. Docker command execution

**Decision**: All `php artisan` via `docker compose exec app`; npm via `docker compose exec node`.

**Rationale**: Existing project constraint; constitution; no Sail.

## 13. Filament read-only pattern

**Decision**: Resource with `public static function canCreate/canEdit/canDelete(): bool { return false; }`; view page with relation managers; no header actions.

**Rationale**: Spec FR-031; Filament v3 standard pattern.

## 14. Refunds

**Decision**: Deferred — no `app/Domain/Refunds`, no reversal migrations in v1.

**Rationale**: Spec out of scope until core complete.
