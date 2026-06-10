---
description: "Task list for Daily Allocation, Refunds, and Financial Admin Visibility"
---

# Tasks: Daily Allocation, Refunds, and Financial Admin Visibility

**Input**: Design documents from `specs/002-daily-allocation-refunds-admin/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, feature 001 complete (30 tests passing)

**Tests**: REQUIRED — Pest tests for daily allocation, cross-mode guards, refunds, Filament, and dashboard. Run full suite after every phase gate.

**Organization**: Phases 0–7; user stories US1 (daily allocation), US2 (refunds), US3 (subscription screen), US4 (dashboard).

**Docker**: `docker compose exec app php artisan ...` — not Sail.

## Critical implementation warnings

- **Additive migrations only** — do not edit feature 001 migration files.
- **Integer minor units only** — no floats in financial calculations.
- **Append-only ledger** — do not UPDATE or DELETE existing `instructor_ledger_entries`.
- **Standard refunds** — no `earning_reversal` or `clawback` ledger entries.
- **Refund flow** — daily allocation only via `AllocateRevenueForDayAction`; never monthly.
- **Cross-mode guards** — `AllocationModeGuardService` blocks daily/monthly overlap in same calendar month; idempotency keys alone are insufficient.
- **Payout architecture** — do NOT modify `ProcessInstructorPayoutAction`, payout jobs, `PayoutProvider`, `MockPayoutProvider`, `FakePayoutProvider`, or `active_snapshot_key` logic unless explicitly required (Phase 5 is docs/warning only).
- **InstructorBalanceResource** — do not modify `app/Filament/Resources/InstructorBalanceResource.php` or its pages/relation managers.
- **Legacy monthly** — preserve `AllocateRevenueForSettlementAction` behavior for feature 001 tests; only add guard hook at entry.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no incomplete-task dependencies)
- **[Story]**: US1, US2, US3, US4

---

## Phase 0: Foundation — Schema, Enums, Models

**Purpose**: Additive database layer without changing runtime behavior.

**⚠️ CRITICAL**: No US1–US4 work until checkpoint passes with 30 existing tests green.

- [x] T001 Create additive migration `database/migrations/2026_*_add_daily_allocation_and_refunds.php`: `settlement_periods.granularity` (default `monthly`), unique `(granularity, period_start)` as `sp_granularity_period_start_uniq`, `revenue_allocations.allocation_date` nullable + index, `subscriptions.cancelled_at` and `refunded_at`, `refunds` table per `specs/002-daily-allocation-refunds-admin/data-model.md` with unique `idempotency_key`
- [x] T002 [P] Create `app/Domain/Revenue/Enums/SettlementGranularity.php` (`monthly`, `daily`)
- [x] T003 [P] Create `app/Domain/Revenue/Enums/RefundStatus.php` (`pending`, `completed`, `failed`)
- [x] T004 Extend `app/Domain/Revenue/Enums/SubscriptionStatus.php` with `Refunded` case; do not remove existing cases
- [x] T005 Create `app/Models/Refund.php` with relationships to `subscription`, `payment`, `student`; integer casts for `amount_minor`
- [x] T006 [P] Extend `app/Models/SettlementPeriod.php`: cast `granularity`, scope/helpers for daily vs monthly
- [x] T007 [P] Extend `app/Models/RevenueAllocation.php`: fillable/casts for `allocation_date`
- [x] T008 Extend `app/Models/Subscription.php`: `cancelled_at`, `refunded_at`, `refunds()` relationship
- [x] T009 Create `database/factories/RefundFactory.php`
- [x] T010 [P] Sync `specs/002-daily-allocation-refunds-admin/data-model.md` if migration differs from plan
- [x] T011 Run `docker compose exec app php artisan migrate` and `docker compose exec app php artisan test` — **all 30 existing tests must pass**; no new feature behavior yet

**Checkpoint**: Migration applied; zero regressions on feature 001 test suite.

---

## Phase 1: User Story 1 — Daily Allocation (Priority: P1) 🎯 MVP

**Goal**: Official allocation via `revenue:allocate --date=YYYY-MM-DD` with same-mode idempotency and cross-mode month guards.

**Independent Test**: Seed subscription + consumptions for one day; run `--date`; verify allocations, ledger, balances; rerun same date (no duplicates); verify guard blocks mixed monthly/daily month.

### Tests for US1 (write first — must fail before implementation)

- [x] T012 [P] [US1] Create `tests/Feature/Revenue/AllocationModeGuardTest.php`: daily blocked when monthly allocations exist for month; monthly blocked when daily allocations exist for month; assert exact error messages from plan
- [x] T013 [P] [US1] Create `tests/Feature/Revenue/DailyAllocateRevenueTest.php` scaffold: one-day allocation, same-date idempotency, future date rejected, engagement filtered to calendar day, pool sum exact (LRM)
- [x] T014 [P] [US1] Extend `tests/Unit/Domain/Revenue/RevenueRecognitionServiceTest.php` with `earnedAmountMinorForDay`, last-subscription-day remainder, and lifetime sum equals payment

### Implementation for US1

- [x] T015 [US1] Create `app/Domain/Revenue/Services/AllocationModeGuardService.php` with `assertDailyAllocationAllowed(Carbon $date)` and `assertMonthlyAllocationAllowed(int $year, int $month)` querying `settlement_periods` + `revenue_allocations` per plan
- [x] T016 [P] [US1] Extend `app/Domain/Revenue/Services/RevenueRecognitionService.php`: `earnedAmountMinorForDay`, `isLastSubscriptionDay`, `unusedFutureDaysAmountMinor` (integer `intdiv`/`%` only — no floats)
- [x] T017 [P] [US1] Extend `app/Domain/Revenue/Services/RevenueAllocationService.php`: `engagementWeightsForDay(Subscription, Carbon $date)` filtering `DATE(consumed_at) = date`
- [x] T018 [US1] Create `app/Domain/Revenue/Actions/AllocateRevenueForDayAction.php`: call guard first; reject future dates; resolve/create daily `SettlementPeriod`; allocate with idempotency key `allocation:daily:{date}:{sub}:{instructor}`; ledger + balance via existing actions
- [x] T019 [US1] Add **only** `AllocationModeGuardService` call at start of `app/Domain/Revenue/Actions/AllocateRevenueForSettlementAction.php` — preserve all existing monthly allocation logic for feature 001 tests
- [x] T020 [US1] Extend `app/Console/Commands/RevenueAllocateCommand.php`: `--date=YYYY-MM-DD`; mutual exclusion with `--month`; default to yesterday when no flags; deprecate `--month` in description; wire daily path to `AllocateRevenueForDayAction`
- [x] T021 [US1] Complete `tests/Feature/Revenue/AllocationModeGuardTest.php` — all cases green
- [x] T022 [US1] Complete `tests/Feature/Revenue/DailyAllocateRevenueTest.php` — all cases green
- [x] T023 [US1] Run `docker compose exec app php artisan test --filter=AllocationModeGuardTest` and `DailyAllocateRevenueTest` and `AllocateRevenueTest` — new + **all 30 legacy tests** pass

**Checkpoint**: Daily allocation official path works; cross-mode guards enforced; monthly legacy tests unchanged.

---

## Phase 2: User Story 2 — Standard Refunds (Priority: P2)

**Goal**: Unused future days refund with pre-allocation through cancellation day (daily only); no instructor reversals.

**Independent Test**: Partial daily allocation; refund on Jan 10; pre-allocates Jan 1–10; refund amount covers Jan 11–end; duplicate refund idempotent; no new ledger entries on refund (except pre-allocation credits).

### Tests for US2 (write first)

- [x] T024 [P] [US2] Create `tests/Feature/Refunds/SubscriptionRefundTest.php`: cancellation day used; refund starts next day; Jan 1–30 cancel Jan 10 amount; pre-refund allocates missing days; duplicate refund blocked/idempotent; assert no `earning_reversal`/`clawback` ledger entries; ledger count unchanged after refund except pre-allocation

### Implementation for US2

- [x] T025 [P] [US2] Create `app/Domain/Revenue/Services/RefundCalculationService.php`: used/unused day counts; `amount_minor` from unused future daily earned sum; `preview()` for remaining refundable; integer only
- [x] T026 [US2] Create `app/Domain/Revenue/Actions/EnsureElapsedDaysAllocatedAction.php`: loop `starts_at..cancellation_date` inclusive; call **`AllocateRevenueForDayAction` only** — must NOT call monthly allocation
- [x] T027 [US2] Create `app/Domain/Refunds/Actions/CreateSubscriptionRefundAction.php`: idempotency key `refund:{subscription_id}:{cancellation_date}`; orchestrate ensure allocated → calculate → persist refund → update subscription (`cancelled_at`, `refunded_at`, `status=refunded`); **no ledger writes**
- [x] T028 [P] [US2] Create optional `app/Console/Commands/ProcessSubscriptionRefundCommand.php` (`refunds:process`) for Pest/CLI demo wrapping `CreateSubscriptionRefundAction`
- [x] T029 [US2] Complete `tests/Feature/Refunds/SubscriptionRefundTest.php` — all cases green
- [x] T030 [US2] Run `docker compose exec app php artisan test --filter=SubscriptionRefundTest` and full suite — no regressions

**Checkpoint**: Refund flow complete; standard refunds do not mutate ledger history.

---

## Phase 3: User Story 3 — Filament Subscription Financial Screen (Priority: P3)

**Goal**: Read-only subscription lifecycle view + **Refund Unused Days** action on view page only.

**Independent Test**: Filament list/view loads; financial fields match DB; refund action works and is idempotent; `InstructorBalanceResourceTest` still passes.

**⚠️ Do not modify** `app/Filament/Resources/InstructorBalanceResource.php`.

### Tests for US3

- [x] T031 [P] [US3] Create `tests/Feature/Filament/SubscriptionResourceTest.php`: authenticated access; list/view 200; financial fields visible; **Refund Unused Days** action present; idempotent double submit; run `InstructorBalanceResourceTest` in same gate

### Implementation for US3

- [x] T032 [US3] Create `app/Domain/Revenue/Services/SubscriptionFinancialSummaryService.php`: compute paid, earned, unearned, refunded, remaining refundable, platform earned, instructor allocated/paid/outstanding from **daily allocation + refund lifecycle** (DB source of truth)
- [x] T033 [US3] Create `app/Filament/Resources/SubscriptionResource.php` with `Pages/ListSubscriptions.php` and `Pages/ViewSubscription.php`: read-only list/view; Infolist fields per FR-018; `canCreate`/`canEdit`/`canDelete` false; no payout actions
- [x] T034 [US3] Create `app/Filament/Resources/SubscriptionResource/Actions/RefundUnusedDaysAction.php` on view page only: label **Refund Unused Days**; cancellation date form; confirmation with preview; calls `CreateSubscriptionRefundAction`
- [x] T035 [US3] Complete `tests/Feature/Filament/SubscriptionResourceTest.php` — all cases green
- [x] T036 [US3] Run `docker compose exec app php artisan test --filter=SubscriptionResourceTest` and `InstructorBalanceResourceTest` and full suite

**Checkpoint**: Subscription financial screen demo-ready; instructor balance resource unchanged.

---

## Phase 4: User Story 4 — Filament Dashboard Widgets (Priority: P4)

**Goal**: DB-backed financial dashboard widgets for platform totals and top instructors.

**Independent Test**: Widget totals match seeded payments, daily allocations, refunds, payouts for daily-only demo data.

### Tests for US4

- [x] T037 [P] [US4] Create `tests/Feature/Filament/FinancialDashboardWidgetTest.php`: spot-check payment, earned, refund, instructor allocated/paid/outstanding totals against known seed data

### Implementation for US4

- [x] T038 [P] [US4] Create `app/Filament/Widgets/FinancialOverviewStats.php` — total payments, earned/recognized revenue, unearned liability, total refunds (Eloquent sums, integer minor units)
- [x] T039 [P] [US4] Create `app/Filament/Widgets/RevenueSplitStats.php` — platform earned, instructor allocated, paid, outstanding
- [x] T040 [P] [US4] Create `app/Filament/Widgets/PayoutPipelineStats.php` — pending, pending_confirmation, failed payout counts
- [x] T041 [P] [US4] Create `app/Filament/Widgets/SubscriptionStatusStats.php` — active vs cancelled/refunded counts
- [x] T042 [P] [US4] Create `app/Filament/Widgets/TopInstructorsByEarned.php` — table widget top 5 by `total_earned_minor`
- [x] T043 [P] [US4] Create `app/Filament/Widgets/TopInstructorsByOutstanding.php` — table widget top 5 by `outstanding_minor`
- [x] T044 [US4] Register widgets on Filament dashboard via `app/Providers/Filament/AdminPanelProvider.php` or discovery; no Redis cache for money totals
- [x] T045 [US4] Complete `tests/Feature/Filament/FinancialDashboardWidgetTest.php` — all cases green
- [x] T046 [US4] Run `docker compose exec app php artisan test --filter=FinancialDashboardWidgetTest` and full suite

**Checkpoint**: Dashboard loads; widget math matches DB.

---

## Phase 5: Payout Ordering — Documentation & Soft Guard Only

**Goal**: Document payout ordering; optional allocation-completeness warning. **No payout logic rewrite.**

**⚠️ CRITICAL**: Do NOT change `app/Domain/Payouts/Actions/ProcessInstructorPayoutAction.php`, payout jobs, provider classes, or provider success semantics.

- [x] T047 [P] Create optional `app/Domain/Revenue/Services/AllocationCompletenessService.php` — `unallocatedElapsedDaysInMonth($year, $month)`; log warning only (no payout block)
- [x] T048 Add docblock/comments to `app/Console/Commands/PayoutsRunCommand.php`: pays `outstanding_minor > 0` only; outstanding from allocation; provider success only moves paid; daily period should be fully allocated before monthly payout in official lifecycle
- [x] T049 Run `docker compose exec app php artisan test --filter=Payout` — **all 10 payout tests pass unchanged**

**Checkpoint**: Payout safety preserved; documentation clear.

---

## Phase 6: Documentation & Demo

**Purpose**: Interview-ready docs; daily official path; legacy monthly note; refund policy.

- [x] T050 [P] Update `README.md`: daily `--date` official path; legacy `--month`; cross-mode guard rule; Filament subscription + refund action; dashboard; demo commands; no floats
- [x] T051 [P] Update `docs/ARCHITECTURE.md`: daily-only official mode; `AllocationModeGuardService`; refund cancellation-day rule; no instructor reversals for standard refunds; exceptional refunds doc-only; payout ordering unchanged
- [x] T052 [P] Update `docs/AI_USAGE.md` with feature 002 scope note if needed
- [x] T053 Update `specs/002-daily-allocation-refunds-admin/quickstart.md` to match implemented commands and daily-only refund demo (no mixed monthly/daily months)
- [x] T054 [P] Document in `README.md` daily allocation loop for `DemoFinancialCoreSeeder` demo (do not require seeder to auto-run 30 daily commands unless explicitly added later)

**Checkpoint**: Docs match implementation; quickstart manually verifiable.

---

## Phase 7: Final Validation

- [x] T055 Run `docker compose exec app php artisan migrate` and `docker compose exec app php artisan test` — **full suite green** (30 feature 001 + all feature 002 tests)
- [x] T056 Manual validation per `specs/002-daily-allocation-refunds-admin/quickstart.md`: migrate → seed → daily allocate days → Filament refund → `payouts:run` + queue worker → dashboard review

**Checkpoint**: Feature 002 complete; ready for submission.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 0**: No feature 002 code dependencies — blocks all US work
- **Phase 1 (US1)**: Depends on Phase 0 — blocks refunds (US2) and accurate subscription metrics
- **Phase 2 (US2)**: Depends on Phase 1 (`AllocateRevenueForDayAction`, `RefundCalculationService` recognition helpers)
- **Phase 3 (US3)**: Depends on Phase 2 (`CreateSubscriptionRefundAction`, summary service can start after Phase 1 but refund action needs Phase 2)
- **Phase 4 (US4)**: Depends on Phase 0; richest after Phases 1–2 (refunds in totals)
- **Phase 5**: Depends on Phase 1; parallel with 3–4 after US1 complete
- **Phase 6–7**: Depends on all implementation phases

### User Story Dependencies

| Story | Depends on | Independent test |
|-------|------------|------------------|
| US1 Daily allocation | Phase 0 | `DailyAllocateRevenueTest`, `AllocationModeGuardTest` |
| US2 Refunds | US1 | `SubscriptionRefundTest` |
| US3 Subscription screen | US1, US2 | `SubscriptionResourceTest` |
| US4 Dashboard | Phase 0; best after US1–2 | `FinancialDashboardWidgetTest` |

### Parallel Opportunities

- Phase 0: T002–T004, T006–T007, T009–T010 in parallel after T001 drafted
- Phase 1: T012–T014 parallel; T016–T017 parallel after T015
- Phase 2: T024 parallel with T025; T028 parallel after T027
- Phase 4: T038–T043 parallel widget files
- Phase 6: T050–T052 parallel

---

## Parallel Example: User Story 1

```bash
# Tests together (after Phase 0):
T012 AllocationModeGuardTest.php
T013 DailyAllocateRevenueTest.php
T014 RevenueRecognitionServiceTest.php (extend)

