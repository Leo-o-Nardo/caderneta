/**
 * As cores, espaços, cantos e fontes do guia "Caderneta" (docs/design-guide.html). Todo o app usa
 * só estes valores: mudou o guia, muda aqui primeiro.
 */
export const colors = {
  // Papel: fundo de todas as telas.
  canvas: '#F4F0E8',
  // Folha: campos, fichas do chat e folha de confirmação, o que fica "por cima" do papel.
  surface: '#FBF9F4',
  // Fundo da linha enquanto o dedo está nela.
  pressed: '#ECE6DA',
  // Pauta: divisórias e borda das fichas. Só decorativa, nunca o único contorno de algo tocável.
  line: '#DCD5C6',
  // Borda de campo: contorno de campos, tags e do interruptor desligado (3:1 sobre o papel).
  border: '#8F8778',
  // Tinta: texto principal e botão principal.
  ink: '#1E1F1C',
  // O botão de Tinta pressionado.
  inkPressed: '#000000',
  // Grafite: legendas, datas, ícones das abas.
  muted: '#5F5B52',
  // Lápis: texto de exemplo nos campos, a seta "›" e item desabilitado.
  faint: '#6F6A60',
  // Musgo (destaque 1): links, seleção, foco, receitas e "deu certo".
  accent: '#2C5440',
  // Texto em cima do Musgo.
  onAccent: '#F4F0E8',
  accentSoft: '#E1E9DF',
  // Mostarda (destaque 2): só como fundo de texto Tinta, como o número de avisos.
  attention: '#D9A53A',
  // Atenção: a versão legível da Mostarda, para texto e barras a partir de 80%.
  warn: '#7F5A0C',
  warnSoft: '#F5E8C8',
  // Tijolo: atrasada, estourou, erro, apagar.
  danger: '#A63A25',
  dangerSoft: '#F3DFD7',
  // O botão de apagar pressionado.
  dangerPressed: '#8A2E1C',
  // Texto em cima do Tijolo (botão de apagar).
  onDanger: '#FFFFFF',
} as const;

/** A escala do guia: nenhum espaço fora dela (nada de `space.sm + 2`). */
export const space = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  // Margem lateral de toda tela.
  xl: 24,
  // Entre uma seção e outra.
  xxl: 40,
  xxxl: 64,
} as const;

/** Cada canto tem um uso: barras 2, tags 4, botões, campos e fichas 6, balão 12, folha 16. */
export const radius = {
  xs: 2,
  sm: 4,
  md: 6,
  lg: 12,
  xl: 16,
  // Só o microfone, o interruptor, as iniciais da pessoa e os pontos.
  pill: 999,
} as const;

/** IBM Plex Sans para tudo; Fraunces para títulos e valores grandes. */
export const font = {
  regular: 'IBMPlexSans_400Regular',
  medium: 'IBMPlexSans_500Medium',
  semibold: 'IBMPlexSans_600SemiBold',
  serif: 'Fraunces_600SemiBold',
  serifMedium: 'Fraunces_500Medium',
} as const;

export type ColorName = keyof typeof colors;

/** Uma cor com transparência ("#1E1F1C" a 42%): o véu e a sombra da folha que sobe. */
export function withAlpha(hex: string, alpha: number): string {
  const value = hex.replace('#', '');
  const [red, green, blue] = [0, 2, 4].map((start) => Number.parseInt(value.slice(start, start + 2), 16));

  return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}
