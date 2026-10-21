import { parseItems } from '../lists';

describe('parseItems', () => {
  it('splits several items typed at once', () => {
    expect(parseItems('leite, café e sabão em pó')).toEqual(['Leite', 'Café', 'Sabão em pó']);
    expect(parseItems('arroz; feijão\nbanana')).toEqual(['Arroz', 'Feijão', 'Banana']);
  });

  it('ignores empty pieces and extra spaces', () => {
    expect(parseItems('  pão ,, , manteiga  ')).toEqual(['Pão', 'Manteiga']);
    expect(parseItems('   ')).toEqual([]);
  });

  it('keeps words that only contain an "e" inside', () => {
    expect(parseItems('detergente')).toEqual(['Detergente']);
    expect(parseItems('queijo e presunto')).toEqual(['Queijo', 'Presunto']);
  });
});
