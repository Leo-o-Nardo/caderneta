/** Uma aba do app: o tour troca de aba sozinho antes de mostrar o passo. */
export type TourRoute = '/' | '/chat' | '/summary' | '/agenda' | '/lists';

export type TourStep = {
  id: string;
  // A aba do passo; null no último, que não aponta para nada.
  route: TourRoute | null;
  // O elemento destacado (TourTarget com esse id); null no último.
  target: string | null;
  title: string;
  body: string;
  // Só para quem pode lançar: o "Só leitura" não vê.
  needsWrite?: boolean;
};

/** Os passos do tour (guia 1.7), na ordem em que aparecem. */
export const TOUR_STEPS: TourStep[] = [
  {
    id: 'mic',
    route: '/',
    target: 'home.mic',
    title: 'Fale o que gastou',
    body: 'Toque no microfone e fale como falaria com alguém: "gastei 30 no mercado". O assistente monta a ficha e espera você conferir.',
    needsWrite: true,
  },
  {
    id: 'month',
    route: '/',
    target: 'home.month',
    title: 'O mês até aqui',
    body: 'Quanto já saiu este mês e o que ainda resta nos orçamentos. Sem orçamento, aparece o que entrou.',
  },
  {
    id: 'today',
    route: '/',
    target: 'home.today',
    title: 'Hoje',
    body: 'O que foi lançado hoje. Toque num lançamento para ver ou corrigir, e em "Ver todos" para o mês inteiro.',
  },
  {
    id: 'chat',
    route: '/chat',
    target: 'chat.composer',
    title: 'O Chat',
    body: 'Escreva ou pergunte: "quanto gastei com mercado?". As fichas para conferir e os avisos de contas e lembretes chegam aqui.',
  },
  {
    id: 'summary',
    route: '/summary',
    target: 'summary.balance',
    title: 'O mês inteiro numa tela',
    body: 'O saldo é o que entrou menos o que saiu no mês. Embaixo, os gastos por categoria, quem gastou quanto, as faturas e os orçamentos.',
  },
  {
    id: 'agenda',
    route: '/agenda',
    target: 'agenda.sections',
    title: 'O que vence',
    body: 'Contas fixas, faturas dos cartões e lembretes, pelo que vence primeiro. O + cadastra um novo.',
  },
  {
    id: 'lists',
    route: '/lists',
    target: 'lists.chips',
    title: 'Listas',
    body: 'Compras, desejos e o que fazer, separados por lista. No mercado, é só tocar no item para marcar.',
  },
  {
    id: 'settings',
    route: '/',
    target: 'home.settings',
    title: 'Configurações',
    body: 'Categorias, cartões, a casa, os avisos, os primeiros passos e este tour ficam aqui.',
  },
  {
    id: 'end',
    route: null,
    target: null,
    title: 'Pronto.',
    body: 'Agora é com você. Para rever o tour ou os primeiros passos, é só abrir as Configurações.',
  },
];

/** Os passos que a pessoa vê: sem os de lançar para quem é "Só leitura". */
export function tourStepsFor(canWrite: boolean): TourStep[] {
  return TOUR_STEPS.filter((step) => canWrite || !step.needsWrite);
}

/** De onde o tour começa: o passo guardado, se ainda existir; senão, o primeiro. */
export function resumeIndex(saved: number | null, total: number): number {
  return saved !== null && saved >= 0 && saved < total - 1 ? saved : 0;
}

export type Rect = { x: number; y: number; width: number; height: number };

/** Se duas medidas são do mesmo lugar (menos de 1 ponto de diferença): a tela parou de se mexer. */
export function sameRect(a: Rect, b: Rect): boolean {
  return (
    Math.abs(a.x - b.x) < 1 && Math.abs(a.y - b.y) < 1 && Math.abs(a.width - b.width) < 1 && Math.abs(a.height - b.height) < 1
  );
}

/**
 * Onde vai a ficha: embaixo do recorte, se couber; senão, em cima. Devolve o topo da ficha e o
 * lado da seta. O recorte já vem com a folga em volta.
 */
export function tipPlacement(hole: Rect, tipHeight: number, screenHeight: number, gap = 16, bottomInset = 0) {
  const below = hole.y + hole.height + gap + tipHeight <= screenHeight - bottomInset - gap;

  if (below) {
    return { top: hole.y + hole.height + gap, side: 'below' as const };
  }

  return { top: Math.max(gap, hole.y - gap - tipHeight), side: 'above' as const };
}

/** Um retângulo de cantos redondos como caminho de SVG (o recorte do tour). */
export function roundedRectPath({ x, y, width, height }: Rect, radius: number): string {
  'worklet';
  const r = Math.max(0, Math.min(radius, width / 2, height / 2));

  return (
    `M${x + r} ${y}H${x + width - r}A${r} ${r} 0 0 1 ${x + width} ${y + r}` +
    `V${y + height - r}A${r} ${r} 0 0 1 ${x + width - r} ${y + height}` +
    `H${x + r}A${r} ${r} 0 0 1 ${x} ${y + height - r}` +
    `V${y + r}A${r} ${r} 0 0 1 ${x + r} ${y}Z`
  );
}
