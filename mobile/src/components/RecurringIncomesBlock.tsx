import { router } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';

import { useRecurringIncomes } from '@/api/recurringIncomes';
import type { RecurringIncome } from '@/api/types';
import { Amount, SectionHeader, Tag, Text } from '@/components/ui';
import { shortDate } from '@/lib/dates';
import { colors, space } from '@/theme/tokens';

import { CategoryIcon } from './CategoryIcon';

/**
 * As receitas fixas (salário…), no fim da aba Contas. Elas nascem pelo "Adicionar" daqui ou pelo
 * "Repetir todo mês" ao lançar uma receita. Toque numa abre a tela de editar ou parar.
 */
export function RecurringIncomesBlock() {
  const query = useRecurringIncomes();
  const incomes = query.data ?? [];

  return (
    <View>
      <SectionHeader title="Receitas fixas" action={{ label: 'Adicionar', onPress: () => router.push('/recurring-incomes/new') }} />

      {query.isError ? (
        <Text tone="danger">{query.error.message}</Text>
      ) : incomes.length > 0 ? (
        incomes.map((income) => <IncomeRow key={income.id} income={income} />)
      ) : (
        <Text variant="caption" tone="muted">
          {query.isPending
            ? 'Carregando…'
            : 'Salário e outras receitas que caem todo mês: toque em “Adicionar” (ou ligue “Repetir todo mês” ao lançar a receita) e o app lança sozinho, todo mês, no dia.'}
        </Text>
      )}
    </View>
  );
}

/** "Salário · Todo dia 5 · próximo em 05/11", com o valor em Musgo e "+". */
function IncomeRow({ income }: { income: RecurringIncome }) {
  return (
    <Pressable
      onPress={() => router.push({ pathname: '/recurring-incomes/[id]', params: { id: String(income.id) } })}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole="button"
    >
      <CategoryIcon icon={income.category.icon} color={income.category.color} />
      <View style={styles.texts}>
        <View style={styles.line}>
          <Text variant="strong" numberOfLines={1} style={styles.shrink}>
            {income.description}
          </Text>
          {income.visibility === 'private' ? <Tag label="só você" /> : null}
        </View>
        <Text variant="caption" tone="muted" numberOfLines={1}>
          Todo dia {income.day_of_month} · próximo em {shortDate(income.next_on)}
        </Text>
      </View>
      <Amount cents={income.amount_cents} kind="income" />
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
  shrink: { flexShrink: 1 },
});
