import { StyleSheet, View } from 'react-native';

import { colors, radius, space } from '@/theme/tokens';

import { Text } from './Text';

/** A etiqueta pequena em caixa alta ao lado de um nome ("só você", "da casa"), no lugar dos ícones. */
export function Tag({ label }: { label: string }) {
  return (
    <View style={styles.tag}>
      <Text variant="overline" tone="muted" style={styles.text}>
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  tag: {
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.xs,
    paddingHorizontal: space.xs,
  },
  text: { lineHeight: 14 },
});
