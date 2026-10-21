import { StyleSheet, View, type ViewProps } from 'react-native';

import { colors, radius } from '@/theme/tokens';

/**
 * A ficha do guia: a única caixa do app, para o que o assistente propõe ou avisa no chat. Listas e
 * seções ficam direto no papel, com divisórias.
 */
export function Card({ style, ...props }: ViewProps) {
  return <View style={[styles.card, style]} {...props} />;
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    overflow: 'hidden',
  },
});
