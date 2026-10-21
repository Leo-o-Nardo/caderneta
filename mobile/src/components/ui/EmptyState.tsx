import type { LucideIcon } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { colors, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  // O desenho (Receipt, CreditCard…): o componente o mostra com 32 px em Grafite, solto, sem quadrado.
  icon?: LucideIcon;
  title: string;
  description?: string;
  action?: ReactNode;
};

/** O "estado vazio" de uma tela ou lista: o ícone, uma frase que diz o que falta e, se houver, o primeiro passo. */
export function EmptyState({ icon: Icon, title, description, action }: Props) {
  return (
    <View style={styles.container}>
      {Icon ? <Icon size={32} color={colors.muted} strokeWidth={1.6} style={styles.icon} /> : null}
      <Text variant="subtitle">{title}</Text>
      {description ? <Text tone="muted">{description}</Text> : null}
      {action ? <View style={styles.action}>{action}</View> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    gap: space.sm,
    paddingVertical: space.xxl,
  },
  icon: { marginBottom: space.xs },
  action: { marginTop: space.sm, alignItems: 'flex-start' },
});
