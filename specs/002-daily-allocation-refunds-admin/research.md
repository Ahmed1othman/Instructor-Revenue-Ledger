# Research: Daily Allocation, Refunds, and Financial Admin Visibility

**Date**: 2026-06-10 | **Plan**: [plan.md](./plan.md)

## R1: Daily allocation data model (additive)

**Decision**: Extend `settlement_periods` with `granularity` (`monthly` | `daily`) and use one row per calendar day for daily mode (`period_start = period_end = allocation date`). Add nullable `allocation_date` on `revenue_allocations` for query/report clarity. Keep `settlement_period_id` FK on allocations for both modes.

**Rationale**: Reuses existing allocation + ledger pipeline (`AllocateRevenueForSettlementAction` pattern) without a parallel allocations table. Legacy monthly rows keep `granularity=monthly`. Daily idempotency keys use date: `allocation:daily:{YYYY-MM-DD}:{subscription_id}:{instructor_id}`.

**Alternatives considered**:
- Separate `daily_revenue_allocations` table — rejected (duplicates ledger linkage and reporting).
- Only `allocation_date` on `revenue_allocations` without settlement_period — rejected (breaks existing FK and period status workflow).

## R2: Single-day revenue recognition

**Decision**: Add `RevenueRecognitionService::earnedAmountMinorForDay(Payment, Subscription, Carbon $date)` using `intdiv(payment * 1, totalDays)` per day, with **last subscription calendar day** absorbing lifetime remainder (mirror monthly last-period rule).

**Rationale**: Deterministic integer proration; sum of all daily earned amounts equals `payment.amount_minor` exactly.

**Alternatives considered**:
- Float daily rate — rejected (constitution).
- Monthly recognition split across days inside month — rejected (daily is authoritative mode).

## R3: Daily engagement window

**Decision**: Filter `lesson_consumptions` where `DATE(consumed_at) = allocation_date` (calendar day in app timezone UTC unless configured).

**Rationale**: Matches spec: weight by `valid_watched_seconds` for that elapsed day only.

## R4: Legacy monthly command

**Decision**: Keep `revenue:allocate --month=YYYY-MM` unchanged for backward compatibility and existing 30 tests. Mark deprecated in docs/command help. New demo path uses `--date`. Do not auto-run monthly when daily is active.

**Rationale**: Non-regression requirement; feature 001 tests depend on monthly path.

## R5: Refund storage and idempotency

**Decision**: New `refunds` table with unique `idempotency_key` = `refund:{subscription_id}:{cancellation_date}`. Add `cancelled_at` (date) on `subscriptions`. Add `SubscriptionStatus::Refunded` enum case.

**Rationale**: Refund is platform-to-student liability event; no instructor ledger mutation for standard refunds.

## R6: Refund amount calculation

**Decision**: `RefundCalculationService` computes unused future days as calendar days from `cancellation_date + 1 day` through `ends_at` inclusive. Amount = sum of daily earned portions for those days (same proration as recognition), integer only. Cancellation day is **not** in refund sum.

**Rationale**: Aligns with clarified business rules; independent of watch time on future days.

## R7: Pre-refund allocation

**Decision**: `EnsureElapsedDaysAllocatedAction` loops `starts_at..cancellation_date` and invokes daily allocation for each day not yet allocated (idempotent per day).

**Rationale**: Ensures cancellation-day engagement is allocated before refund; safe to rerun.

## R8: Payout ordering guard

**Decision**: No change to payout provider architecture. Document that `outstanding_minor` only increases via allocation ledger credits — unallocated future revenue never enters outstanding. Optional soft guard in `PayoutsRunCommand`: log warning if prior calendar month has subscriptions with unallocated elapsed days (demo-only, not blocking v1).

**Rationale**: Existing payout safety must not be rewritten; mathematical invariant already holds if allocation discipline is followed.

## R9: Filament subscription screen

**Decision**: `SubscriptionResource` (financial) with list + view, read-only fields via Infolist, single header action **Refund Unused Days** on view page with confirmation modal (cancellation date defaults to today).

**Rationale**: Matches clarify decision; demo-friendly; no student portal.

## R10: Subscription financial metrics

**Decision**: `SubscriptionFinancialSummaryService` computes display-only metrics from DB: payments, daily allocations, refunds, recognition service for earned/unearned, instructor allocations summed by subscription; instructor paid/outstanding from `instructor_balances` for instructors with allocations on subscription.

**Rationale**: DB source of truth; no cached widget math.

## R11: Dashboard widgets

**Decision**: 4–6 Filament stats widgets + 2 table widgets (top instructors). Register on default Filament dashboard. Each widget runs explicit Eloquent aggregate queries.

**Rationale**: Practical for hiring demo; avoids over-engineering a BI layer.

## R12: Test strategy

**Decision**: Add feature tests in new files; run full suite (30 existing + new) on every phase gate. Use `FakePayoutProvider` in tests; do not modify existing payout tests unless additive fixtures required.

**Rationale**: Protect non-regression mandate.
