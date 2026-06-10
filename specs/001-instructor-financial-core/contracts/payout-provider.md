# Contract: Payout Provider

**Date**: 2026-06-10 | **Plan**: [plan.md](../plan.md)

## Interface

```php
interface PayoutProvider
{
    public function sendPayout(
        string $idempotencyKey,
        int $amountMinor,
        string $currency,
        int $instructorId,
    ): PayoutProviderResult;

    public function checkStatus(string $idempotencyKey): PayoutProviderResult;
}
```

## PayoutProviderResult

| Field | Type | Description |
|-------|------|-------------|
| status | ProviderResultStatus | `success`, `permanent_failure`, `timeout_unknown` |
| providerReference | ?string | External reference when known |
| message | ?string | Diagnostic text |

## Implementations

### MockPayoutProvider (demo / non-test runtime)

- Randomly returns `success`, `permanent_failure`, or `timeout_unknown`.
- For `timeout_unknown`, internal state may record success for later `checkStatus` (simulates delayed confirmation).
- Bound in `AppServiceProvider` for local/demo when `PAYOUT_PROVIDER=mock`.

### FakePayoutProvider (tests only)

- Configurable responses per idempotency key.
- Deterministic; no randomness.
- Bound in Pest `TestCase` or test setup.

## Caller obligations (ProcessInstructorPayoutAction)

1. Load payout; return early if `succeeded`, `failed`, or `pending_confirmation`.
2. Set status `processing` in transaction; commit.
3. Call `sendPayout()` **outside** transaction.
4. In new transaction:
   - `success` → `MarkPayoutSucceededAction` + payout debit ledger (idempotent)
   - `permanent_failure` → `MarkPayoutFailedAction`
   - `timeout_unknown` → `MarkPayoutPendingConfirmationAction`; no debit

## Caller obligations (CheckPayoutStatusAction)

1. Load payout; require `pending_confirmation`.
2. Call `checkStatus()` outside transaction.
3. In new transaction:
   - `success` → succeed + debit once
   - `permanent_failure` → fail without debit
   - `timeout_unknown` → remain `pending_confirmation`

## Idempotency

- Send and check use the same key: `payout:{payout_id}`
- Ledger debit key: `ledger:payout_debit:{payout_id}`
- Duplicate send MUST NOT occur when status is `pending_confirmation`
