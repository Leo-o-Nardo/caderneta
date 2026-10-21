<?php

use App\Support\InvoiceCalendar;
use Carbon\CarbonImmutable;

/** [mês da fatura, fecha em, vence em] para uma compra feita em $date. */
function invoiceOf(int $closingDay, int $dueDay, string $date): array
{
    return array_values(InvoiceCalendar::for($closingDay, $dueDay, CarbonImmutable::parse($date))->toArray());
}

it('puts a purchase made before the closing day in the invoice of the same month', function () {
    expect(invoiceOf(3, 10, '2026-10-02'))->toBe(['2026-10', '2026-10-03', '2026-10-10']);
});

it('moves a purchase made on or after the closing day to the next invoice', function () {
    expect(invoiceOf(3, 10, '2026-10-03'))->toBe(['2026-11', '2026-11-03', '2026-11-10'])
        ->and(invoiceOf(3, 10, '2026-10-07'))->toBe(['2026-11', '2026-11-03', '2026-11-10']);
});

it('makes the invoice due in the next month when the due day comes before the closing day', function () {
    expect(invoiceOf(25, 5, '2026-10-10'))->toBe(['2026-10', '2026-10-25', '2026-11-05']);
});

it('crosses the year', function () {
    expect(invoiceOf(3, 10, '2026-12-20'))->toBe(['2027-01', '2027-01-03', '2027-01-10'])
        ->and(invoiceOf(25, 5, '2026-12-01'))->toBe(['2026-12', '2026-12-25', '2027-01-05']);
});

it('uses the last day of the month when the day does not exist', function () {
    expect(invoiceOf(31, 10, '2027-02-15'))->toBe(['2027-02', '2027-02-28', '2027-03-10'])
        ->and(invoiceOf(30, 10, '2028-02-29'))->toBe(['2028-03', '2028-03-30', '2028-04-10']);
});

it('ignores the time of the purchase', function () {
    expect(invoiceOf(3, 10, '2026-10-02 23:59:59'))->toBe(['2026-10', '2026-10-03', '2026-10-10']);
});

it('builds the invoice of a given month', function () {
    $invoice = InvoiceCalendar::forMonth(25, 5, CarbonImmutable::parse('2026-11-18'));

    expect($invoice->toArray())->toBe([
        'month' => '2026-11',
        'closes_on' => '2026-11-25',
        'due_on' => '2026-12-05',
    ]);
});
