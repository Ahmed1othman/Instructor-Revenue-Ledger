# Artisan Commands Contract: Feature 002

**Date**: 2026-06-10

Extends [001 artisan-commands](../001-instructor-financial-core/contracts/artisan-commands.md).

## revenue:allocate (extended)

**Signature**:

```text
php artisan revenue:allocate {--date=} {--month=}
```

### --date=YYYY-MM-DD (NEW — official path)

Allocates **one completed calendar day**.

| Rule | Behavior |
|------|----------|
| Date format | `YYYY-MM-DD` |
| Future date | Reject with error (day not yet elapsed) |
| Today before end of day | Reject or require yesterday default (implementation: reject if date >= today in app timezone) |
| Idempotency | Rerun same date → no duplicate allocations/ledger/balances |
| Subscriptions | Active on that date (`starts_at <= date <= ends_at`) |
| Engagement | `valid_watched_seconds` where `DATE(consumed_at) = date` |
| Money | Integer minor units; Largest Remainder Method |

**Example**:

```bash
docker compose exec app php artisan revenue:allocate --date=2026-01-04
```

### --month=YYYY-MM (LEGACY — backward compatibility)

Unchanged from feature 001. Deprecated in help text and documentation. Existing tests continue to use this path.

**Example**:

```bash
docker compose exec app php artisan revenue:allocate --month=2026-01
```

### Mutual exclusion

Only one mode per invocation. If both flags provided, error.

If neither flag: default to **yesterday** for `--date` (daily official policy).

## refunds:process (optional test helper)

**Signature** (optional, for Pest / CLI demo):

```text
php artisan refunds:process {subscription} {--cancel-date=YYYY-MM-DD}
```

Wraps `CreateSubscriptionRefundAction`. Primary UI path is Filament **Refund Unused Days** action.

## payouts:run (unchanged)

No signature change. Pays instructors with `outstanding_minor > 0` only. Provider semantics unchanged.
