<?php

namespace Tests;

use App\Domain\Payouts\Contracts\PayoutProvider;
use App\Domain\Payouts\Providers\FakePayoutProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected FakePayoutProvider $fakePayoutProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePayoutProvider = new FakePayoutProvider;
        $this->app->instance(PayoutProvider::class, $this->fakePayoutProvider);
    }
}
