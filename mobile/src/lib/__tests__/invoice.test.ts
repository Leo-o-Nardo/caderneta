import { invoiceMonthFor } from '../invoice';

// Os mesmos casos do InvoiceCalendarTest do backend: as duas regras precisam dar o mesmo resultado.
describe('invoiceMonthFor', () => {
  it('puts a purchase made before the closing day in the invoice of the same month', () => {
    expect(invoiceMonthFor(3, '2026-10-02')).toBe('2026-10');
  });

  it('moves a purchase made on or after the closing day to the next invoice', () => {
    expect(invoiceMonthFor(3, '2026-10-03')).toBe('2026-11');
    expect(invoiceMonthFor(3, '2026-10-07')).toBe('2026-11');
  });

  it('crosses the year', () => {
    expect(invoiceMonthFor(3, '2026-12-20')).toBe('2027-01');
    expect(invoiceMonthFor(25, '2026-12-01')).toBe('2026-12');
  });

  it('uses the last day of the month when the day does not exist', () => {
    expect(invoiceMonthFor(31, '2027-02-15')).toBe('2027-02');
    expect(invoiceMonthFor(30, '2028-02-29')).toBe('2028-03');
  });
});
