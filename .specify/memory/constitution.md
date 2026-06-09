<!--
Sync Impact Report
==================
Version change: 1.0.0 → 1.0.1 (PATCH)
Modified principles:
  - IV. Deterministic Revenue Allocation → expanded with Largest Remainder Method algorithm
Added sections: None
Removed sections: None
Templates requiring updates:
  - .specify/templates/plan-template.md ✅ updated
  - .specify/templates/tasks-template.md ✅ updated
  - .specify/templates/spec-template.md ✅ no changes required
  - .specify/templates/checklist-template.md ✅ no changes required
  - README.md ⚠ pending (still default Laravel README; project docs deferred to feature quickstart)
Follow-up TODOs:
  - Create docs/AI_USAGE.md when AI-assisted work begins
-->

# Instructor Revenue Ledger Constitution

## Core Principles

### I. Money Correctness First (NON-NEGOTIABLE)

Money correctness is more important than feature count. All monetary amounts MUST be stored as
integer minor units (cents or piasters). Floats MUST NEVER be used for money calculations.
MySQL is the financial source of truth. Redis MAY be used for queues and cache only — never as
the source of financial truth. Financial writes MUST use database transactions. Pessimistic
locking MUST be used only where needed, especially when updating instructor balance projections.

**Rationale**: Incorrect money handling is the highest-risk failure mode in a financial core.
Integer minor units and a single durable source of truth prevent rounding drift and silent data
loss.

### II. Append-Only Instructor Ledger (NON-NEGOTIABLE)

The instructor ledger MUST be append-only. Ledger entries are the financial source of truth for
instructor earnings, reversals, and payout debits. Instructor balances are projections derived
from ledger entries and MUST be rebuildable. Refunds, if implemented, MUST be represented through
reversal ledger entries — never by deleting or mutating historical entries.

**Rationale**: Immutable financial history enables auditability, safe replays, and defensible
balance reconstruction without rewriting the past.

### III. Idempotency & Safe Payouts (NON-NEGOTIABLE)

Every financial operation MUST be idempotent. Unique idempotency keys MUST be used for revenue
allocations, ledger entries, refunds, and payouts. Provider timeout means unknown state, not
failure. External payout provider calls MUST NEVER happen inside an open database transaction or
while holding database locks. Payout jobs MUST be safe when retried. Running the payout command
more than once MUST NEVER double-pay instructors. Delayed provider confirmation MUST NEVER create
duplicate payment records or duplicate payout ledger debits.

**Rationale**: Retries, timeouts, and duplicate command runs are normal in distributed systems.
Idempotency and out-of-transaction provider calls prevent double payment and inconsistent state.

### IV. Deterministic Revenue Allocation (NON-NEGOTIABLE)

Revenue allocation MUST be deterministic and testable. The system MUST use the **Largest
Remainder Method** for allocation rounding. All allocation math MUST use integer arithmetic
only — floats MUST NOT be used.

For each instructor, compute:

- `numerator = instructor_pool_minor * instructor_weight`
- `floor_amount = intdiv(numerator, total_weight)`
- `remainder = numerator % total_weight`

After calculating floor allocations for all instructors, distribute the remaining minor units one
by one to instructors with the largest remainders. Tie-breaking MUST be deterministic by
`instructor_id` ascending.

The final sum of all instructor allocations MUST always equal `instructor_pool_minor` exactly.
Every major allocation decision MUST be supported by tests or documentation.

**Rationale**: Non-deterministic allocation produces unreproducible earnings, failed audits, and
unfair instructor payouts. The Largest Remainder Method with fixed tie-breaking guarantees exact
pool conservation and reproducible results across runs.

### V. Simplicity & Explicit Financial Code

Keep the implementation simple and focused. Prefer clear names over clever abstractions. Write
code that can be explained in an interview. Do not add packages unless they directly support the
challenge requirements. Avoid hidden financial side effects in model observers. Financial actions
MUST be explicit, readable, and easy to test.

**Rationale**: This is a hiring-challenge financial core, not a platform rewrite. Clarity and
testability demonstrate engineering judgment better than abstraction volume.

## Technology Stack & Development Environment

This project is a Laravel 11 hiring-challenge application with a custom Docker environment — not
Laravel Sail.