# Services together (after T015 guard):
T016 RevenueRecognitionService.php
T017 RevenueAllocationService.php
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 0
2. Complete Phase 1 (US1)
3. **STOP and VALIDATE**: `revenue:allocate --date=...` + guard tests + 30 legacy tests green

### Incremental Delivery

1. Phase 0 → schema ready
2. Phase 1 → daily allocation → **MVP**
3. Phase 2 → refunds
4. Phase 3 → subscription Filament screen
5. Phase 4 → dashboard widgets
6. Phase 5 → payout docs (no logic change)
7. Phase 6–7 → docs + full validation

### Docker Command Reference

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan revenue:allocate --date=2026-01-04
docker compose exec app php artisan revenue:allocate --month=2026-01   # legacy only
docker compose exec app php artisan refunds:process {id} --cancel-date=2026-01-10
docker compose exec app php artisan payouts:run
docker compose exec app php artisan queue:work redis --tries=3
docker compose exec app php artisan test
```

---

## Notes

- Do not create `earning_reversal` or `clawback` ledger types in this feature
- Do not use floats; use `intdiv` and `%` only
- Do not mix daily and monthly allocations in the same calendar month (guards enforce)
- Refund demos must use daily allocation only for the target month
- `pending_confirmation` payouts keep non-null `active_snapshot_key` until terminal resolution (unchanged from 001)
- Provider calls must never run inside open DB transactions (unchanged from 001)
