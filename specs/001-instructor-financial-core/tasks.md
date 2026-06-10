---
description: "Task list for Instructor Financial Core implementation"
---

# Tasks: Instructor Financial Core

**Input**: Design documents from `specs/001-instructor-financial-core/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: REQUIRED — Pest tests for all business-critical financial behavior per constitution v1.0.1.

**Organization**: Tasks grouped by user story (US1 allocation, US2 payouts, US3 Filament visibility).

**Docker**: Run `php artisan` / `composer` in `app` container; `npm` in `node` container. Not Sail.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no incomplete-task dependencies)
- **[Story]**: US1, US2, or US3

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Domain layout and environment prerequisites on existing Laravel 11 app.

- [ ] T001 Create domain directories `app/Domain/{Money,Revenue,Ledger,Payouts}` with Actions, Services, Enums, Jobs, Contracts, Providers, DTOs subfolders per plan.md
- [ ] T002 [P] Update `.env.example` with Redis queue/cache settings (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST=redis`) and document app-container usage in comments
- [ ] T003 [P] Sync `specs/001-instructor-financial-core/data-model.md` payouts table with `active_snapshot_key` nullable unique index and remove application-only duplicate guard language
- [ ] T004 [P] Create `tests/Support/RebuildsInstructorBalances.php` trait stub for ledger-derived balance reconciliation in tests

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, enums, models, ledger — MUST complete before user story work.

**⚠️ CRITICAL**: No US1/US2/US3 work until this phase checkpoint passes.

### Migrations

- [ ] T005 Create migration `database/migrations/*_create_plans_subscriptions_payments_tables.php` for plans, subscriptions, payments with integer minor units, basis points, idempotency keys, and indexes per data-model.md
- [ ] T006 [P] Create migration `database/migrations/*_create_instructors_courses_lesson_consumptions_tables.php` including `valid_watched_seconds`, `consumed_at`, and consumption indexes
- [ ] T007 [P] Create migration `database/migrations/*_create_settlement_periods_revenue_allocations_tables.php` with unique settlement `(year, month)` and allocation idempotency unique key
- [ ] T008 [P] Create migration `database/migrations/*_create_instructor_ledger_and_balances_tables.php` for append-only ledger entries and `instructor_balances` unique `(instructor_id, currency)`
- [ ] T009 Create migration `database/migrations/*_create_payout_tables.php` for payout_batches, payouts (`balance_snapshot_hash`, nullable `active_snapshot_key` with UNIQUE index), payout_attempts per plan PATCH

### Enums

- [ ] T010 [P] Create `app/Domain/Revenue/Enums/SubscriptionStatus.php`
- [ ] T011 [P] Create `app/Domain/Revenue/Enums/PaymentStatus.php`
- [ ] T012 [P] Create `app/Domain/Revenue/Enums/SettlementPeriodStatus.php`
- [ ] T013 [P] Create `app/Domain/Ledger/Enums/LedgerEntryType.php` and `app/Domain/Ledger/Enums/LedgerDirection.php`
- [ ] T014 [P] Create `app/Domain/Payouts/Enums/PayoutBatchStatus.php`, `PayoutStatus.php`, `PayoutAttemptStatus.php`, `ProviderResultStatus.php`

### Eloquent models

- [ ] T015 [P] Create `app/Models/Plan.php` and `app/Models/Subscription.php` with enum casts and relationships
- [ ] T016 [P] Create `app/Models/Payment.php`, `app/Models/Instructor.php`, `app/Models/Course.php`
- [ ] T017 [P] Create `app/Models/LessonConsumption.php` and `app/Models/SettlementPeriod.php`
- [ ] T018 [P] Create `app/Models/RevenueAllocation.php`, `app/Models/InstructorLedgerEntry.php`, `app/Models/InstructorBalance.php`
- [ ] T019 Create `app/Models/PayoutBatch.php`, `app/Models/Payout.php`, `app/Models/PayoutAttempt.php` with `active_snapshot_key` fillable/cast rules