**Stack**: PHP 8.3 FPM, Nginx, MySQL 8, Redis, Node 20, Livewire v3, Filament v3, Pest.

**Container rules**:

- PHP, Composer, and Artisan commands MUST run inside the `app` Docker container.
- Node and npm commands MUST run inside the `node` Docker container.
- Source code is edited on the host (Cursor); containers access the same files via Docker volumes.
- Do NOT assume Laravel Sail. Do NOT generate Sail-specific commands or configuration.

**Rationale**: Consistent container boundaries prevent environment drift and keep financial
commands reproducible across developers and CI.

## Architecture & Implementation Standards

- Prefer explicit Action classes for financial use cases.
- Prefer Services for reusable calculation logic.
- Use a `PayoutProvider` interface with a `MockPayoutProvider` implementation.
- Use typed enums for financial statuses and ledger entry types.
- Use a Money value object or strict helper for minor-unit money operations.
- Avoid Repository Pattern unless there is a real need.
- Avoid Strategy Pattern unless multiple allocation algorithms are actually implemented.
- Avoid full Event Sourcing.
- Avoid full Chart of Accounts.

**Rationale**: The architecture should make financial flows obvious in code review and tests, not
buried behind generic patterns.

## Scope Boundaries

Build the core financial system only. This is NOT a full LMS.

**In scope**:

- Subscription-based revenue allocation from student engagement input data.
- Append-only instructor ledger, balance projections, and payout processing.
- One simple read-only Filament screen showing instructor balances and payout history.
- Optional ledger visibility in Filament when it improves auditability.

**Out of scope**:

- Full student-facing LMS, course catalog UI, video player, heartbeat tracking.
- Full instructor dashboard.
- Live lesson consumption tracking — consumption records are input data from seeders, factories,
  and tests.

**Rationale**: Scope discipline keeps effort on money correctness, idempotency, and payout safety
— the evaluation criteria for this challenge.

## Testing & Documentation Standards

**Testing (Pest)**:

- Tests MUST focus on business-critical financial behavior.
- Required proofs:
  - Revenue allocation calculations are correct.
  - Rounding uses the Largest Remainder Method with integer arithmetic only.
  - 100 minor units split equally across 3 instructors results in 34, 33, 33 using deterministic
    tie-breaking by `instructor_id` ascending.
  - The sum of allocations always equals the instructor pool.
  - No float-based allocation is used.
  - Payout command can run twice without double-paying.
  - Retried payout jobs do not double-pay.
  - Unreliable provider timeout does not create duplicate payments.
  - Delayed status check confirms payout safely.
  - Ledger entries are idempotent.
  - Balances update correctly from ledger entries.

**Documentation**:

- Document assumptions, trade-offs, and intentional out-of-scope items clearly.
- Document Docker setup and commands.
- Document provider timeout handling and idempotency keys.
- Document AI usage in `docs/AI_USAGE.md`.

**Rationale**: Financial systems are validated by behavior under retry and edge cases, not by UI
breadth. Documentation makes design intent reviewable in an interview setting.

## Governance

This constitution supersedes ad-hoc implementation choices for the Instructor Revenue Ledger
project. All feature specs, plans, tasks, and pull requests MUST verify compliance with the
principles above before merge.

**Amendment procedure**:

1. Propose the change with rationale and version bump type (MAJOR / MINOR / PATCH).
2. Update `.specify/memory/constitution.md` and propagate impacts to dependent templates.
3. Record the sync impact report in the constitution HTML comment.
4. Re-run constitution checks on any in-flight feature specs or plans.

**Versioning policy**:

- MAJOR: Backward-incompatible principle removals or redefinitions.
- MINOR: New principles or materially expanded guidance.
- PATCH: Clarifications, wording, or non-semantic refinements.

**Compliance review**: Plans MUST include a Constitution Check gate before research and after
design. Violations MUST be documented in Complexity Tracking with justification, or resolved
before implementation proceeds.

Runtime development guidance: read the current feature `plan.md` and this constitution via
`.cursor/rules/specify-rules.mdc`.

**Version**: 1.0.1 | **Ratified**: 2026-06-10 | **Last Amended**: 2026-06-10
