import { useMutation } from '@tanstack/react-query';
import { useLocalSearchParams } from 'expo-router';
import { Check, Trash2 } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { removeBudget, setBudget, useBudget } from '@/api/budgets';
import type { BudgetDetail } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { MoneyInput } from '@/components/MoneyInput';
import { Amount, Button, Chip, Field, Form, Header, InfoRow, Screen, Text } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { averageLabel, suggestedLimit } from '@/lib/budgets';
import { formatCents } from '@/lib/money';
import { closeAndRefresh } from '@/lib/navigation';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

export default function BudgetScreen() {
  const { categoryId } = useLocalSearchParams<{ categoryId: string }>();
  const budget = useBudget(Number(categoryId));

  return (
    <Screen>
      <Header title="Orçamento" leading="close" />

      {budget.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : budget.isError ? (
        <Text tone="danger" style={styles.status}>
          {budget.error.message}
        </Text>
      ) : (
        <BudgetForm detail={budget.data} />
      )}
    </Screen>
  );
}

/**
 * O limite por mês de uma categoria. Embaixo, o gasto deste mês e a média dos anteriores, com um
 * atalho para usar a média (arredondada) como limite.
 */
function BudgetForm({ detail }: { detail: BudgetDetail }) {
  const [limit, setLimit] = useState(detail.limit_cents ?? 0);
  const { category } = detail;
  const suggestion = detail.average_cents ? suggestedLimit(detail.average_cents) : null;

  // Fecha e atualiza Resumo, Início e a lista de orçamentos.
  const save = useMutation({ mutationFn: () => setBudget(category.id, limit), onSuccess: closeAndRefresh });
  const remove = useMutation({ mutationFn: () => removeBudget(category.id), onSuccess: closeAndRefresh });

  const failure = save.error ?? remove.error;
  const error = failure instanceof ApiError ? failure : null;

  async function confirmRemove() {
    const confirmed = await confirm({
      title: `Remover o limite de ${category.name}?`,
      message: `${category.name} continua nos lançamentos e no Resumo, mas sai dos orçamentos e do “Resta nos orçamentos” do Início.`,
      confirmLabel: 'Remover limite',
      destructive: true,
    });

    if (confirmed) {
      remove.mutate();
    }
  }

  return (
    <Form
      error={error?.messageOutside(['limit_cents'])}
      submit={
        <Button
          title={detail.limit_cents === null ? 'Definir limite' : 'Salvar'}
          icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={save.isPending}
          disabled={limit === 0 || limit === detail.limit_cents}
          onPress={() => save.mutate()}
        />
      }
    >
      <View style={styles.category}>
        <CategoryIcon icon={category.icon} color={category.color} />
        <Text variant="subtitle" style={styles.flex}>
          {category.name}
        </Text>
      </View>

      <Field label="Limite por mês" error={error?.field('limit_cents')}>
        <MoneyInput value={limit} onChange={setLimit} autoFocus={detail.limit_cents === null} />
      </Field>

      <View style={styles.facts}>
        <InfoRow label="Gasto este mês" value={<Amount cents={detail.spent_cents} />} />
        {detail.average_cents !== null ? (
          <InfoRow label={averageLabel(detail.average_months)} value={<Amount cents={detail.average_cents} />} />
        ) : null}
      </View>

      {suggestion !== null && suggestion !== limit ? (
        <View style={styles.suggestion}>
          <Chip label={`Usar ${formatCents(suggestion)}`} onPress={() => setLimit(suggestion)} />
          <Text variant="caption" tone="muted" style={styles.flex}>
            A média arredondada para cima.
          </Text>
        </View>
      ) : null}

      {detail.average_cents === null ? (
        <Text variant="caption" tone="muted">
          Quando fechar o primeiro mês com lançamentos, a média aparece aqui para ajudar a escolher.
        </Text>
      ) : null}

      {detail.limit_cents !== null ? (
        <Button
          title="Remover limite"
          variant="dangerLink"
          icon={<Trash2 size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={remove.isPending}
          onPress={confirmRemove}
        />
      ) : null}
    </Form>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  category: { flexDirection: 'row', alignItems: 'center', gap: space.sm, paddingTop: space.sm },
  flex: { flex: 1 },
  // A pauta em cima da primeira linha fecha a tabela de dados.
  facts: { borderTopWidth: 1, borderTopColor: colors.line },
  suggestion: { flexDirection: 'row', alignItems: 'center', gap: space.md },
});
