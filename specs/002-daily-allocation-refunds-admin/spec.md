# Feature Specification: Daily Allocation, Refunds, and Financial Admin Visibility

**Feature Branch**: `002-daily-allocation-refunds-admin`

**Created**: 2026-06-10

**Status**: Draft

**Input**: User description: "Add daily revenue allocation as the only allocation mode, standard refunds for unused future subscription days, simple refund entry point, admin subscription financial screen, financial dashboard widgets, tests, and documentation — built on the existing Instructor Revenue Ledger core without rebuilding the app or weakening payout safety."

**Depends on**: `specs/001-instructor-financial-core` (subscription payments, ledger, payouts, read-only instructor balances)

## Clarifications

### Session 2026-06-10 (specify)

- Allocation mode → **Daily only**. Monthly allocation is retired as an active mode for new processing. Only completed elapsed calendar days may be allocated.
- Payout frequency → **Monthly**, unchanged. Payout pays allocated outstanding balances only after the target payout period is fully allocated.
- Cancellation day → Counted as a **used** access day. Refund window starts the day after cancellation.
- Standard refunds → Unused future days only. No instructor earning reversals required because future days were never allocated.
- Exceptional refunds → Chargebacks, goodwill on used days, disputes, fraud — **documented future design only**; append-only `earning_reversal` / `clawback` entries; not implemented in this feature.
- Cash vs earned → Upfront payment is cash received immediately; earning happens day-by-day as access elapses.
- Earned vs paid → Allocation increases instructor outstanding; only confirmed payout provider success increases instructor paid and decreases outstanding.

### Session 2026-06-10 (clarify — fixed business decisions)

- Q: Allocation mode → A: **Daily allocation only** for this feature. Monthly allocation is not supported as an alternative mode. Legacy monthly demo command/behavior from feature 001 may remain for backward compatibility only; documented policy and all new feature work use daily allocation.
- Q: Payout frequency → A: **Monthly payout** remains. Allocation frequency and payout frequency are **separate** concerns.
- Q: Cancellation day → A: Cancellation/refund request day is **used**. Refund window starts the **next calendar day**.
- Q: Refund basis → A: Standard refunds use **unused future days only**, not watched content. **Elapsed access days are non-refundable** by default.
- Q: Before refund → A: System MUST **allocate all unallocated elapsed days through and including cancellation day** before creating a refund.
- Q: Instructor reversals → A: Standard unused-day refunds create **no instructor reversals** (future days were never allocated). Exceptional refunds/chargebacks are **future extensions — documentation only**.
- Q: Student refund entry point → A: **Filament admin action** on subscription view is acceptable; keep demo-friendly. **No full student dashboard**.
- Q: Subscription financial screen → A: **Filament**; **primarily read-only**; may include a **clearly named refund action** if implemented on that screen.
- Q: Dashboard → A: **Filament widgets** where practical; calculations must be **understandable** and sourced from **database records** (MySQL financial source of truth).
- Q: Money → A: **Integer minor units only**; **no floats** for money calculations.
- Q: Idempotency → A: Daily allocation and refund creation MUST be idempotent; duplicate command runs or refund requests MUST NOT duplicate allocations, ledger entries, balances, or refunds.
- Q: Payout ordering → A: Payout pays **allocated outstanding only**; MUST NOT create payouts from unallocated future revenue.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Allocate Earned Revenue Daily (Priority: P1)

The platform operator runs daily allocation after a subscription access day has fully completed.
For each eligible subscription day, the system recognizes that day's earned revenue, computes the
instructor pool after the platform share, allocates the pool across instructors by
`valid_watched_seconds` for that day, writes earning credits to the append-only instructor ledger,
and updates instructor outstanding balances. Running allocation for the same day again must not
duplicate allocations, ledger entries, or balance changes.

**Why this priority**: Daily allocation is the foundation for correct earning, refund eligibility,
and monthly payout cutoffs. Without it, refunds and payout period completeness cannot be enforced.

**Independent Test**: Seed a subscription with engagement on specific calendar days, run daily
allocation for one completed day, verify allocation amounts, ledger entries, and balances; run
the same day again and confirm zero duplicates.

