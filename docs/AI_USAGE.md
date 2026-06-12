# AI Usage Disclosure

This document describes how AI tools were used while building the **Instructor Revenue Ledger** submission for the Career 180 Hiring Quest. AI acted as an assistant; architectural and financial decisions were made and validated by the author. Nothing below replaces reading the code and tests.

## How AI was used

AI (primarily Cursor with large language models) assisted with:

| Area | AI contribution |
|------|-----------------|
| **Requirements clarification** | Turning an ambiguous challenge brief into structured user stories, acceptance criteria, and edge cases |
| **Spec Kit workflow** | Drafting specs, plans, task lists, and phase-gated implementation prompts |
| **Architecture exploration** | Proposing Laravel domain layout, migration shapes, and Filament resource structure |
| **Implementation scaffolding** | Generating migrations, services, Artisan commands, Filament screens, and Pest test skeletons |
| **Edge-case review** | Challenging risky assumptions (daily vs monthly overlap, refund cancellation-day rule, payout timeout semantics) |
| **Documentation** | Drafting README, architecture notes, and this disclosure |
| **Test strategy** | Suggesting idempotency, rounding, timeout, retry, and Filament smoke scenarios |

All AI output was **reviewed, edited, and validated** against running tests before being treated as final.

## Main prompts and workflows

The project followed a **Spec Kit** phase-based workflow rather than one-shot code generation:

| Command | Purpose |
|---------|---------|
| `/speckit.specify` | Feature specification from natural language |
| `/speckit.clarify` | Encode fixed business decisions (daily official mode, cancellation day, no reversals, etc.) |
| `/speckit.plan` | Technical plan, data model, contracts, risk controls |
| `/speckit.tasks` | Dependency-ordered tasks with phase gates |
| `/speckit.implement` | Execute tasks per phase; run tests after each gate |

**Implementation approach**

- **Feature 001** (financial core): monthly allocation, ledger, payouts, instructor balance Filament screen
- **Feature 002** (extension): daily allocation, refunds, subscription screen, dashboard widgets
- **Test-gated phases** — full Pest suite run after foundation, daily allocation, refunds, Filament, and final validation
- **Explicit constraints** in every implement prompt: no floats, append-only ledger, no payout architecture rewrites, additive migrations only

**Review prompts used repeatedly**

- “Does this double-allocate if daily and monthly both run?” → led to `AllocationModeGuardService`
- “What happens on provider timeout?” → confirmed no `payout_debit` until reconcile
- “Does standard refund reverse instructor earnings?” → confirmed no; only unused future days
- “Compare implementation to challenge requirements” → gap analysis before submission docs

Individual prompts are not reproduced verbatim here; the workflow above reflects how the author maintained ownership.

## What AI generated vs what was manually designed

### AI helped draft

- Initial `spec.md`, `plan.md`, `tasks.md`, and checklists
- Implementation prompts and file-level scaffolding
- Documentation first drafts (README, ARCHITECTURE)
- Test case ideas (idempotency reruns, cross-mode guards, refund Jan 1–30 / cancel Jan 10 scenario)
- Filament resource and widget boilerplate

### Human decisions (not blindly accepted from AI)

| Decision | Why |
|----------|-----|
| **Integer minor units, no floats** | Eliminate rounding drift in money |
| **Append-only ledger** | Auditable history; corrections as new entries |
| **Largest Remainder Method** | Exact instructor pool sums with fair integer split |
| **Daily allocation as official mode** | Matches “revenue over subscription period” lifecycle |
| **Monthly allocation as legacy only** | Preserve feature 001 tests; block cross-mode overlap |
| **Separate allocation vs payout frequency** | Daily earn visibility; monthly payout batching |
| **Monthly payout via `payouts:run`** | Challenge-aligned batch payment model |
| **Cancellation day = used** | Clear boundary for unused-days refund |
| **Refund starts next calendar day** | Unused future days only |
| **Standard refunds without instructor reversal** | Future days were never allocated |
| **Exceptional refunds as future extension** | `earning_reversal` / `clawback` documented, not built |
| **Provider timeout = unknown** | Never assume failure; reconcile later |
| **Provider calls outside DB transactions** | Avoid lock contention |
| **`active_snapshot_key` on payouts** | MySQL-friendly duplicate active payout prevention |
| **Filament for admin only** | Scope focus; no full LMS UI |
| **Read-only screens except Refund Unused Days** | Controlled single refund entry point |
| **Redis queues/cache only** | MySQL remains financial source of truth |

