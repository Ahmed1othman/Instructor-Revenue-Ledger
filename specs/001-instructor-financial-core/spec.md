# Feature Specification: Instructor Financial Core

**Feature Branch**: `001-instructor-financial-core`

**Created**: 2026-06-10

**Status**: Draft

**Input**: User description: "Build the financial core for Instructor Revenue Ledger — subscription revenue allocation, append-only instructor ledger, safe payouts with mock provider, automated financial tests, and read-only balance visibility."

## Clarifications

### Session 2026-06-10

- Settlement periods → Calendar months as the default settlement period.
- Revenue recognition → Linear day-based proration over the subscription access period; earned
  amount for a settlement period is based on overlap between subscription active dates and the
  settlement period date range.
- Engagement weight → Sum of `valid_watched_seconds` per subscription/instructor within the
  settlement period (not consumption record count).
- Lesson consumption fields → `subscription_id`, `student_id`, `course_id`, `instructor_id`,
  `valid_watched_seconds`, `consumed_at`; allocation groups by `subscription_id` and
  `instructor_id` inside the settlement period.
- No engagement → No instructor earnings for that subscription/period; instructor pool documented
  as unallocated/platform-retained without increasing instructor payable balances.
- Rounding → Largest Remainder Method with integer-only arithmetic; tie-break by `instructor_id`
  ascending; allocation sum must equal `instructor_pool_minor` exactly.
- Refunds → Optional future improvement; defer until core allocation, ledger, payouts, timeout
  handling, tests, read-only admin view, and documentation are complete.
- Payout provider timeout → Unknown state, not failure; `pending_confirmation` payouts resolved
  only via status check with the same idempotency key, never re-sent.
- Payout command duplication → Must not create duplicate active payouts for the same instructor
  and same outstanding balance snapshot; payout creation requires a clear idempotency approach.
- Read-only admin view → Read-only only; no create, edit, delete, or payout-trigger actions.
- Redis → Queues and cache only; never the financial source of truth.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Allocate Subscription Revenue to Instructors (Priority: P1)

The platform recognizes earned subscription revenue over time and allocates each instructor's share
based on student engagement with that instructor's content during a settlement period. When a
student's subscription payment is active and engagement records exist, the platform operator
runs revenue allocation for a period and each participating instructor's earned balance increases
by their fair, deterministic share after the platform cut.

**Why this priority**: Instructor earnings are the foundation of the financial core. Without
correct, deterministic allocation, balances and payouts cannot be trusted.

**Independent Test**: Can be fully tested by seeding a subscription payment, engagement records
with varying `valid_watched_seconds` for multiple instructors on one subscription, running
allocation for one calendar-month settlement period, and verifying allocation records, ledger
earning credits, and balance projections match expected integer minor-unit amounts with zero pool
loss.

**Acceptance Scenarios**:

1. **Given** a successful upfront subscription payment and valid engagement records for multiple
   instructors on that subscription during a settlement period, **When** revenue allocation runs
   for that period, **Then** each instructor receives a credit proportional to their summed
   `valid_watched_seconds` weight, the platform retains its configured share, and the sum of
   instructor allocations equals the instructor pool exactly.
2. **Given** 100 minor units in the instructor pool split equally across 3 instructors (equal
   engagement weights), **When** allocation rounding is applied, **Then** allocations are 34, 33,
   and 33 minor units with the extra unit assigned using deterministic tie-breaking by lowest
   instructor identifier first.
3. **Given** allocation has already run for a subscription/instructor/period combination,
   **When** allocation runs again with the same inputs, **Then** no duplicate allocation records
   or duplicate earning ledger entries are created.
4. **Given** a subscription has no valid engagement during a settlement period, **When** revenue
   allocation runs, **Then** no instructor earning is created for that subscription/period, the
   instructor pool for that case is documented as unallocated or platform-retained, and no
   instructor payable balance increases.

---

### User Story 2 - Pay Out Instructor Outstanding Balances Safely (Priority: P2)

Operations staff run a payout process to pay instructors who have outstanding earned balances.
The process must remain safe when run multiple times, when background jobs retry, and when the
external payout provider returns success, permanent failure, or an ambiguous timeout that may
have already succeeded.

**Why this priority**: Paying instructors incorrectly (double payment or lost payment) is the
highest operational risk after allocation errors.

**Independent Test**: Can be fully tested by creating instructors with outstanding balances,
running the payout process twice against the same balance snapshot, simulating provider outcomes
including timeout-then-confirm, and verifying at most one successful payout debit per payout with
correct balance updates.

**Acceptance Scenarios**:

