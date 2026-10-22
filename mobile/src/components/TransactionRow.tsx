import { router } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';

import { useCanWrite } from '@/api/auth';
import type { PaymentType, Transaction } from '@/api/types';
import { Amount, Tag, Text } from '@/components/ui';
import { amountLabel } from '@/lib/money';
import { colors, space } from '@/theme/tokens';

import { CategoryIcon } from './CategoryIcon';

export const PAYMENT_LABELS: Record<PaymentType, string> = {
  pix: 'Pix',
  debit: 'Débito',
  credit_card: 'Crédito',
  cash: 'Dinheiro',
};

/** "Mercado · Pix" ou "Compras · Nubank · 3/10". */
export function transactionDetails(transaction: Transaction): string {
  const parts = [transaction.category.name];

  if (transaction.card) {
    parts.push(transaction.card.name);
  } else if (transaction.payment_type) {
    parts.push(PAYMENT_LABELS[transaction.payment_type]);
  }

  if (transaction.installment) {
    parts.push(`${transaction.installment.number}/${transaction.installment.count}`);
  }

  return parts.join(' · ');
}

type Props = {
  transaction: Transaction;
};

/**
 * Uma linha de lançamento do guia, direto no papel: o ícone da categoria na cor dela (coluna de 24),
 * o nome com os detalhes embaixo e o valor à direita (receitas com "+" em Musgo). Toque abre a edição;
 * para quem é "Só leitura", a linha é só para ver.
 */
export function TransactionRow({ transaction }: Props) {
  const isIncome = transaction.kind === 'income';
  const isPrivate = transaction.visibility === 'private';
  const details = transactionDetails(transaction);
  const canWrite = useCanWrite();

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/transactions/[id]', params: { id: String(transaction.id) } })}
      disabled={!canWrite}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole={canWrite ? "button" : undefined}
      accessibilityLabel={[
        transaction.description,
        amountLabel(transaction.amount_cents, { income: isIncome }),
        details,
        isPrivate ? 'só você vê' : null,
      ]
        .filter(Boolean)
        .join(', ')}
    >
      <CategoryIcon icon={transaction.category.icon} color={transaction.category.color} />
      <View style={styles.texts}>
        <View style={styles.line}>
          <Text variant="strong" numberOfLines={1} style={styles.name}>
            {transaction.description}
          </Text>
          {isPrivate ? <Tag label="só você" /> : null}
        </View>
        <Text variant="caption" tone="muted" numberOfLines={1}>
          {details}
        </Text>
      </View>
      <Amount cents={transaction.amount_cents} kind={isIncome ? 'income' : 'expense'} />
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
  line: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  name: { flexShrink: 1 },
});
