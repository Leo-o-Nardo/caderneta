import { StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, space } from '@/theme/tokens';

import { Text } from './Text';
import { TextLink } from './TextLink';

type Props = {
  title: string;
  // Ao lado do título, em Grafite: o total ("R$ 87,50") ou a unidade ("% das despesas").
  detail?: string;
  action?: { label: string; onPress: () => void };
  // Para quem precisa de menos espaço em cima (o "Hoje" do Início).
  style?: StyleProp<ViewStyle>;
};

/** O título de uma seção ("Orçamentos") com o traço de Tinta embaixo e um link opcional ("Editar"). */
export function SectionHeader({ title, detail, action, style }: Props) {
  return (
    <View style={[styles.container, style]}>
      <Text variant="subtitle" accessibilityRole="header" style={styles.title}>
        {title}
        {detail ? (
          <Text variant="caption" tone="muted">
            {`  ·  ${detail}`}
          </Text>
        ) : null}
      </Text>
      {action ? <TextLink label={action.label} onPress={action.onPress} size="sm" /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
    gap: space.md,
    marginTop: space.xxl,
    marginBottom: space.sm,
    paddingBottom: space.sm,
    borderBottomWidth: 1,
    borderBottomColor: colors.ink,
  },
  title: { flex: 1 },
});
