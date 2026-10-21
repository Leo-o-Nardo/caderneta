/**
 * Os tons de pigmento do guia (parte 02), na ordem dele, com o nome que o leitor de tela fala. A
 * cor pinta só o ícone (da categoria, do cartão, da lista), nunca o fundo. As categorias padrão
 * usam estes mesmos tons (DefaultCategories, no backend).
 */
const PIGMENTS: { hex: string; name: string }[] = [
  { hex: '#4F7F4F', name: 'Louro' },
  { hex: '#3A8577', name: 'Verde-água' },
  { hex: '#357A8F', name: 'Petróleo' },
  { hex: '#4A6A9A', name: 'Ardósia' },
  { hex: '#5A5A9A', name: 'Índigo' },
  { hex: '#7E5490', name: 'Ameixa' },
  { hex: '#9A4A72', name: 'Vinho' },
  { hex: '#B5677E', name: 'Rosa-argila' },
  { hex: '#B9553A', name: 'Telha' },
  { hex: '#C27A3A', name: 'Canela' },
  { hex: '#B8902F', name: 'Açafrão' },
  { hex: '#7F8A3A', name: 'Oliva' },
  { hex: '#8A6A50', name: 'Castanho' },
  { hex: '#7D786D', name: 'Pedra' },
];

/** As cores que uma categoria pode ter: os 14 tons, na ordem do guia. */
export const CATEGORY_COLORS = PIGMENTS.map((pigment) => pigment.hex);

/** O nome do tom ("#7E5490" → "Ameixa"), sem ligar para maiúsculas; null para uma cor fora da paleta. */
export function colorName(hex: string): string | null {
  return PIGMENTS.find((pigment) => pigment.hex === hex.toUpperCase())?.name ?? null;
}

/** O código de um tom pelo nome ("Açafrão" → "#B8902F"), para exemplos fixos como o da capa. Sem o nome, Pedra. */
export function pigment(name: string): string {
  return PIGMENTS.find((item) => item.name === name)?.hex ?? '#7D786D';
}

// Quantas categorias a grade do formulário mostra antes do "Todas" (duas linhas de 4, com ele).
export const GRID_LIMIT = 7;

/**
 * As categorias da grade do formulário (guia 1.3): as primeiras, na ordem de Configurações ›
 * Categorias. A escolhida sempre aparece: se ela está mais para o fim, entra no lugar da última.
 * Com poucas categorias (até uma a mais que o limite), mostra todas e não precisa do "Todas".
 */
export function gridCategories<T extends { id: number }>(
  categories: T[],
  selectedId: number | null,
  limit: number = GRID_LIMIT,
): { shown: T[]; hasMore: boolean } {
  if (categories.length <= limit + 1) {
    return { shown: categories, hasMore: false };
  }

  const first = categories.slice(0, limit);
  const selected = categories.find((category) => category.id === selectedId);

  if (!selected || first.includes(selected)) {
    return { shown: first, hasMore: true };
  }

  return { shown: [...first.slice(0, limit - 1), selected], hasMore: true };
}
