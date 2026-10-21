import { BRAND } from '@/theme/brand';

import { markInTile, markOnCover, splashTile } from '../opening';

const screen = { width: 390, height: 844 };

describe('splashTile', () => {
  it('puts the splash icon in the middle of the screen', () => {
    const tile = splashTile(screen);

    expect(tile.width).toBe(BRAND.splashSize);
    expect(tile.left + tile.width / 2).toBe(screen.width / 2);
    expect(tile.top + tile.height / 2).toBe(screen.height / 2);
  });
});

describe('markInTile', () => {
  it('scales the mark box from the 1024 grid to the icon size', () => {
    expect(markInTile(BRAND.grid)).toEqual({
      left: BRAND.markBox.x,
      top: BRAND.markBox.y,
      width: BRAND.markBox.width,
      height: BRAND.markBox.height,
    });
    expect(markInTile(BRAND.grid / 2).width).toBe(BRAND.markBox.width / 2);
  });
});

describe('markOnCover', () => {
  it('keeps the mark proportions and its horizontal place on the icon', () => {
    const mark = markOnCover(screen);
    const iconCenter = (BRAND.markBox.x + BRAND.markBox.width / 2) / BRAND.grid;

    expect(mark.width).toBe(150);
    expect(mark.height / mark.width).toBeCloseTo(BRAND.markBox.height / BRAND.markBox.width);
    expect((mark.left + mark.width / 2) / screen.width).toBeCloseTo(iconCenter);
  });

  it('sits a little above the middle of the screen', () => {
    const mark = markOnCover(screen);

    expect(mark.top + mark.height / 2).toBeCloseTo(screen.height * 0.44);
  });
});
