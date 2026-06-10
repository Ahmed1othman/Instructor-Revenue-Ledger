# Implementation Plan: Daily Allocation, Refunds, and Financial Admin Visibility

**Branch**: `002-daily-allocation-refunds-admin` | **Date**: 2026-06-10 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-daily-allocation-refunds-admin/spec.md`

**Builds on**: `specs/001-instructor-financial-core` (complete — 30 passing tests)

## Plan Amendments (PATCH 2026-06-10)

- Added **Allocation Mode Policy** with hard no-overlap rule between daily and monthly in the same calendar month.
- Added **`AllocationModeGuardService`** — cross-mode guards at command/action layer with explicit error messages.
- Clarified idempotency keys (same-mode reruns) vs overlap guards (cross-mode prevention) as **distinct** mechanisms.
- Refund flow: **daily allocation only** via `EnsureElapsedDaysAllocatedAction`; never monthly.
- Subscription screen + dashboard: based on daily/refund lifecycle; refund demos must not mix legacy monthly months.
- Payout ordering: allocated outstanding only; daily period complete before monthly payout; provider success unchanged.
- Risks: cross-mode overlap mitigated by guards + idempotency, not key namespaces alone.

## Summary

Extend the existing Laravel 11 Instructor Revenue Ledger with **daily revenue allocation** as the **official** earning mode, **standard unused-days refunds** (Filament-triggered), **subscription financial visibility**, and a **Filament financial dashboard** — without rebuilding the app, weakening payout idempotency, or breaking existing tests.

**Official allocation mode (feature 002)**: daily allocation via `revenue:allocate --date=YYYY-MM-DD`. This drives refunds, subscription financial views, dashboard metrics, and the documented demo lifecycle.

**Legacy only**: `revenue:allocate --month=YYYY-MM` from feature 001 may remain for backward compatibility and existing tests. It is **not** an alternative active production mode for the new refund/admin financial lifecycle and must not be mixed with daily allocation in the same calendar month.

Monthly payout (`payouts:run`) and all feature 001 payout safety behaviors remain unchanged.

## Technical Context

**Language/Version**: PHP 8.3 FPM (Laravel 11)

**Primary Dependencies**: Filament v3, Livewire v3, Redis (queues/cache), Pest 3, custom Docker

**Storage**: MySQL 8 (financial source of truth); Redis queues/cache only

**Testing**: Pest — additive feature tests; full suite must remain green (30 existing + new)

**Target Platform**: Docker Compose (unchanged)

**Constraints**:

- Integer minor units only; no floats
- Append-only ledger; no mutation of historical entries
- Daily allocation + refunds idempotent
- Provider calls outside DB transactions (unchanged)
- No Laravel Sail; no Docker rebuild
- InstructorBalanceResource stays read-only for payouts

**Scale/Scope**: Hiring-challenge enhancement; demo-friendly Filament admin

## Constitution Check

*GATE: Must pass before implementation. Re-check after Phase 1 design.*

| Gate | Status | How plan satisfies |
|------|--------|-------------------|
| No floats for money | PASS | Daily recognition/refund use `intdiv`/`%`; last-day remainder rule |
| MySQL source of truth | PASS | refunds, allocations, ledger in MySQL; widgets query DB |
| Redis queues/cache only | PASS | No financial state in Redis |
| Append-only ledger | PASS | Standard refunds write no reversals; no updates to old entries |
| Idempotency | PASS | Per-mode idempotency keys + cross-mode `AllocationModeGuardService` |
| Safe payouts | PASS | No changes to provider layer, jobs, `active_snapshot_key` |
| Deterministic allocation | PASS | Reuse `AllocationRoundingService` / LRM |
| Scope | PASS | Filament admin only; no student dashboard |
| Testing | PASS | Additive Pest tests; existing suite protected |
| Docker / no Sail | PASS | Same compose commands |

**Post-design re-check**: PASS. No Complexity Tracking violations.

## Allocation Mode Policy

### Official vs legacy

| Mode | Command | Role in feature 002 |
|------|---------|---------------------|
| **Daily (official)** | `revenue:allocate --date=YYYY-MM-DD` | Production earning path; refunds; admin visibility; demos |
| **Monthly (legacy)** | `revenue:allocate --month=YYYY-MM` | Feature 001 backward compatibility only; not for refund demo data |

### No daily/monthly overlap (hard rule)

A **settlement month must never mix** daily and monthly allocations. Separate idempotency key namespaces alone are **not** sufficient to prevent double allocation — cross-mode guards are required.

| Condition | Block |
|-----------|-------|
| Any **daily** settlement period with allocations exists in calendar month M | **Monthly** allocation for month M |
| A **monthly** settlement period with allocations exists for month M | **Daily** allocation for any date in month M |

This prevents the same subscription-period revenue slice from being allocated twice under different modes.

### Cross-mode allocation guards (implementation)

Add `AllocationModeGuardService` (or equivalent) invoked by **both** allocation entry points before work begins.

**`revenue:allocate --date=YYYY-MM-DD`** — before allocating:

1. Resolve calendar month from `--date`
2. If a **monthly** `settlement_period` for that month has any `revenue_allocations` → **stop with error**

Example error:

```text
Cannot run daily allocation for 2026-01-10 because monthly allocation already exists for 2026-01.
```

**`revenue:allocate --month=YYYY-MM`** — before allocating:

1. If any **daily** `settlement_period` in that month has any `revenue_allocations` → **stop with error**

Example error:

```text
Cannot run monthly allocation for 2026-01 because daily allocations already exist for this month.
```

### Idempotency vs overlap guards (distinct roles)

| Mechanism | Purpose |
|-----------|---------|
| **Idempotency keys** (`allocation:daily:…` vs `allocation:{period}:…`) | Prevent duplicate execution of the **same** allocation mode (rerun same date/month) |
| **Cross-mode allocation guards** | Prevent duplicate allocation **across** daily and monthly modes in the same month |

Both are required. Do not treat separate key namespaces as mitigation for cross-mode overlap.

### Refund dependency

Refund flow uses **daily allocation only**:

- `EnsureElapsedDaysAllocatedAction` loops elapsed calendar days through cancellation day inclusive and calls `AllocateRevenueForDayAction` for each
- It **must not** call `AllocateRevenueForSettlementAction` or monthly allocation
- Refund demo data must be built with daily allocation only (no mixed-month datasets)

## Risk Controls (explicit)

| Risk | Mitigation |
|------|------------|
| Break 30 existing tests | Do not modify monthly allocation logic behavior; additive migrations with defaults; run full suite each phase |
| Double allocation daily | Unique idempotency_key on `revenue_allocations`; ledger idempotency |
| Daily + monthly double-count (cross-mode) | `AllocationModeGuardService` hard blocks mixed months; plus per-mode idempotency keys |
| Double refund | Unique `refunds.idempotency_key`; Filament action idempotent |
| Payout pays unallocated revenue | Outstanding only from ledger credits; document invariant; optional log guard |
| Mutate ledger history | Refund flow creates refund row only; no UPDATE on ledger |
| Weaken payout timeout safety | Zero changes to `ProcessInstructorPayoutAction`, jobs, reconcile command |
| Float drift | Code review + tests assert integer types |
| Filament scope creep | Subscription resource: one refund action only; InstructorBalanceResource untouched |

## Project Structure

### Documentation (this feature)

```text
specs/002-daily-allocation-refunds-admin/
├── plan.md                 # This file
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── artisan-commands.md
│   └── refund-flow.md
└── tasks.md                # /speckit-tasks (next)
```

### Source Code (additions / changes)

```text
app/
├── Domain/
│   ├── Revenue/
│   │   ├── Actions/
│   │   │   ├── AllocateRevenueForSettlementAction.php    # unchanged (legacy monthly)
│   │   │   ├── AllocateRevenueForDayAction.php           # NEW
│   │   │   └── EnsureElapsedDaysAllocatedAction.php      # NEW
│   │   ├── Services/
│   │   │   ├── RevenueRecognitionService.php             # extend: earnedAmountMinorForDay, unusedDaysAmount
│   │   │   ├── RevenueAllocationService.php              # extend: engagementWeightsForDay
│   │   │   ├── RefundCalculationService.php              # NEW
│   │   │   ├── SubscriptionFinancialSummaryService.php   # NEW
│   │   │   └── AllocationModeGuardService.php            # NEW — cross-mode month guards
│   │   └── Enums/
│   │       ├── SettlementGranularity.php                 # NEW
│   │       └── RefundStatus.php                          # NEW
│   └── Refunds/
│       └── Actions/
│           └── CreateSubscriptionRefundAction.php        # NEW
├── Console/Commands/
│   └── RevenueAllocateCommand.php                        # extend: --date flag
├── Filament/
│   ├── Resources/
│   │   └── SubscriptionResource/                         # NEW
│   │       ├── SubscriptionResource.php
│   │       ├── Pages/ListSubscriptions.php
│   │       ├── Pages/ViewSubscription.php
│   │       └── Actions/RefundUnusedDaysAction.php
│   └── Widgets/                                          # NEW (stats + tables)
│       ├── FinancialOverviewStats.php
│       ├── RevenueLiabilityStats.php
│       ├── PayoutPipelineStats.php
│       ├── SubscriptionStatusStats.php
│       ├── TopInstructorsByEarned.php
│       └── TopInstructorsByOutstanding.php
├── Models/
│   ├── Refund.php                                        # NEW
│   ├── SettlementPeriod.php                              # extend
│   ├── RevenueAllocation.php                             # extend
│   └── Subscription.php                                  # extend
database/
├── migrations/
│   └── 2026_*_add_daily_allocation_and_refunds.php       # NEW (additive)
├── factories/
│   └── RefundFactory.php                                 # NEW
tests/
├── Feature/
│   ├── Revenue/
│   │   ├── DailyAllocateRevenueTest.php                  # NEW
│   │   └── AllocateRevenueTest.php                       # unchanged
│   ├── Refunds/
│   │   └── SubscriptionRefundTest.php                    # NEW
│   └── Filament/
│       ├── SubscriptionResourceTest.php                  # NEW
│       └── InstructorBalanceResourceTest.php             # unchanged
docs/
├── ARCHITECTURE.md                                       # update
└── README.md                                             # update
```

---

## Phased Implementation Plan

### Phase 0: Foundation — Schema, Enums, Models

**Goal**: Additive database layer without changing runtime behavior.

**Tasks**:

1. Migration `add_daily_allocation_and_refunds`:
   - `settlement_periods.granularity` default `monthly`
   - `revenue_allocations.allocation_date` nullable
   - `subscriptions.cancelled_at`, `refunded_at`
   - `refunds` table (per data-model.md)
   - Indexes: idempotency, reporting
2. Enums: `SettlementGranularity`, `RefundStatus`; extend `SubscriptionStatus::Refunded`
3. Models: `Refund`, extend `SettlementPeriod`, `RevenueAllocation`, `Subscription`
4. `RefundFactory`
5. Run `php artisan migrate`; run existing 30 tests — **must pass**

**Gate**: Migration succeeds; zero test regressions.

---

### Phase 1: Daily Allocation (P1)

**Goal**: Official allocation path via `--date`.

**Tasks**:

1. **RevenueRecognitionService**
   - `earnedAmountMinorForDay(Payment, Subscription, Carbon $date)`
   - `isLastSubscriptionDay(Subscription, Carbon $date)` for remainder
   - `unusedFutureDaysAmountMinor(Payment, Subscription, Carbon $cancellationDate)` for refunds
2. **RevenueAllocationService**
   - `engagementWeightsForDay(Subscription, Carbon $date)`
3. **AllocationModeGuardService** (NEW)
   - `assertDailyAllocationAllowed(Carbon $date)` — block if monthly allocations exist in that month
   - `assertMonthlyAllocationAllowed(int $year, int $month)` — block if daily allocations exist in that month
   - Called at start of daily and monthly allocation actions/commands
4. **AllocateRevenueForDayAction**
   - Call `AllocationModeGuardService` first
   - Resolve/create daily `SettlementPeriod` (`granularity=daily`, `period_start=period_end=date`)
   - Reject future dates
   - Per payment/subscription active on date: recognize → pool → weights → LRM → `revenue_allocations` + ledger + balance
   - Idempotency key: `allocation:daily:{date}:{sub}:{instructor}` (same-mode rerun only)
5. **AllocateRevenueForSettlementAction** (legacy monthly)
   - Call `AllocationModeGuardService` at start (additive hook; preserve existing behavior when guard passes)
6. **RevenueAllocateCommand**
   - Add `--date=YYYY-MM-DD`
   - Mutual exclusion with `--month` on same invocation
   - Default when no flags: yesterday's date (daily policy)
   - Deprecation notice on `--month` in command description
7. **Tests**: `DailyAllocateRevenueTest.php`
   - One-day calculation
   - Idempotency (same date twice)
   - Future date rejected
   - Engagement filtered to calendar day
   - Integer pool sum exact
   - **Cross-mode guard**: daily blocked when monthly exists for month; monthly blocked when daily exists (dedicated test file or cases)

**Gate**: New daily tests pass; existing `AllocateRevenueTest` unchanged and green.

---

### Phase 2: Refunds (P2)

**Goal**: Standard unused-days refund with pre-allocation through cancel day.

**Tasks**:

1. **EnsureElapsedDaysAllocatedAction**
   - Loop calendar days `starts_at..cancellation_date`; call **`AllocateRevenueForDayAction` only** (never monthly)
   - Each day benefits from daily idempotency + cross-mode guards if misconfigured data exists
2. **RefundCalculationService**
   - Used/unused day counts
   - `amount_minor` from unused future daily earned sum
   - `preview()` for remaining refundable on subscription screen
3. **CreateSubscriptionRefundAction**
   - Orchestrate: idempotency → ensure allocated → calculate → persist refund → update subscription
   - No ledger writes
4. Optional: `refunds:process` command for Pest
5. **Tests**: `SubscriptionRefundTest.php`
   - Cancel day counted used; refund starts next day
   - Jan 1–30 / cancel Jan 10 scenario
   - Pre-refund allocates missing days
   - Duplicate refund idempotent
   - No instructor ledger entries added on refund

**Gate**: Refund tests pass; ledger entry count unchanged after refund (except pre-allocation credits).

---

### Phase 3: Filament Subscription Financial Screen (P3)

**Goal**: Read-only subscription lifecycle view + refund action.

**Tasks**:

1. **SubscriptionFinancialSummaryService** — metrics for Infolist; computed from **daily allocation + refund lifecycle** (earned/unearned/refunded/remaining). Legacy monthly allocations on a subscription may display if present from old demos but **refund demo datasets must use daily-only months** (no mixed-mode months).
2. **SubscriptionResource**
   - List: student, plan, status, dates, payment amount
   - View Infolist: all fields from FR-018
   - Read-only: no create/edit/delete
3. **RefundUnusedDaysAction** on view page
   - Label: **Refund Unused Days**
   - Form: cancellation date (default today)
   - Calls `CreateSubscriptionRefundAction`
   - Confirmation modal with computed preview amount
4. **Tests**: `SubscriptionResourceTest.php`
   - HTTP/Livewire smoke: list/view 200
   - Financial fields visible
   - Refund action present; idempotent on double submit
   - InstructorBalanceResource tests still pass

**Gate**: Filament tests pass; InstructorBalanceResource unchanged behavior.

---

### Phase 4: Filament Dashboard Widgets (P4)

**Goal**: DB-backed financial overview.

**Widgets** (group on default dashboard):

| Widget | Metrics |
|--------|---------|
| FinancialOverviewStats | total payments, earned, unearned liability, total refunds |
| RevenueSplitStats | platform earned, instructor allocated, paid, outstanding |
| PayoutPipelineStats | pending, pending_confirmation, failed counts |
| SubscriptionStatusStats | active, refunded/cancelled counts |
| TopInstructorsByEarned | table top 5 |
| TopInstructorsByOutstanding | table top 5 |

**Implementation notes**:

- Eloquent `sum()` / `count()` on authoritative tables
- Metrics reflect **daily allocation / refund lifecycle** as the official model; do not blend legacy monthly demo months into feature 002 refund dashboard scenarios
- No Redis cache for money totals
- Register in `AdminPanelProvider` or widget discovery

**Tests**: `FinancialDashboardWidgetTest.php` (spot-check key totals against seeded data)

**Gate**: Widget tests pass; dashboard loads without N+1 explosions (use `with()` where needed).

---

### Phase 5: Payout Ordering Documentation & Optional Guard

**Goal**: Preserve payout architecture; document ordering.

**Tasks**:

1. Add comment/docblock in `PayoutsRunCommand`: pays `outstanding_minor > 0` only; outstanding from allocation
2. Optional: `AllocationCompletenessService::unallocatedDaysInMonth($year, $month)` — log warning only
3. **Do not** change `ProcessInstructorPayoutAction`, provider bindings, or job retry logic
4. Re-run all payout tests: `PayoutCommandTest`, `PayoutJobRetryTest`, `PayoutTimeoutReconcileTest`

**Gate**: All 10 payout tests still pass unchanged.

---

### Phase 6: Documentation & Demo Seeder Update

**Goal**: Interview-ready docs; daily demo path.

**Tasks**:

1. **README.md** — daily allocation official; legacy monthly note; refund Filament path; dashboard
2. **docs/ARCHITECTURE.md** — align with locked lifecycle rules; daily-only policy; refund policy
3. **docs/AI_USAGE.md** — note feature 002 scope if needed
4. **DemoFinancialCoreSeeder** (optional enhancement): document daily allocate loop in README rather than automating 30 days in seeder
5. **quickstart.md** validation walkthrough

**Gate**: Manual quickstart steps verifiable.

---

### Phase 7: Final Validation

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

**Success**: ≥30 existing tests + all new feature 002 tests green.

---

## Payout Ordering Plan (detail)

**Invariant**: `instructor_balances.outstanding_minor` increases only when `earning_credit` ledger entries are applied. Unallocated future days never create earning credits → never enter outstanding. Payouts pay **allocated outstanding only** — never unallocated future revenue.

**Monthly payout** (`payouts:run`) — architecture **unchanged**:

1. Select balances where `outstanding_minor > 0` (already allocated earnings only)
2. Create payout per instructor with `active_snapshot_key` (unchanged)
3. Jobs call provider; **provider success is the only event** that writes `payout_debit`, increases `total_paid_minor`, and decreases `outstanding_minor`
4. Pending, processing, failed, and `pending_confirmation` payouts do **not** change paid/outstanding

**Daily allocation lifecycle + monthly payout ordering**:

- For the official daily path, run **daily allocation for every elapsed day** in the target payout month before `payouts:run`
- Monthly payout pays whatever outstanding has been **allocated** by daily runs; it does not allocate or pay future unearned days
- Optional: `AllocationCompletenessService` logs warning if elapsed days in prior month lack daily allocation (soft enforcement; does not rewrite payout provider)

**No changes to**: `MockPayoutProvider`, `FakePayoutProvider`, `ProcessInstructorPayoutAction`, `CheckPayoutStatusAction`, reconcile command, or provider-outside-transaction semantics.

---

## Test Strategy

| Layer | Files | Proves |
|-------|-------|--------|
| Unit | Extend `RevenueRecognitionServiceTest` | Daily earned amount, unused-days refund amount, last-day remainder |
| Feature | `DailyAllocateRevenueTest` | One day, idempotency, future rejection, cross-mode guard |
| Feature | `AllocationModeGuardTest` | Daily blocked after monthly; monthly blocked after daily |
| Feature | `SubscriptionRefundTest` | Cancel day, pre-allocate, duplicate block, no reversals |
| Feature | `SubscriptionResourceTest` | Filament view fields, refund action |
| Feature | `FinancialDashboardWidgetTest` | Widget totals |
| Regression | All 001 tests | No modifications to expected behavior |

**CI command**: `docker compose exec app php artisan test`

**Order of execution during development**: Run full suite after every phase gate.

---

## Key Files Expected to Change

| Category | Files |
|----------|-------|
| Migrations | 1 new additive migration |
| Domain | ~8 new/extended PHP files under `Revenue/`, `Refunds/` |
| Commands | `RevenueAllocateCommand.php` |
| Filament | `SubscriptionResource/*`, 6 widgets |
| Models | 4 extended/new |
| Tests | 4–5 new test files |
| Docs | README, ARCHITECTURE, optional AI_USAGE |
| Unchanged | Payout actions/jobs/provider, `InstructorBalanceResource`, monthly allocation action logic |

---

## Main Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Daily + monthly double-count if both run on same subscription/month | Medium | High | **Explicit cross-mode allocation guards** (`AllocationModeGuardService`) plus per-mode idempotency keys; legacy monthly not used in refund demo months |
| Refund amount drift vs manual calculation | Medium | Medium | Shared `RevenueRecognitionService` for daily + refund |
| Filament refund double-click | Low | Medium | Idempotent action + unique refund key |
| Widget query performance | Low | Low | Simple aggregates; hiring-scale data |
| Breaking 001 tests | Medium | High | Phase gates; avoid editing monthly code paths |

---

## Complexity Tracking

No constitution violations requiring justification.

---

## Next Steps

1. `/speckit-tasks` — generate `tasks.md` from this plan
2. `/speckit-implement` — execute phase by phase with test gates
