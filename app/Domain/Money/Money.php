<?php

namespace App\Domain\Money;

use InvalidArgumentException;

final class Money
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
    ) {}

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