**Acceptance Scenarios**:

1. **Given** a successful upfront subscription payment and engagement records on a completed
   calendar day, **When** daily allocation runs for that date, **Then** instructor allocations
   for that day sum to that day's instructor pool exactly using deterministic integer rounding,
   earning credits are written, and outstanding balances increase accordingly.
2. **Given** daily allocation has already succeeded for a subscription/instructor/date
   combination, **When** daily allocation runs again for the same date, **Then** no duplicate
   allocation records, earning ledger entries, or balance increments occur.
3. **Given** a subscription day has not yet ended, **When** daily allocation is requested for
   that date, **Then** the system does not allocate revenue for that future day.
4. **Given** a completed day with zero engagement across all instructors for a subscription,
   **When** daily allocation runs, **Then** no instructor earning credits are created for that
   subscription/day and the instructor pool for that day is not allocated to instructors.
5. **Given** a monthly, 3-month, or annual subscription, **When** daily allocation runs across
   multiple elapsed days, **Then** each day's earned portion is recognized and allocated
   independently and lifetime allocated instructor amounts never exceed the subscription's total
   instructor pool.

---

### User Story 2 - Refund Unused Future Subscription Days (Priority: P2)

Operations staff or an authorized admin can refund a student subscription for **unused future days
only** — refund basis is elapsed access calendar days, **not** watched content. Elapsed access days
are non-refundable by default. The cancellation day counts as used. Before computing the refund,
the system allocates all unallocated elapsed days through and including the cancellation day so
instructor engagement on that day is settled. The refund amount covers days from the day after
cancellation through subscription end. A refund record is created, subscription status reflects
cancellation/refund, and duplicate refunds for the same subscription/cancellation are prevented.
No instructor earning reversals are created.

**Why this priority**: Refunds are the primary new money-out flow for students and depend on daily
allocation being current through the cancellation day.

**Independent Test**: Create a mid-period subscription with partial daily allocation, request
refund on a specific cancellation date, verify pre-refund allocation through cancel day, refund
amount matches unused future days only, and a second refund attempt is blocked or is a no-op.

**Acceptance Scenarios**:

1. **Given** a subscription from Jan 1 to Jan 30 and cancellation requested on Jan 10,
   **When** a standard refund is processed, **Then** Jan 1–Jan 10 are treated as used days,
   refundable days are Jan 11–Jan 30, and the refund amount equals the unused future portion in
   integer minor units.
2. **Given** unallocated elapsed days exist through the cancellation day,
   **When** a refund is requested, **Then** the system allocates those days first, then
   calculates and records the refund.
3. **Given** a refund has already been recorded for a subscription/cancellation request,
   **When** the same refund is requested again, **Then** no duplicate refund record or duplicate
   refund amount is applied.
4. **Given** cancellation occurs on the subscription end date,
   **When** refund is requested, **Then** no future days remain and refund amount is zero (or
   request is rejected with a clear outcome).
5. **Given** instructors received earning credits for elapsed days including cancellation day,
   **When** a standard refund completes, **Then** instructor allocated earnings for those elapsed
   days are not reversed.

---

### User Story 3 - View Subscription Financial Lifecycle in Filament (Priority: P3)

An authorized admin can open a **Filament** subscription financial view that explains where money
is across the student payment lifecycle: paid, earned, unearned, refunded, remaining refundable,
platform share, instructor pool allocated, instructor paid, and instructor outstanding linked to
that subscription. The screen is **primarily read-only** and may expose a **clearly named refund
action** as the only write operation on this resource.

**Why this priority**: Makes the daily allocation and refund model auditable and interview-ready
without building a full student portal.

**Independent Test**: Seed a subscription with payment, run partial daily allocations, process a
refund, open the subscription financial view, and verify displayed amounts match ledger and
refund records.

**Acceptance Scenarios**:

