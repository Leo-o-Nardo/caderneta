<?php

use App\Enums\Recurrence;
use Carbon\CarbonImmutable;

/** Próxima data da série (só a data, Y-m-d), ou null. */
function nextOf(Recurrence $recurrence, string $anchor, int $every, string $after): ?string
{
    return $recurrence
        ->nextAfter(CarbonImmutable::parse($anchor), $every, CarbonImmutable::parse($after))
        ?->toDateString();
}

it('does not repeat a one-time reminder', function () {
    expect(nextOf(Recurrence::None, '2026-10-20', 1, '2026-10-20'))->toBeNull();
});

it('adds days, weeks, months and years', function () {
    expect(nextOf(Recurrence::Daily, '2026-10-20', 1, '2026-10-20'))->toBe('2026-10-21')
        ->and(nextOf(Recurrence::Weekly, '2026-10-20', 2, '2026-10-20'))->toBe('2026-11-03')
        ->and(nextOf(Recurrence::Monthly, '2026-10-20', 6, '2026-10-20'))->toBe('2027-04-20')
        ->and(nextOf(Recurrence::Yearly, '2026-10-20', 1, '2026-10-20'))->toBe('2027-10-20');
});

it('keeps the day of the month of the original date', function () {
    expect(nextOf(Recurrence::Monthly, '2027-01-31', 1, '2027-01-31'))->toBe('2027-02-28')
        ->and(nextOf(Recurrence::Monthly, '2027-01-31', 1, '2027-02-28'))->toBe('2027-03-31');
});

it('handles leap years', function () {
    expect(nextOf(Recurrence::Yearly, '2028-02-29', 1, '2028-02-29'))->toBe('2029-02-28')
        ->and(nextOf(Recurrence::Yearly, '2028-02-29', 1, '2031-03-01'))->toBe('2032-02-29');
});

it('jumps to the first date after the given moment', function () {
    expect(nextOf(Recurrence::Monthly, '2026-01-10', 1, '2026-10-07'))->toBe('2026-10-10');
});