1. **Given** instructors with outstanding balance greater than zero, **When** the payout process
   runs, **Then** a payout batch and individual payout records are created and each payout is
   queued for processing without creating duplicate active payouts for the same instructor and
   same outstanding balance snapshot from a prior run.
2. **Given** a payout job where the provider returns success, **When** the result is persisted,
   **Then** exactly one payout debit ledger entry is created and instructor paid/outstanding
   balances update correctly.
3. **Given** a payout job where the provider times out after possibly succeeding, **When** the
   job completes, **Then** the payout is marked pending confirmation, no payout debit is created
   yet, and no new payment request is sent on retry.
4. **Given** a payout in pending confirmation state, **When** a status check confirms success
   using the same idempotency key, **Then** exactly one payout debit ledger entry is created and
   balances update once.
5. **Given** a payout in pending confirmation state, **When** a status check confirms permanent
   failure, **Then** the payout is marked failed safely with no payout debit and no duplicate
   provider payment attempt.
6. **Given** a payout job is retried after partial processing, **When** the job runs again,
   **Then** it does not re-send payouts that already succeeded or are awaiting confirmation.

---

### User Story 3 - Review Instructor Financial Position (Priority: P3)

A platform operator or reviewer opens a read-only administrative view to audit instructor
financial position: how much each instructor has earned, been paid, and still outstanding, plus
their payout history. Optional ledger entry visibility supports deeper audit without allowing
changes through this screen.

**Why this priority**: Visibility proves the financial core works and supports interview
defense, but depends on correct allocation and payout behavior from P1 and P2.

**Independent Test**: Can be fully tested by seeding instructors with known ledger history and
verifying the view displays correct earned, paid, outstanding, currency, and payout history
without create, edit, delete, or payout-trigger actions.

**Acceptance Scenarios**:

1. **Given** instructors with allocation and payout history, **When** a reviewer opens the
   instructor financial view, **Then** each instructor shows name, total earned, total paid,
   outstanding balance, currency, and payout history.
2. **Given** the instructor financial view is open, **When** a reviewer inspects available actions,
   **Then** no create, edit, delete, payout-trigger, or other write actions are available.

---

### Edge Cases

- What happens when the instructor pool cannot divide evenly across instructors? Largest Remainder
  Method applies with deterministic `instructor_id` ascending tie-breaking; pool sum is preserved.
- What happens when engagement records exist but summed `valid_watched_seconds` is zero for all
  instructors? Treat as no valid engagement; no instructor earnings for that subscription/period.
- What happens when payout command runs twice against the same outstanding balance snapshot?
  Second run must not create duplicate active payouts or double-pay.
- What happens when provider timeout occurs after actual success? Status check confirms outcome
  using the same idempotency key; only one payout debit is ever recorded; payout is never
  re-sent while pending confirmation.
- What happens when allocation is re-run for the same period? Idempotency keys prevent duplicate
  allocations and ledger entries.
- What happens when an instructor has zero outstanding balance? They are excluded from new payout
  batch creation.
- What happens to unallocated revenue when there is no engagement? Instructor pool is documented
  as unallocated or platform-retained; instructor payable balances must not increase.

## Requirements *(mandatory)*

### Functional Requirements

**Domain & money**

- **FR-001**: System MUST store all monetary amounts as integer minor units (e.g., cents) with
  explicit currency on financial records.
- **FR-002**: System MUST NOT use floating-point arithmetic for any money or allocation calculation.
- **FR-003**: System MUST treat the primary relational database as the sole financial source of
  truth; Redis and other ephemeral stores MAY be used for queues and cache only and MUST NOT hold
  authoritative financial state.
- **FR-004**: System MUST store revenue share percentages as basis points (default instructor
  share 6000 bps / platform share 4000 bps unless configured otherwise per plan).

**Subscriptions & revenue recognition**

- **FR-005**: System MUST support subscription plans, student subscriptions, and successful
  upfront subscription payments.
- **FR-006**: System MUST recognize subscription revenue over the subscription access period using
  linear day-based proration, not entirely on payment day.
- **FR-007**: System MUST use calendar months as the default settlement period and calculate earned
  subscription revenue for each period based on the date-range overlap between the subscription
  active period and the settlement period before allocation.
- **FR-008**: System MUST treat lesson consumption records as input data (not live-tracked in this
  submission) with required attributes: `subscription_id`, `student_id`, `course_id`,
  `instructor_id`, `valid_watched_seconds`, and `consumed_at`.

**Revenue allocation**

- **FR-009**: For each settlement period, system MUST calculate the instructor pool after applying
  the platform revenue share to the earned subscription amount for that period.
- **FR-010**: System MUST group valid engagement by `subscription_id` and `instructor_id` within
  the settlement period before allocating; global instructor aggregation before
  subscription-level allocation is NOT permitted.
