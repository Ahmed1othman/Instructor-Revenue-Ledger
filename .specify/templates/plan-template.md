# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]

**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command. See `.specify/templates/plan-template.md` for the execution workflow.

## Summary

[Extract from feature spec: primary requirement + technical approach from research]

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP 8.3 (Laravel 11)

**Primary Dependencies**: Livewire v3, Filament v3, Redis (queues/cache), custom Docker (not Sail)

**Storage**: MySQL 8 (financial source of truth); Redis for queues/cache only

**Testing**: Pest (business-critical financial behavior required)

**Target Platform**: Docker (app, nginx, mysql, redis, node containers); host editing via volumes

**Project Type**: Laravel web application — focused financial core for subscription LMS revenue

**Performance Goals**: Correctness and idempotency over throughput; deterministic allocation

**Constraints**: Integer minor units only; append-only ledger; no provider calls inside DB
transactions; PHP/Artisan in `app` container; npm in `node` container

**Scale/Scope**: Core financial system only — not a full LMS (see constitution Scope Boundaries)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Verify compliance with `.specify/memory/constitution.md` before proceeding:

- [ ] **Money correctness**: Integer minor units only; MySQL as financial source of truth
- [ ] **Append-only ledger**: No mutation/deletion of historical ledger entries
- [ ] **Idempotency**: Unique keys for allocations, ledger entries, refunds, payouts
- [ ] **Safe payouts**: Provider calls outside DB transactions; retry-safe jobs/commands
- [ ] **Deterministic allocation**: Largest Remainder Method with integer `intdiv`/`%` math;
      tie-break by `instructor_id` ascending; pool totals match exactly
- [ ] **Architecture**: Explicit Actions/Services; PayoutProvider + MockPayoutProvider; no hidden observers
- [ ] **Scope**: No full LMS/UI beyond required Filament read-only balances/payout history
- [ ] **Testing**: Required Pest proofs for allocation, idempotency, double-pay prevention, timeouts
- [ ] **Docker**: No Sail commands; container execution rules respected

Re-check after Phase 1 design. Document any violations in Complexity Tracking below.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)
<!--
  ACTION REQUIRED: Replace the placeholder tree below with the concrete layout
  for this feature. Delete unused options and expand the chosen structure with
  real paths (e.g., apps/admin, packages/something). The delivered plan must
  not include Option labels.
-->

```text
# [REMOVE IF UNUSED] Option 1: Single project (DEFAULT)
src/
├── models/
├── services/
├── cli/
└── lib/

tests/
├── contract/
├── integration/
└── unit/

# [REMOVE IF UNUSED] Option 2: Web application (when "frontend" + "backend" detected)
backend/
├── src/
│   ├── models/
│   ├── services/
│   └── api/
└── tests/

frontend/
├── src/
│   ├── components/
│   ├── pages/
│   └── services/
└── tests/

# [REMOVE IF UNUSED] Option 3: Mobile + API (when "iOS/Android" detected)
api/
└── [same as backend above]

ios/ or android/
└── [platform-specific structure: feature modules, UI flows, platform tests]
```

**Structure Decision**: [Document the selected structure and reference the real
directories captured above]

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