### Money and ledger core

- [ ] T020 Create `app/Domain/Money/Money.php` value object for integer minor-unit add/subtract with currency guard
- [ ] T021 Create `app/Domain/Ledger/Actions/RecordInstructorLedgerEntryAction.php` with idempotent insert on `idempotency_key`
- [ ] T022 Create `app/Domain/Ledger/Actions/UpdateInstructorBalanceProjectionAction.php` using `lockForUpdate` on `instructor_balances`
- [ ] T023 Implement balance rebuild logic in `tests/Support/RebuildsInstructorBalances.php` summing ledger entries to compare projections

### Factories

- [ ] T024 [P] Create factories in `database/factories/` for Plan, Subscription, Payment, Instructor, Course
- [ ] T025 [P] Create `database/factories/LessonConsumptionFactory.php` with `valid_watched_seconds` and `consumed_at`
- [ ] T026 [P] Create factories for SettlementPeriod, RevenueAllocation, InstructorLedgerEntry, InstructorBalance

### Foundational tests

- [ ] T027 Create `tests/Feature/Ledger/LedgerAndBalanceTest.php` proving idempotent ledger insert, earning credit updates, and balance equals ledger-derived totals

**Checkpoint**: `docker compose exec app php artisan migrate` succeeds; T027 passes.

---

## Phase 3: User Story 1 — Allocate Subscription Revenue (Priority: P1) 🎯 MVP

**Goal**: Monthly settlement, day-based proration, `valid_watched_seconds` allocation, Largest Remainder rounding, idempotent earning credits.

**Independent test**: Seed payment + consumptions, run `revenue:allocate --month=YYYY-MM`, verify allocations 10800/5400/1800 (demo ratio), pool sum exact, re-run creates no duplicates.

### Tests for US1

- [ ] T028 [P] [US1] Create `tests/Unit/Domain/Revenue/AllocationRoundingServiceTest.php` for 34/33/33 tie-break, pool-sum equality, and integer-only math
- [ ] T029 [P] [US1] Create `tests/Unit/Domain/Revenue/RevenueRecognitionServiceTest.php` for full-month overlap, partial-overlap proration, and lifetime recognized sum equals `payment_amount_minor`
- [ ] T030 [US1] Create `tests/Feature/Revenue/AllocateRevenueTest.php` for `valid_watched_seconds` weighting, no-engagement skip, allocation idempotency, and demo 10800/5400/1800 scenario

### Implementation for US1

- [ ] T031 [P] [US1] Create `app/Domain/Revenue/Services/AllocationRoundingService.php` implementing Largest Remainder Method per plan.md
- [ ] T032 [P] [US1] Create `app/Domain/Revenue/Services/RevenueRecognitionService.php` with day-overlap proration and last-period remainder rule
- [ ] T033 [US1] Create `app/Domain/Revenue/Services/RevenueAllocationService.php` grouping by subscription/instructor, summing `valid_watched_seconds`, skipping zero weight
- [ ] T034 [US1] Create `app/Domain/Revenue/Actions/AllocateRevenueForSettlementAction.php` orchestrating recognition, pool split, allocation, ledger credits, balance updates
- [ ] T035 [US1] Create `app/Console/Commands/RevenueAllocateCommand.php` (`revenue:allocate --month=YYYY-MM`) resolving calendar-month settlement period

**Checkpoint**: T028–T030 pass; manual `docker compose exec app php artisan revenue:allocate --month=2026-01` works with seeded data.

---

## Phase 4: User Story 2 — Safe Instructor Payouts (Priority: P2)

**Goal**: Idempotent payout command, queued jobs, mock provider, timeout reconciliation via `active_snapshot_key`, provider outside transactions.

**Independent test**: Run `payouts:run` twice with unchanged balances — no duplicate active payouts; timeout → `pending_confirmation` → `payouts:reconcile` → single debit.

### Tests for US2