- **FR-011**: Engagement weight for an instructor on a subscription in a period MUST equal the sum
  of `valid_watched_seconds` from valid consumption records in that period (a 60-minute valid watch
  MUST weigh more than a 1-minute valid watch).
- **FR-012**: System MUST allocate the instructor pool by engagement weight using integer-only
  arithmetic and the Largest Remainder Method:
  - `numerator = instructor_pool_minor * instructor_weight`
  - `floor_amount = intdiv(numerator, total_weight)`
  - `remainder = numerator % total_weight`
  - distribute remaining minor units one-by-one to largest remainders; ties broken by lowest
    `instructor_id` ascending.
- **FR-013**: System MUST ensure the sum of all instructor allocations equals `instructor_pool_minor`
  exactly for every allocation run.
- **FR-014**: When no valid engagement exists for a subscription in a settlement period, system
  MUST NOT create instructor earnings; the instructor pool for that subscription/period MUST be
  documented as unallocated or platform-retained without increasing instructor payable balances.
- **FR-015**: System MUST create revenue allocation records and append-only instructor ledger
  earning credit entries, then update instructor balance projections.
- **FR-016**: System MUST use unique idempotency keys for allocations and ledger entries (e.g.,
  `allocation:{settlement_period_id}:{subscription_id}:{instructor_id}` and
  `ledger:earning:{settlement_period_id}:{subscription_id}:{instructor_id}`).

**Ledger & balances**

- **FR-017**: Instructor ledger MUST be append-only; historical entries MUST NOT be deleted or
  mutated.
- **FR-018**: Ledger MUST support entry types including earning credit, payout debit, and optional
  future types (earning reversal, payout reversal, manual adjustment) only if refunds or
  adjustments are implemented after core flows are complete.
- **FR-019**: Instructor balances MUST be projections derived from ledger entries and MUST be
  rebuildable from the ledger alone.
- **FR-020**: Balances MUST track total earned, total paid, outstanding, and currency per
  instructor.

**Payouts**

- **FR-021**: System MUST provide a command-driven payout process that finds instructors with
  outstanding balance > 0, creates a payout batch and individual payouts, and dispatches queued
  processing jobs.
- **FR-022**: Running the payout command more than once MUST NOT create duplicate active payouts
  for the same instructor and same outstanding balance snapshot, and MUST NOT double-pay
  instructors; payout creation MUST use a documented idempotency approach.
- **FR-023**: Payout jobs MUST check current payout status before acting; MUST NOT re-send payouts
  that succeeded or are pending confirmation.
- **FR-024**: External payout provider calls MUST occur outside open database transactions and
  without holding database locks; results MUST be persisted in a subsequent transaction.
- **FR-025**: Payout debit ledger entries MUST be created at most once per payout using idempotency
  keys (e.g., `ledger:payout_debit:{payout_id}`).
- **FR-026**: System MUST record payout batches, payouts, and payout attempts with status tracking
  and unique idempotency constraints at the database level.

**Mock payout provider**

- **FR-027**: System MUST integrate via a payout provider abstraction with a mock implementation
  that randomly returns success, permanent failure, or timeout-after-possible-success.
- **FR-028**: Provider timeout MUST be treated as unknown state, not failure.
- **FR-029**: On timeout, system MUST mark payout `pending_confirmation`, MUST NOT create payout
  debit yet, MUST NOT send a new payment while pending confirmation, and MUST resolve only via
  status check using the same idempotency key.
- **FR-030**: Confirmed success after timeout MUST create exactly one payout debit; confirmed
  failure MUST mark payout failed without debit.

**Visibility & verification**

- **FR-031**: System MUST provide a read-only administrative view showing instructor name, total
  earned, total paid, outstanding balance, currency, and payout history; optional read-only ledger
  entry visibility is permitted. The view MUST NOT expose create, edit, delete, or payout-trigger
  actions.
- **FR-032**: System MUST include automated tests proving business-critical financial behavior
  including: correct allocation shares by `valid_watched_seconds` weight; Largest Remainder
  rounding; 34/33/33 equal-split case; pool-sum equality; allocation idempotency; duplicate payout
  command safety for the same balance snapshot; retried job safety; timeout without duplicate
  payment; pending-confirmation status check path; single payout debit on delayed success; balance
  projection correctness; and that Redis/cache is not the financial source of truth.
- **FR-033**: System MUST document assumptions, trade-offs, idempotency strategy, provider timeout
  handling, architecture decisions, and AI usage for interview review.

**Schema**

- **FR-034**: System MUST persist the financial core entities including users, plans, subscriptions,
  payments, instructors, courses, lesson consumptions, settlement periods, revenue allocations,
  instructor ledger entries, instructor balances, payout batches, payouts, and payout attempts.
