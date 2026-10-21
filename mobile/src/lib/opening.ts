import { BRAND } from '@/theme/brand';

export type Box = { left: number; top: number; width: number; height: number };

type Size = { width: number; height: number };

// A marca na capa da abertura: 150 de largura, um pouco acima do meio da tela (aprovado no protótipo).
const COVER_MARK_WIDTH = 150;
const COVER_MARK_CENTER_Y = 0.44;

/** O ícone da splash do sistema: o quadrado de splashSize no centro da tela, onde a abertura começa. */
export function splashTile(screen: Size): Box {
  const side = BRAND.splashSize;

  return { left: (screen.width - side) / 2, top: (screen.height - side) / 2, width: side, height: side };
}

/** Onde a marca (o "C" e o traço duplo) fica dentro de um ícone de lado `side`. */
export function markInTile(side: number): Box {
  const k = side / BRAND.grid;
  const { x, y, width, height } = BRAND.markBox;

  return { left: x * k, top: y * k, width: width * k, height: height * k };
}

/** A marca na capa (a tela inteira): no mesmo ponto da horizontal que no ícone, um pouco acima do meio. */
export function markOnCover(screen: Size): Box {
  const { x, width: boxWidth, height: boxHeight } = BRAND.markBox;
  const width = COVER_MARK_WIDTH;
  const height = (width * boxHeight) / boxWidth;
  const centerX = ((x + boxWidth / 2) / BRAND.grid) * screen.width;

  return { left: centerX - width / 2, top: screen.height * COVER_MARK_CENTER_Y - height / 2, width, height };
}
