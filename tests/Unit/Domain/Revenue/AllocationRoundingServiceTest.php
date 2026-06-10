<?php

use App\Domain\Revenue\Services\AllocationRoundingService;

it('splits 100 minor units across 3 equal instructors as 34 33 33', function (): void {
    $service = new AllocationRoundingService;

    $result = $service->distribute(100, [
        1 => 1,
        2 => 1,
        3 => 1,
    ]);

    expect($result[1])->toBe(34);
    expect($result[2])->toBe(33);
    expect($result[3])->toBe(33);
    expect(array_sum($result))->toBe(100);
});

it('always sums allocations to the instructor pool exactly', function (): void {
    $service = new AllocationRoundingService;
    $pool = 18000;
    $weights = [10 => 3600, 20 => 1800, 30 => 600];

    $result = $service->distribute($pool, $weights);

    expect(array_sum($result))->toBe($pool);
    expect($result[10])->toBe(10800);
    expect($result[20])->toBe(5400);
    expect($result[30])->toBe(1800);
});

it('uses integer arithmetic only for rounding', function (): void {
    $service = new AllocationRoundingService;
    $weights = [5 => 7, 8 => 11];

    $result = $service->distribute(999, $weights);

    foreach ($result as $amount) {
        expect($amount)->toBeInt();
    }

    expect(array_sum($result))->toBe(999);
});
