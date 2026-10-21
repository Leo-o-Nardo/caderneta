<?php

use App\Support\Money;

it('writes cents as reais', function () {
    expect(Money::format(123456))->toBe('R$ 1.234,56')
        ->and(Money::format(5))->toBe('R$ 0,05')
        ->and(Money::format(0))->toBe('R$ 0,00')
        ->and(Money::format(-1128000))->toBe('-R$ 11.280,00');
});

it('splits a value that divides evenly', function () {
    expect(Money::split(250000, 10))->toBe(array_fill(0, 10, 25000));
});

it('gives the leftover cents to the first installments', function () {
    expect(Money::split(10000, 3))->toBe([3334, 3333, 3333])
        ->and(Money::split(10001, 3))->toBe([3334, 3334, 3333])
        ->and(Money::split(5, 3))->toBe([2, 2, 1]);
});

it('always adds up to the total', function (int $total, int $parts) {
    expect(array_sum(Money::split($total, $parts)))->toBe($total);
})->with([
    [99999, 7],
    [1, 2],
    [123456789, 48],
]);