1. **Given** a subscription with a successful payment and partial daily allocation,
   **When** an admin views the subscription financial screen, **Then** student, plan, status,
   dates, payment amount, earned, unearned, refunded, remaining refundable, platform earned,
   instructor allocated, instructor paid, and instructor outstanding are shown with consistent
   integer minor-unit values.
2. **Given** a cancelled/refunded subscription,
   **When** an admin views the screen, **Then** cancellation date and refunded amount are
   visible and remaining refundable is zero.
3. **Given** the Filament subscription view,
   **When** displayed, **Then** it is primarily read-only and does not expose payout triggers or
   other balance mutation actions beyond the clearly named standard refund action.

---

### User Story 4 - Monitor Business Financial Health via Filament Dashboard (Priority: P4)

An authorized admin sees a **Filament dashboard** with widgets summarizing platform-wide totals
and highlights: student payments, earned and unearned revenue, refunds, instructor
allocated/paid/outstanding, subscription counts, payout pipeline status, and top instructors by
earned and outstanding. Widget calculations MUST be understandable and derived from database
financial records (not cache or estimates).

**Why this priority**: Gives operators a single place to validate the financial model during demo
and review.

**Independent Test**: Seed known financial data, open dashboard, verify widget totals match
underlying payment, allocation, ledger, refund, and payout records.

**Acceptance Scenarios**:

1. **Given** seeded payments, allocations, refunds, and payouts,
   **When** an admin opens the financial dashboard, **Then** aggregate totals for payments,
   earned revenue, unearned liability, refunds, instructor allocated, paid, and outstanding match
   authoritative records within integer minor units.
2. **Given** payouts in pending, pending confirmation, or failed states,
   **When** the dashboard loads, **Then** counts or totals for those states are visible.
3. **Given** multiple instructors with earnings,
   **When** the dashboard loads, **Then** top instructors by earned and by outstanding are listed
   with correct ordering.

---

### User Story 5 - Initiate Refund from Filament (Priority: P2)

An authorized operator can initiate a standard refund without a full student portal — via a
**Filament admin action on the subscription view** (demo-friendly). An operational command may
also exist for testing, but the primary demo path is the Filament refund action. No student
dashboard is built.

**Why this priority**: Refunds must be demonstrable end-to-end, not only calculable internally.

**Independent Test**: Trigger refund through the entry point for a seeded subscription and
verify the same outcomes as User Story 2.

**Acceptance Scenarios**:

1. **Given** a valid active subscription and cancellation date on or before end date,
   **When** an authorized operator initiates refund through the entry point,
   **Then** allocation-through-cancel-day and refund processing run and a success outcome is
   returned.
2. **Given** an unauthorized or unauthenticated request,
   **When** refund is attempted, **Then** access is denied.

### Edge Cases

- Cancellation on the first subscription day: one used day, refund for all remaining days.
- Cancellation on the last subscription day: no refundable future days.
- Refund requested before any daily allocation has run: system allocates all elapsed days through
  cancellation day first, then refunds.
- Subscription already fully allocated through end date then cancelled retroactively: refund still
  uses unused future days only; no instructor reversals.
- Partial engagement on cancellation day: allocation for that day uses `valid_watched_seconds`
  before refund calculation.
- 3-month and annual plans: daily earning and refund proration follow the same day-based rules
  across the full access window.
- Monthly payout attempted before all days in the payout month are allocated: payout for that
  period is blocked or excludes unallocated amounts.
- Payout in pending, processing, failed, or pending confirmation: outstanding balances unchanged
  until provider success.
- Duplicate daily allocation job/command retry: idempotent no-op.
- Duplicate refund request: idempotent no-op or explicit rejection.
- Refund amount ignores watch-time on future days; only unused future **calendar access days**
  determine refund (elapsed days are non-refundable).
- Legacy monthly allocation command from feature 001: may still run for old demos but is not the
  business policy path for this feature.

## Requirements *(mandatory)*

### Functional Requirements

**Daily allocation**

- **FR-001**: System MUST support **daily allocation as the only active allocation mode** for new
  earning processing.
- **FR-002**: System MUST allocate revenue only for **completed elapsed** subscription calendar
  days; future unearned days MUST NOT be allocated.