- [ ] T036 [P] [US2] Create `tests/Feature/Payouts/PayoutCommandTest.php` for duplicate command prevention via `active_snapshot_key` and no double debit
- [ ] T037 [P] [US2] Create `tests/Feature/Payouts/PayoutJobRetryTest.php` for retried job safety and no re-send when succeeded/pending_confirmation
- [ ] T038 [US2] Create `tests/Feature/Payouts/PayoutTimeoutReconcileTest.php` for timeout without debit, no provider re-send, reconcile success/failure paths

### Payout provider and DTOs

- [ ] T039 [P] [US2] Create `app/Domain/Payouts/Contracts/PayoutProvider.php` and `app/Domain/Payouts/DTOs/PayoutProviderResult.php` per contracts/payout-provider.md
- [ ] T040 [P] [US2] Create `app/Domain/Payouts/Providers/FakePayoutProvider.php` with deterministic configurable outcomes for tests
- [ ] T041 [US2] Create `app/Domain/Payouts/Providers/MockPayoutProvider.php` with random success/failure/timeout_unknown for demo

### Payout actions

- [ ] T042 [US2] Create `app/Domain/Payouts/Actions/CreatePayoutBatchAction.php` and `app/Domain/Payouts/Actions/CreateInstructorPayoutAction.php` computing `balance_snapshot_hash` and setting `active_snapshot_key`
- [ ] T043 [P] [US2] Create `app/Domain/Payouts/Actions/MarkPayoutSucceededAction.php`, `MarkPayoutFailedAction.php`, `MarkPayoutPendingConfirmationAction.php` clearing `active_snapshot_key` only on terminal success/failure
- [ ] T044 [US2] Create `app/Domain/Payouts/Actions/ProcessInstructorPayoutAction.php` with status gate, provider call outside transaction, persist in new transaction
- [ ] T045 [US2] Create `app/Domain/Payouts/Actions/CheckPayoutStatusAction.php` resolving `pending_confirmation` without re-send

### Jobs and commands

- [ ] T046 [P] [US2] Create `app/Domain/Payouts/Jobs/ProcessInstructorPayoutJob.php` dispatching `ProcessInstructorPayoutAction`
- [ ] T047 [P] [US2] Create `app/Domain/Payouts/Jobs/CheckPayoutStatusJob.php` dispatching `CheckPayoutStatusAction`
- [ ] T048 [US2] Create `app/Console/Commands/PayoutsRunCommand.php` (`payouts:run`) and `app/Console/Commands/PayoutsReconcileCommand.php` (`payouts:reconcile`)
- [ ] T049 [US2] Bind `PayoutProvider` to `MockPayoutProvider` in `app/Providers/AppServiceProvider.php` and `FakePayoutProvider` in `tests/TestCase.php`

**Checkpoint**: T036–T038 pass; payout command + queue worker + reconcile flow verified.

---

## Phase 5: User Story 3 — Read-Only Financial Visibility (Priority: P3)

**Goal**: Filament read-only instructor balances, payout history, optional ledger entries.

**Independent test**: Open `/admin` Instructor Balances — earned/paid/outstanding/currency visible; no create/edit/delete/payout actions.

### Tests for US3

- [ ] T050 [US3] Create `tests/Feature/Filament/InstructorBalanceResourceTest.php` asserting list/view data and absence of write actions

### Implementation for US3

- [ ] T051 [US3] Create `app/Filament/Resources/InstructorBalanceResource.php` with list columns: instructor name, total earned, total paid, outstanding, currency
- [ ] T052 [US3] Add view page and relation managers in `app/Filament/Resources/InstructorBalanceResource/` for payout history and optional ledger entries (read-only)
- [ ] T053 [US3] Disable create/edit/delete actions in `app/Filament/Resources/InstructorBalanceResource.php`. Keep only index and view pages in `getPages()`. Do not register create/edit pages. Remove header actions, table actions, bulk actions, and any payout-trigger actions.

**Checkpoint**: T050 passes; Filament displays seeded instructor financial data.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Demo data, documentation, end-to-end validation.

