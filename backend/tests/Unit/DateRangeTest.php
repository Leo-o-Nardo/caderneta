<?php

use App\Support\DateRange;
use Carbon\CarbonImmutable;

it('says the period the way people say it', function (string $from, string $to, string $label) {
    $today = CarbonImmutable::parse('2026-10-09 10:00', 'America/Sao_Paulo');

    expect(DateRange::parse($from, $to, 'America/Sao_Paulo')->label($today))->toBe($label);
})->with([
    'today' => ['2026-10-09', '2026-10-09', 'hoje'],
    'yesterday' => ['2026-10-08', '2026-10-08', 'ontem'],
    'another single day' => ['2026-10-05', '2026-10-05', 'em 05/10'],
    'a whole month' => ['2026-09-01', '2026-09-30', 'em setembro'],
    'a whole month of another year' => ['2025-11-01', '2025-11-30', 'em novembro de 2025'],
    'month to date' => ['2026-10-01', '2026-10-09', 'de 1º a 9 de outubro'],
    'days in the same month' => ['2026-10-05', '2026-10-09', 'de 5 a 9 de outubro'],
    'across two months' => ['2026-09-28', '2026-10-04', 'de 28/09 a 04/10'],
]);

it('counts the days including both ends', function () {
    expect(DateRange::parse('2026-10-01', '2026-10-09', 'America/Sao_Paulo')->days())->toBe(9);
});