- **FR-003**: Daily allocation MUST use **integer minor units only** and deterministic rounding
  (Largest Remainder Method) so instructor allocations for a day sum exactly to that day's
  instructor pool.
- **FR-004**: Daily allocation MUST be **idempotent** per subscription/instructor/day (or
  equivalent unique business key); reruns MUST NOT duplicate allocations, ledger entries, or
  balance projections.
- **FR-005**: Daily allocation MUST weight instructor shares by summed `valid_watched_seconds`
  for that subscription and day within the elapsed day window.
- **FR-006**: System MUST use **daily allocation as the only supported allocation mode** in this
  feature. Monthly allocation MUST NOT be offered as an alternative mode. Legacy monthly
  allocation from feature 001 MAY remain for backward compatibility only and MUST NOT be the
  documented or primary business path.

**Monthly payout (existing, preserved)**

- **FR-007**: Payout frequency MUST remain **monthly**, separate from allocation frequency, and
  MUST pay only **allocated** outstanding instructor balances — never unallocated future revenue.
- **FR-008**: Monthly payout MUST NOT pay amounts for a period until **all days in that payout
  period have been daily-allocated** (or equivalent completeness guard). No payout MAY be created
  from unallocated future revenue.
- **FR-009**: Instructor **paid** balances MUST increase and **outstanding** decrease only on
  **confirmed payout provider success**; pending, processing, failed, and pending_confirmation
  payouts MUST NOT change paid/outstanding.

**Standard refunds**

- **FR-010**: System MUST support standard refunds for **unused future subscription days only**;
  refund basis is calendar access days, **not** watched content. Elapsed access days are
  **non-refundable** by default.
- **FR-011**: Cancellation day MUST be counted as a **used** access day; refundable window MUST
  start the day after cancellation through subscription end.
- **FR-012**: Before refund calculation, system MUST **allocate all unallocated elapsed days
  through and including the cancellation day**.
- **FR-013**: Refund amount MUST be calculated in integer minor units from unused future days
  only using the same day-based earning/proration rules as allocation.
- **FR-014**: System MUST create a **refund record** and update subscription status to
  cancelled/refunded (or equivalent terminal refund state).
- **FR-015**: System MUST prevent duplicate refunds for the same subscription/cancellation
  request via idempotency key or unique constraint.
- **FR-016**: Standard refunds MUST NOT create instructor earning reversals.

**Refund entry point**

- **FR-017**: System MUST provide a **Filament admin refund action** on the subscription view as
  the primary demo-friendly entry point (operational command optional for tests). No full student
  dashboard.

**Admin subscription financial view**

- **FR-018**: Authorized admins MUST be able to view a **Filament** per-subscription financial
  summary (primarily read-only; optional clearly named refund action) showing:
  student, plan, status, start date, end date, cancellation date (if any), original payment
  amount, earned amount, unearned amount, refunded amount, remaining refundable amount, platform
  earned amount, instructor pool allocated amount, instructor paid amount, and instructor
  outstanding amount.
- **FR-019**: Displayed amounts MUST reconcile with payments, daily allocations, refunds, ledger
  entries, and payout records for that subscription.

**Financial dashboard**

- **FR-020**: Authorized admins MUST see a **Filament dashboard with widgets**, where data exists,
  with understandable totals computed from database financial records: total student
  payments; recognized/earned revenue; unearned revenue liability; platform earned revenue;
  instructor pool allocated; instructor paid; instructor outstanding; total refunds; remaining
  refundable liability; active subscriptions; cancelled/refunded subscriptions; pending payouts;
  pending confirmation payouts; failed payouts; top instructors by earned; top instructors by
  outstanding.

**Documentation**

- **FR-021**: Project documentation MUST explain daily-only allocation, monthly payout, cancellation
  day rule, refund calculation, why standard refunds need no instructor reversals, exceptional
  refund future design, admin subscription view fields, and dashboard metrics.

**Testing**

- **FR-022**: Automated tests MUST cover daily allocation for one day, daily idempotency,
  cancellation-day-used rule, refund amount from next day through end, pre-refund allocation
  through cancel day, duplicate refund prevention, subscription financial view values, and
  dashboard totals where practical.