- [ ] T054 [P] Create `database/seeders/DemoFinancialCoreSeeder.php` with plan, student, 3 instructors, courses, subscription/payment 30000 minor, consumptions 3600/1800/600 seconds
- [ ] T055 [P] Register `DemoFinancialCoreSeeder` in `database/seeders/DatabaseSeeder.php`
- [ ] T056 [P] Replace `README.md` with project overview, Docker setup, migrate/seed/test/allocation/payout/reconcile/Filament instructions per quickstart.md
- [ ] T057 [P] Create `docs/ARCHITECTURE.md` covering assumptions, proration, `valid_watched_seconds`, Largest Remainder, ledger, idempotency, `active_snapshot_key`, timeout handling, out-of-scope
- [ ] T058 [P] Create `docs/AI_USAGE.md` documenting AI-assisted spec/plan/tasks and human-reviewed financial decisions
- [ ] T059 Run full Pest suite via `docker compose exec app php artisan test` and fix any failures
- [ ] T060 Validate end-to-end flow per `specs/001-instructor-financial-core/quickstart.md` (allocate → payout → reconcile → Filament)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup — **blocks all user stories**
- **US1 (Phase 3)**: Depends on Foundational (ledger + schema)
- **US2 (Phase 4)**: Depends on Foundational; soft-depends on US1 for meaningful outstanding balances in demo
- **US3 (Phase 5)**: Depends on Foundational; soft-depends on US1/US2 for rich history in demo
- **Polish (Phase 6)**: Depends on US1–US3

### User Story Dependencies

- **US1 (P1)**: Can start after Phase 2 — no dependency on US2/US3
- **US2 (P2)**: Can start after Phase 2 — independently testable with manual balance seeding; demo flow prefers US1 first
- **US3 (P3)**: Can start after Phase 2 — independently testable with seeded ledger data; richest with US1+US2

### Within Each User Story

- Tests written first; ensure they fail before implementation
- Services before actions before commands
- Provider contract before actions before jobs

### Parallel Opportunities

- Phase 1: T002, T003, T004 in parallel
- Phase 2: T006–T008, T010–T018, T024–T026 in parallel after T005/T009 ordering
- US1: T028, T029, T031, T032 in parallel
- US2: T036, T037, T039, T040, T046, T047 in parallel
- Polish: T054–T058 in parallel

---

## Parallel Example: User Story 1

```bash
# Tests together (after T027 foundational):
T028 AllocationRoundingServiceTest.php
T029 RevenueRecognitionServiceTest.php

# Services together:
T031 AllocationRoundingService.php
T032 RevenueRecognitionService.php
```

---

## Parallel Example: User Story 2

```bash
# Provider layer together:
T039 PayoutProvider.php + PayoutProviderResult.php
T040 FakePayoutProvider.php

# Feature tests after implementation:
T036 PayoutCommandTest.php
T037 PayoutJobRetryTest.php
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: US1
4. **STOP and VALIDATE**: `revenue:allocate` + allocation tests green
5. Demo allocation before payouts if time-boxed

### Incremental Delivery

1. Setup + Foundational → schema and ledger ready
2. US1 → allocation and earning credits → **MVP**
3. US2 → payouts and timeout safety
4. US3 → Filament read-only visibility
5. Polish → seeder, docs, quickstart validation

### Docker Command Reference

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=DemoFinancialCoreSeeder
docker compose exec app php artisan revenue:allocate --month=2026-01
docker compose exec app php artisan payouts:run
docker compose exec app php artisan queue:work redis --tries=3
docker compose exec app php artisan payouts:reconcile
docker compose exec app php artisan test
```

---

## Notes

- Do not create `app/Domain/Refunds` in v1
- Do not use floats, Sail, partial unique indexes, or consumption record count as weight
- `pending_confirmation` payouts keep non-null `active_snapshot_key` until terminal resolution
- Provider calls must never run inside open DB transactions
- Filament must remain read-only only
