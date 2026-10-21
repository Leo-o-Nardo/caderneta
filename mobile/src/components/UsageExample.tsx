import { Check } from 'lucide-react-native';
import { StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';

import { FichaHeader, FichaItem } from '@/components/cards/Ficha';
import { CategoryIcon } from '@/components/CategoryIcon';
import { Amount, Card, Text } from '@/components/ui';
import { pigment } from '@/lib/categories';
import { colors, radius, space } from '@/theme/tokens';

/**
 * Um exemplo de uso de verdade: a fala da pessoa, a ficha "Lançado" e a frase do orçamento, como no
 * Chat. Aparece na capa de entrada e na primeira tela das boas-vindas.
 */
export function UsageExample({ style }: { style?: StyleProp<ViewStyle> }) {
  return (
    <View
      style={[styles.example, style]}
      accessible
      accessibilityLabel="Exemplo: você fala “Gastei 42 no almoço, no débito”, e o app lança o almoço em Alimentação, R$ 42,00, e diz quanto resta no orçamento."
    >
      <View style={styles.bubble}>
        <Text>Gastei 42 no almoço, no débito</Text>
      </View>
      <Card>
        <FichaHeader icon={Check} title="Lançado" tone="accent" />
        <FichaItem
          icon={<CategoryIcon icon="utensils" color={pigment('Açafrão')} />}
          title="Almoço"
          detail="Alimentação · Débito · hoje"
          value={<Amount cents={4200} />}
        />
      </Card>
      <Text tone="muted">Restam R$ 115,70 em Alimentação este mês.</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  example: { gap: space.md },
  // O balão da pessoa, igual ao do Chat: Folha com a pauta em volta e o canto reto do lado dela.
  bubble: {
    alignSelf: 'flex-end',
    maxWidth: '86%',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.lg,
    borderBottomRightRadius: radius.xs,
    paddingHorizontal: space.md,
    paddingVertical: space.sm,
  },
});
