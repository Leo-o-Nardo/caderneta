import { router } from 'expo-router';
import { Check } from 'lucide-react-native';
import { Pressable, StyleSheet, View } from 'react-native';

import { isOpen } from '@/api/bills';
import type { BillOccurrence } from '@/api/types';
import { Amount, Button, Text } from '@/components/ui';
import { billDueLabel } from '@/lib/bills';
import { colors, space } from '@/theme/tokens';

import { CategoryIcon } from './CategoryIcon';

type Props = {
  occurrence: BillOccurrence;
};

/**
 * Uma conta no mês, direto no papel: o ícone da categoria, a situação ("vence amanhã", "atrasada
 * há 9 dias"), o valor e, se estiver em aberto, "✓ Paguei". Toque abre o detalhe.
 */
export function BillRow({ occurrence }: Props) {
  const due = billDueLabel(occurrence);
  const open = isOpen(occurrence);
  const { bill } = occurrence;
  const details = [due.text, bill.autopay ? 'débito automático' : null, open && occurrence.is_estimate ? 'valor estimado' : null]
    .filter(Boolean)
    .join(' · ');

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/bills/[id]', params: { id: String(bill.id) } })}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole="button"
    >
      <CategoryIcon icon={bill.category.icon} color={bill.category.color} />
      <View style={styles.texts}>
        <Text variant="strong" numberOfLines={1}>
          {bill.name}
        </Text>
        <Text variant="caption" tone={due.tone} numberOfLines={2}>
          {details}
        </Text>
      </View>
      <View style={styles.right}>
        {occurrence.amount_cents === null ? (
          <Text variant="caption" tone="muted">
            valor variável
          </Text>
        ) : (
          <Amount cents={occurrence.amount_cents} estimate={occurrence.is_estimate} tone={open ? undefined : 'faint'} />
        )}
        {open ? (
          <Button
            title="Paguei"
            variant="ghost"
            icon={<Check size={18} color={colors.accent} strokeWidth={1.6} />}
            accessibilityLabel={`Paguei ${bill.name}`}
            onPress={() => router.push({ pathname: '/bills/pay', params: { bill_id: String(bill.id), month: occurrence.month } })}
            style={styles.pay}
          />
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  pressed: { backgroundColor: colors.pressed },
  texts: { flex: 1, gap: space.xs },
  right: { alignItems: 'flex-end', gap: space.xs },
  pay: { alignSelf: 'flex-end' },
});