When AI suggestions conflicted with these rules, the rules won and tests were updated to enforce them.

## Engineering decisions personally made

1. **Money in minor units** — All `*_minor` columns are integers; `Money::formatMinor()` is display-only.
2. **Ledger is append-only** — No updates or deletes on `instructor_ledger_entries`.
3. **Balances are projections** — `instructor_balances` updated from ledger; rebuildable from entries.
4. **Idempotency keys everywhere money can duplicate** — Payments, allocations, ledger, refunds, payouts.
5. **Daily/monthly cross-mode guard** — `AllocationModeGuardService` at both allocation entry points.
6. **Payout debit only on confirmed provider success** — `MarkPayoutSucceededAction` writes one debit.
7. **Timeout does not mean failure** — `pending_confirmation` until `payouts:reconcile` resolves.
8. **Pre-refund elapsed-day allocation** — `EnsureElapsedDaysAllocatedAction` calls daily allocation only.
9. **Refund idempotency** — `refund:{subscription_id}:{cancellation_date}` unique key.
10. **Filament financial visibility** — Subscription summary service queries DB; widgets use direct Eloquent sums (no Redis cache for money totals).

## What differentiates this solution

- **Beyond minimal scope** — Daily allocation lifecycle, standard refunds, subscription financial screen, and dashboard widgets on top of the original monthly core
- **Refund policy implemented and tested** — Cancellation day rule, pre-allocation, no instructor reversals for standard path
- **Payout safety preserved** — Original idempotency, retry, timeout, and reconcile behavior unchanged through feature 002
- **Cross-mode protection** — Hard guard against daily + monthly double allocation in the same month
- **Demonstrable correctness** — 54 Pest tests, 186 assertions, including Filament and widget coverage
- **Documented reasoning** — README, ARCHITECTURE, locked lifecycle rules, and this disclosure for reviewers

## Trade-offs and intentional limitations

| Trade-off | Rationale |
|-----------|-----------|
| No full LMS UI | Challenge focuses on financial correctness, not student UX |
| Mock payout provider | Demonstrates failure/timeout paths without external API |
| No real gateway refunds | Refund is a financial record only; no PSP integration |
| No tax / VAT | Out of challenge scope |
| No multi-currency FX | Single currency per subscription; balances per currency row |
| No chargeback implementation | Documented as future `earning_reversal` / `clawback` extension |
| Legacy monthly allocation retained | Feature 001 test compatibility; not mixed with daily demos |
| Dashboard direct DB queries | Clarity and simplicity for challenge scope over caching layers |
| Minimal RBAC | Demo login only; no role matrix |

## Validation

| Check | Result |
|-------|--------|
| Full Pest suite | **54 tests, 186 assertions — passing** |
| Money calculations | Unit tests for recognition, rounding, refund amounts |
| Idempotency | Allocation reruns, refund duplicates, ledger keys, payout snapshots |
| Refunds | Cancellation day, pre-allocation, no reversals |
| Payout safety | Duplicate prevention, job retry, timeout reconcile |
| Filament | Instructor balances, subscriptions, refund action, dashboard widgets |
| AI suggestions | Reviewed critically; daily/monthly overlap and refund policy patched where AI initially under-specified guards |

**Process:** AI accelerated drafting and exploration; **correctness is defended by tests and integer arithmetic**, not model confidence. Risky financial paths were explicitly challenged in prompts and verified with Pest before merge.

## Tools

- Cursor IDE with agent assistance
- Spec Kit commands (`/speckit.specify`, `/speckit.clarify`, `/speckit.plan`, `/speckit.tasks`, `/speckit.implement`)
- Docker Compose for local environment
- No production credentials or customer data at any stage

## Transparency for reviewers

If asked in an interview:

1. AI reduced boilerplate time; **domain rules and trade-offs were human decisions**.
2. The author can walk through allocation, refund, and payout flows from code and tests.
3. Known limitations are documented honestly — not hidden behind AI-generated optimism.