- **FR-035**: Financial tables MUST use integer minor-unit money columns, explicit currency,
  typed statuses where helpful, unique constraints on idempotency keys, and indexes supporting
  allocation and payout lookups.

### Key Entities

- **Plan**: Subscription offering with price in minor units, currency, and configured revenue
  share basis points.
- **Subscription**: Student enrollment in a plan with defined active start and end dates.
- **Payment**: Successful upfront subscription payment linked to a subscription.
- **Settlement Period**: Calendar-month time window for recognizing earned revenue and running
  allocation.
- **Instructor**: Content owner eligible to receive allocated revenue and payouts.
- **Course**: Instructor-owned content included in subscription access.
- **Lesson Consumption**: Input engagement record with `subscription_id`, `student_id`,
  `course_id`, `instructor_id`, `valid_watched_seconds`, and `consumed_at`; weight derives from
  summed `valid_watched_seconds` per subscription/instructor in the settlement period.
- **Revenue Allocation**: Deterministic record of instructor share for a subscription/period with
  idempotency key.
- **Instructor Ledger Entry**: Append-only financial event (earning credit, payout debit, etc.)
  with idempotency key.
- **Instructor Balance**: Projection of earned, paid, and outstanding amounts per instructor.
- **Payout Batch**: Group of payouts initiated in one command execution.
- **Payout**: Individual instructor payment request with lifecycle status.
- **Payout Attempt**: Record of a provider interaction or status check tied to idempotency key.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For any settlement period with engagement data, 100% of allocation runs produce
  instructor allocation totals that equal the instructor pool exactly with zero minor-unit loss.
- **SC-002**: Re-running allocation or payout processes for the same financial event produces zero
  duplicate earning credits and zero duplicate successful payout debits in 100% of tested scenarios.
- **SC-003**: In the canonical equal-split scenario (100 minor units, 3 equal weights), allocations
  are exactly 34, 33, and 33 minor units with deterministic tie-breaking in 100% of runs.
- **SC-004**: Provider timeout scenarios result in zero duplicate payments and exactly one payout
  debit when success is ultimately confirmed via status check, in 100% of tested
  timeout-then-confirm paths; pending-confirmation payouts are never re-sent.
- **SC-005**: Instructor balance projections (earned, paid, outstanding) match ledger-derived
  totals in 100% of tested reconciliation scenarios.
- **SC-006**: Reviewers can view instructor earned, paid, outstanding, currency, and payout
  history in a single read-only view with no create, edit, delete, or payout-trigger actions.
- **SC-007**: Project documentation enables a technical interviewer to explain revenue
  recognition, allocation rounding, ledger design, idempotency, and timeout handling without
  reading source code first.

## Out of Scope

- Full student-facing LMS, course catalog UI, video player, or heartbeat tracking.
- Real-time lesson consumption tracking (consumption is seeded/test input only).
- Full instructor dashboard beyond the required read-only financial view.
- Real payment gateway or real payout provider integration.
- Tax/VAT, multi-currency conversion, full chart of accounts, full event sourcing.
- Replacing or recreating the existing application scaffold or container environment; Laravel Sail.
- Payout management, balance adjustment, or any write actions in the read-only administrative view.
- Refunds and earning reversals in the initial delivery (deferred as future improvement after core
  flows, tests, read-only view, and documentation are complete).

## Assumptions

- The existing Laravel application and custom Docker environment are already provisioned; this
  feature extends them without greenfield project setup or Sail.
- Settlement periods are calendar months by default.
- Earned revenue for a settlement period uses linear day-based proration over the subscription
  access period, based on overlap between subscription active dates and the settlement period.
- Engagement weight equals summed `valid_watched_seconds` per subscription/instructor within the
  settlement period; consumption records include `subscription_id`, `student_id`, `course_id`,
  `instructor_id`, `valid_watched_seconds`, and `consumed_at`.
- Single currency per allocation/payout run; multi-currency is out of scope.
- Refunds and earning reversals are deferred until allocation, ledger, payouts, timeout handling,
  tests, read-only admin view, and documentation are complete.
- Platform-retained or unallocated revenue from periods with no instructor engagement is
  documented but never credited to instructor payable balances.
- Application commands run in the app container; front-end asset tooling runs in the node
  container; financial commands are not executed on the host directly.
- Redis is used for queues and cache only, never as financial source of truth.
- Mock payout provider behavior is intentionally non-deterministic to exercise timeout and retry
  safety; tests control or stub outcomes as needed.
- Default revenue split is 60% instructor / 40% platform (6000/4000 basis points) on plans unless
  overridden per plan configuration.