**Money and idempotency**

- **FR-023**: All money amounts MUST remain **integer minor units**; floats MUST NOT be used in
  financial calculations.
- **FR-024**: Daily allocation and refund creation MUST be **idempotent**; duplicate command runs
  or duplicate refund requests MUST NOT duplicate allocations, ledger entries, balance projections,
  or refund records.

**Non-regression**

- **FR-025**: Existing payout safety behaviors (duplicate command prevention, job retry safety,
  timeout reconciliation, provider-outside-transaction, `active_snapshot_key`) MUST remain intact.
- **FR-026**: Existing read-only instructor balance visibility MUST remain read-only for payout
  triggers; subscription screen may add refund action only as specified.

### Key Entities

- **Allocation day / daily settlement slice**: A completed calendar day for which earned revenue
  may be recognized and allocated; unique per subscription/day for idempotency.
- **Daily revenue allocation**: Instructor pool split for one day linked to engagement weights;
  produces earning credits.
- **Refund**: Student-facing money return for unused future days; links to subscription,
  cancellation date, amount in minor units, idempotency key, and status.
- **Subscription financial snapshot**: Derived view of paid, earned, unearned, refunded,
  refundable, platform, and instructor amounts for one subscription.
- **Dashboard aggregates**: Platform-wide rollups of payments, earning, liability, refunds, and
  payout pipeline metrics.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Operators can allocate a single completed subscription day and see instructor
  outstanding increase correctly within one operational run, with a second run producing zero new
  allocations.
- **SC-002**: For the Jan 1–Jan 30 / cancel Jan 10 example, refund amount equals the unused
  future portion (Jan 11–Jan 30) in minor units with zero instructor earning reversals.
- **SC-003**: 100% of duplicate daily allocation and duplicate refund retry tests pass without
  double-counting money.
- **SC-004**: Admins can open a subscription financial view and verify all displayed amounts
  match authoritative records for a seeded demo scenario.
- **SC-005**: Dashboard aggregate totals match seeded platform totals for payments, earned,
  refunds, instructor allocated, paid, and outstanding.
- **SC-006**: Monthly payout does not reduce outstanding until provider success, and does not pay
  a month that is not fully daily-allocated.
- **SC-007**: Documentation enables an interviewer to explain cash vs earned, daily allocation,
  standard refund policy, and why exceptional cases need append-only reversals — without reading
  source code.

## Out of Scope

- Full LMS UI, student dashboard, course catalog, video player, heartbeat tracking
- Real payment gateway or payout provider refund integration
- Exceptional refunds: chargebacks, goodwill on used days, disputes, fraud, manual corrections
- `earning_reversal` and `clawback` ledger entry implementation
- Multi-currency FX conversion
- Tax / VAT
- Laravel Sail or Docker setup changes
- Rebuilding the Laravel application or removing existing payout safety logic
- Weakening idempotency or money correctness guarantees

## Assumptions

- Feature `001-instructor-financial-core` is implemented and tests pass: ledger, balances,
  payouts, mock provider, read-only instructor balance admin view.
- Plans support monthly, 3-month, and annual durations via existing `duration_days` or equivalent.
- Lesson consumption remains input data (seeders/factories/tests); no live video tracking.
- Admin UI uses **Filament**: subscription financial resource is primarily read-only with an
  optional clearly named refund action; financial dashboard uses Filament widgets; instructor
  balance resource stays read-only for payouts.
- Legacy `revenue:allocate --month` from feature 001 may remain for backward compatibility but is
  not the business policy for this feature; daily allocation is the sole active mode going forward.
- Refund records represent platform-to-student liability reduction; instructor earnings for
  elapsed allocated days remain with instructors under standard refund policy.
- Monthly payout command and queue architecture are retained; this feature adds daily allocation
  command and payout-period completeness guards.
- Integer minor units and Largest Remainder Method remain mandatory per constitution v1.0.1.
- Redis remains queues/cache only; MySQL remains financial source of truth.
