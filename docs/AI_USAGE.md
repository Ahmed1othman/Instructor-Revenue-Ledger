# AI Usage Disclosure

## Overview

AI tools (including Cursor and large language models) were used to assist with this hiring-challenge project. All AI-generated output was **reviewed, edited, and validated** by a human before being treated as final.

## How AI was used

| Area | AI contribution |
|------|-----------------|
| Specification | Drafting user stories, acceptance criteria, and edge cases from a natural-language brief |
| Planning | Proposing architecture, data model, and task breakdown aligned with Laravel conventions |
| Implementation | Generating scaffolding for migrations, domain services, Filament resources, and Pest tests |
| Documentation | Drafting README, architecture notes, and this disclosure |
| Test ideas | Suggesting idempotency, rounding, timeout, and retry scenarios |

## Human-reviewed decisions

The following **financial and architectural rules** were explicitly chosen and verified — not blindly accepted from AI output:

| Decision | Rationale |
|----------|-----------|
| **Integer minor units** | Avoid floating-point rounding errors in money |
| **Largest Remainder Method** | Exact pool sums with fair integer distribution |
| **Append-only ledger** | Audit trail; corrections via new entries |
| **Idempotency keys** | Safe command/job retries without double-counting |
| **Provider timeout = unknown** | Never assume failure or success without confirmation |
| **Provider calls outside DB transactions** | Prevent lock contention and partial external state |
| **`active_snapshot_key` for MySQL** | Unique nullable column pattern for duplicate active payout prevention (no partial indexes) |
| **Calendar-month settlement** | Simple, predictable billing periods |
| **Day-based proration** | Recognize revenue by overlap days in each month |
| **`valid_watched_seconds` weighting** | Engagement-based split within instructor pool |
| **Excluding full LMS UI** | Scope focused on financial core correctness |
| **Read-only Filament balances** | Audit visibility without payout triggers from admin UI |
| **Daily allocation as official mode** | Elapsed-day earning; monthly legacy for feature 001 tests only |
| **Cross-mode allocation guards** | `AllocationModeGuardService` prevents daily/monthly double allocation |
| **Standard refunds without reversals** | Unused future days only; pre-allocate through cancellation day |
| **Redis for queues only** | MySQL remains financial source of truth |

## Validation process

- **Automated tests** (Pest) cover daily allocation, cross-mode guards, refunds, subscription Filament screen, dashboard widgets, allocation rounding, ledger idempotency, payout duplicate prevention, job retries, and timeout reconciliation
- **Manual review** of migration schema, enum values, and command flows
- **End-to-end demo** via `DemoFinancialCoreSeeder` + daily `revenue:allocate --date` + Filament refund + `payouts:run` + dashboard review
- **Constitution** (`.specify/memory/constitution.md`) used as governing checklist for financial behavior

## What AI did not do unsupervised

- No production credentials or real customer data were used
- No automatic deployment or external API integration without review
- Financial formulas were cross-checked against tests (e.g. 10800/5400/1800 demo split)
- Out-of-scope items (exceptional refunds/chargebacks, real gateways, full LMS) were deliberately excluded after review
- Feature 002 (daily allocation, standard refunds, subscription view, dashboard) was implemented in a second Spec Kit cycle with phase gates and 54 passing tests

## Transparency for reviewers

If asked in an interview:

1. AI accelerated boilerplate and exploration; **domain rules and trade-offs were human decisions**
2. Correctness is defended by **tests and integer arithmetic**, not by model confidence
3. Known limitations are documented honestly in README and ARCHITECTURE.md

## Tools

- Cursor IDE with agent assistance
- Spec Kit workflow (`/speckit.specify`, `/speckit.plan`, `/speckit.tasks`, `/speckit.implement`)

No sensitive production data was used at any stage.
