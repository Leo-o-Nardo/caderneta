import { router } from 'expo-router';
import { ChevronRight } from 'lucide-react-native';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { useBudgets } from '@/api/budgets';
import { useCategories } from '@/api/categories';
import type { BudgetUsage, Category } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { Header, ProgressBar, Screen, SectionHeader, Text } from '@/components/ui';
import { BUDGET_COLORS, budgetStatusLabel, budgetTotals } from '@/lib/budgets';
import { monthName, toMonth } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { colors, font, space } from '@/theme/tokens';

/**
 * Todas as categorias de despesa: as que têm limite, com a barra do mês e a frase em Grafite (como
 * no Resumo), e as que ainda não têm. Tocar em qualquer uma abre o limite dela.
 */
export default function BudgetsScreen() {
  const month = toMonth(new Date());
  const categories = useCategories('expense');
  const budgets = useBudgets(month);

  const pending = categories.isPending || budgets.isPending;
  const failure = categories.error ?? budgets.error;

  if (pending || failure) {
    return (
      <Screen>
        <Header title="Orçamentos" />
        {failure ? (
          <Text tone="danger" style={styles.status}>
            {failure.message}
          </Text>
        ) : (
          <ActivityIndicator color={colors.accent} style={styles.status} />
        )}
      </Screen>
    );
  }

  const byCategory = new Map(budgets.data?.map((budget) => [budget.category.id, budget] as const));
  const withLimit = (categories.data ?? []).filter((category) => byCategory.has(category.id));
  const withoutLimit = (categories.data ?? []).filter((category) => !byCategory.has(category.id));
  // Só os orçamentos de categorias ativas entram no total.
  const totals = budgetTotals(withLimit.flatMap((category) => byCategory.get(category.id) ?? []));

  const open = (category: Category) =>
    router.push({ pathname: '/budgets/[categoryId]', params: { categoryId: String(category.id) } });

  return (
    <Screen scroll>
      <Header title="Orçamentos" />

      {withLimit.length > 0 ? (
        <View style={styles.overview}>
          <Text variant="caption" tone="muted">
            Gasto em {monthName(month)}, somando as categorias com limite
          </Text>
          <Text variant="figure">
            {formatCents(totals.spentCents)}
            <Text variant="figure" tone="muted">
              {` de ${formatCents(totals.limitCents)}`}
            </Text>
          </Text>
          <ProgressBar percent={totals.percent} color={BUDGET_COLORS[totals.status]} />
        </View>
      ) : (
        <Text tone="muted" style={styles.intro}>
          Escolha as categorias que você quer controlar e defina quanto pode gastar em cada uma por mês. O Resumo e o
          Início mostram quanto ainda sobra.
        </Text>
      )}

      {withLimit.length > 0 ? (
        <>
          <SectionHeader title="Com limite" detail={String(withLimit.length)} />
          {withLimit.map((category) => {
            const budget = byCategory.get(category.id);

            return budget ? <BudgetRow key={category.id} category={category} budget={budget} onPress={() => open(category)} /> : null;
          })}
        </>
      ) : null}

      {withoutLimit.length > 0 ? (
        <>
          <SectionHeader title="Sem limite" detail={String(withoutLimit.length)} />
          {withoutLimit.map((category) => (
            <EmptyBudgetRow key={category.id} category={category} onPress={() => open(category)} />
          ))}
        </>
      ) : null}

      <Text variant="caption" tone="muted" style={styles.footer}>
        O limite vale para todos os meses. O gasto conta os lançamentos da casa e os seus privados.
      </Text>
    </Screen>
  );
}

type RowProps = {
  category: Category;
  budget: BudgetUsage;
  onPress: () => void;
};

/** Uma categoria com limite: o gasto e o limite, a barra na cor do estado e a frase pronta embaixo. */
function BudgetRow({ category, budget, onPress }: RowProps) {
  const status = budgetStatusLabel(budget);

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole="button"
      accessibilityLabel={`${category.name}: ${formatCents(budget.spent_cents)} de ${formatCents(budget.limit_cents)}. ${status}`}
    >
      <View style={styles.line}>
        <View style={[styles.name, styles.flex]}>
          <CategoryIcon icon={category.icon} color={category.color} size={20} />
          <Text variant="strong" numberOfLines={1} style={styles.shrink}>
            {category.name}
          </Text>
        </View>
        <Text variant="amount">
          {formatCents(budget.spent_cents)}
          <Text variant="amount" tone="muted" style={styles.regular}>
            {` de ${formatCents(budget.limit_cents)}`}
          </Text>
        </Text>
        <ChevronRight size={16} color={colors.faint} strokeWidth={1.6} />
      </View>
      <ProgressBar percent={budget.percent} color={BUDGET_COLORS[budget.status]} />
      <Text variant="caption" tone="muted">
        {status}
      </Text>
    </Pressable>
  );
}

/** Uma categoria sem limite: só o convite para definir. */
function EmptyBudgetRow({ category, onPress }: { category: Category; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.row, styles.line, pressed && styles.pressed]}
      accessibilityRole="button"
      accessibilityLabel={`${category.name}: definir limite`}
    >
      <View style={[styles.name, styles.flex]}>
        <CategoryIcon icon={category.icon} color={category.color} size={20} />
        <Text variant="strong" numberOfLines={1} style={styles.shrink}>
          {category.name}
        </Text>
      </View>
      <Text variant="label" tone="accent">
        Definir
      </Text>
      <ChevronRight size={16} color={colors.faint} strokeWidth={1.6} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  overview: { gap: space.xs, paddingTop: space.md },
  intro: { marginTop: space.sm },
  regular: { fontFamily: font.regular },
  row: { gap: space.sm, paddingVertical: space.md, borderBottomWidth: 1, borderBottomColor: colors.line },
  pressed: { backgroundColor: colors.pressed },
  line: { flexDirection: 'row', alignItems: 'center', gap: space.md },
  name: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  shrink: { flexShrink: 1 },
  flex: { flex: 1 },
  footer: { marginTop: space.lg },
});
