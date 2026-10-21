import type { ListKind } from '@/api/types';

/**
 * Transforma o que foi digitado em itens: "leite, café e sabão em pó" → ["Leite", "Café",
 * "Sabão em pó"]. Separa por vírgula, ponto e vírgula, quebra de linha e " e ".
 */
export function parseItems(text: string): string[] {
  return text
    .split(/[,;\n]|\s+e\s+/i)
    .map((item) => item.trim())
    .filter((item) => item !== '')
    .map((item) => item.charAt(0).toUpperCase() + item.slice(1));
}

/** O nome, a cor (um dos tons de pigmento do guia) e o texto de exemplo de cada tipo de lista. */
export const LIST_KINDS: Record<ListKind, { label: string; color: string; placeholder: string }> = {
  shopping: { label: 'Compras', color: '#4F7F4F', placeholder: 'Ex.: leite, café e pão' },
  wishlist: { label: 'Desejos', color: '#B5677E', placeholder: 'Ex.: fone de ouvido' },
  todo: { label: 'A fazer', color: '#7E5490', placeholder: 'Ex.: trocar a resistência do chuveiro' },
  custom: { label: 'Outra', color: '#7D786D', placeholder: 'Adicionar item' },
};
