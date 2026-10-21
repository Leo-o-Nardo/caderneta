import type { StyleProp, TextStyle } from 'react-native';

import { amountLabel, formatCents } from '@/lib/money';
import type { ColorName } from '@/theme/tokens';

import { Text } from './Text';

type Kind = 'expense' | 'income' | 'balance';

type Props = {
  cents: number;
  // expense (padrão): sem sinal, em Tinta; income: "+" em Musgo; balance: negativo em Tijolo.
  kind?: Kind;
  // Valor que ainda não é o de verdade (conta de luz pela média): "~R$ 189,00".
  estimate?: boolean;
  // amount: linhas de lista; figure: totais; display: o número principal da tela.
  variant?: 'amount' | 'figure' | 'display';
  // Para fugir da cor da regra (conta já paga, em Lápis).
  tone?: ColorName;
  style?: StyleProp<TextStyle>;
};

function toneFor(kind: Kind, cents: number): ColorName {
  if (kind === 'income') {
    return 'accent';
  }

  return kind === 'balance' && cents < 0 ? 'danger' : 'ink';
}

/**
 * Um valor em R$ pelas regras do guia (parte 09): sinal, cor e algarismos de mesma largura, para
 * as colunas alinharem. Todo valor do app passa por aqui.
 */
export function Amount({ cents, kind = 'expense', estimate = false, variant = 'amount', tone, style }: Props) {
  return (
    <Text
      variant={variant}
      tone={tone ?? toneFor(kind, cents)}
      numberOfLines={1}
      // O leitor de tela lê "cerca de" em vez do "~".
      accessibilityLabel={estimate ? `cerca de ${formatCents(cents)}` : undefined}
      style={style}
    >
      {amountLabel(cents, { income: kind === 'income', estimate })}
    </Text>
  );
}
