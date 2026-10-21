import { fitFontSize } from '../fit';

describe('fitFontSize', () => {
  it('keeps the largest size when the line fits', () => {
    expect(fitFontSize(400, 9.45, 40)).toBe(40);
  });

  it('shrinks just enough for a narrow screen', () => {
    // Um celular de 360 de largura, com as margens de 24: sobram 312.
    const size = fitFontSize(312, 9.45, 40);

    expect(size).toBe(33);
    expect(size * 9.45).toBeLessThanOrEqual(312);
  });

  it('accounts for a bigger system font', () => {
    expect(fitFontSize(363, 9.45, 40, 1.3)).toBe(29);
  });
});
