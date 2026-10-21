import { Info, type LucideIcon } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { colors, space } from '@/theme/tokens';

import { Text } from './Text';

type Tone = 'neutral' | 'warn' | 'danger';

type Props = {
  children: ReactNode;
  // O ícone de 18 do aviso (Info por padrão; relógio para "em análise", ingresso para convite).
  icon?: LucideIcon;
  // neutral: só informa (Grafite); warn: espera, como "em análise" (Atenção); danger: recusado (Tijolo).
  tone?: Tone;
};

// No neutro, o texto fica em Grafite; com estado, o texto fica em Tinta e a cor vai no ícone e no traço.
const TONE_COLORS: Record<Tone, { mark: string; line: string; text: 'muted' | 'ink' }> = {
  neutral: { mark: colors.muted, line: colors.line, text: 'muted' },
  warn: { mark: colors.warn, line: colors.warn, text: 'ink' },
  danger: { mark: colors.danger, line: colors.danger, text: 'ink' },
};

/**
 * Um aviso sem caixa ("Só quem lançou pode alterar", "Seu cadastro ainda está em análise"): o
 * ícone e o texto, com um traço ao lado.
 */
export function Notice({ children, icon: Icon = Info, tone = 'neutral' }: Props) {
  const palette = TONE_COLORS[tone];

  return (
    <View style={[styles.notice, { borderLeftColor: palette.line }]}>
      <Icon size={18} color={palette.mark} strokeWidth={1.6} />
      <Text variant="caption" tone={palette.text} style={styles.text}>
        {children}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  notice: {
    flexDirection: 'row',
    gap: space.sm,
    paddingVertical: space.sm,
    paddingLeft: space.md,
    borderLeftWidth: 2,
  },
  text: { flex: 1 },
});
