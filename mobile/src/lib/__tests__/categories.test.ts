import { CATEGORY_COLORS, colorName, gridCategories, pigment } from '../categories';

const categories = Array.from({ length: 13 }, (_, index) => ({ id: index + 1 }));
const ids = (list: { id: number }[]) => list.map((category) => category.id);

describe('gridCategories', () => {
  it('shows the first seven and "Todas" when there are more', () => {
    const grid = gridCategories(categories, 2);

    expect(ids(grid.shown)).toEqual([1, 2, 3, 4, 5, 6, 7]);
    expect(grid.hasMore).toBe(true);
  });

  it('keeps the chosen one in the grid, in place of the last', () => {
    expect(ids(gridCategories(categories, 12).shown)).toEqual([1, 2, 3, 4, 5, 6, 12]);
  });

  it('shows every category when they fit without "Todas"', () => {
    const few = categories.slice(0, 8);

    expect(gridCategories(few, null)).toEqual({ shown: few, hasMore: false });
  });
});

describe('colorName', () => {
  it('names every pigment, ignoring the case', () => {
    expect(CATEGORY_COLORS).toHaveLength(14);
    expect(CATEGORY_COLORS.every((color) => colorName(color) !== null)).toBe(true);
    expect(colorName('#7e5490')).toBe('Ameixa');
  });

  it('returns null for a color outside the palette', () => {
    expect(colorName('#3DD68C')).toBeNull();
  });
});

describe('pigment', () => {
  it('finds a pigment by its name, falling back to Pedra', () => {
    expect(pigment('Açafrão')).toBe('#B8902F');
    expect(pigment('Neon')).toBe('#7D786D');
  });
});
