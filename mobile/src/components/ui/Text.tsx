import { Text as NativeText, StyleSheet, type TextProps } from 'react-native';

import { colors, font, type ColorName } from '@/theme/tokens';

/** A hierarquia do guia (parte 03). Tamanhos em dp. */
const variants = StyleSheet.create({
  // O número que a pessoa veio ver (saldo do mês). No máximo um por tela.
  display: { fontFamily: font.serif, fontSize: 40, lineHeight: 44, letterSpacing: -0.8, fontVariant: ['lining-nums', 'tabular-nums'] },
  // Título de tela.
  title: { fontFamily: font.serif, fontSize: 28, lineHeight: 34, letterSpacing: -0.3 },
  // Título de seção, sempre com o traço de Tinta embaixo (SectionHeader).
  subtitle: { fontFamily: font.serifMedium, fontSize: 20, lineHeight: 26 },
  // Totais que não são o principal ("Despesas de outubro", o 85% do aviso).
  figure: { fontFamily: font.serifMedium, fontSize: 22, lineHeight: 28, letterSpacing: -0.2, fontVariant: ['lining-nums', 'tabular-nums'] },
  // Nome do lançamento, da conta, da categoria.
  strong: { fontFamily: font.medium, fontSize: 16, lineHeight: 22 },
  // Frases do assistente e textos de ajuda.
  body: { fontFamily: font.regular, fontSize: 16, lineHeight: 24 },
  // Rótulo de campo, tag, botão compacto.
  label: { fontFamily: font.medium, fontSize: 14, lineHeight: 20 },
  // Detalhes embaixo do nome ("Mercado · Pix").
  caption: { fontFamily: font.regular, fontSize: 13, lineHeight: 18 },
  // Só para metadados em caixa alta ("SALDO DO MÊS"), nunca para título de seção.
  overline: { fontFamily: font.semibold, fontSize: 11, lineHeight: 16, letterSpacing: 0.9, textTransform: 'uppercase' },
  // Toda coluna de R$: algarismos de mesma largura para alinhar (ver Amount).
  amount: { fontFamily: font.medium, fontSize: 16, lineHeight: 22, fontVariant: ['tabular-nums'] },
});

export type TextVariant = keyof typeof variants;

type Props = TextProps & {
  variant?: TextVariant;
  tone?: ColorName;
};

/** Todo texto do app passa por aqui, para usar sempre as fontes e as cores do guia. */
export function Text({ variant = 'body', tone = 'ink', style, ...props }: Props) {
  return <NativeText style={[variants[variant], { color: colors[tone] }, style]} {...props} />;
}
