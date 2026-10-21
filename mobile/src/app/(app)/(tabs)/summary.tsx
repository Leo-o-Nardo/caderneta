import { router } from 'expo-router';
import { CreditCard, Receipt } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { useCards } from '@/api/cards';
import { useSummary } from '@/api/summary';
import type { BillOccurrence, BudgetUsage, CategoryKind, Summary } from '@/api/types';
import { CategoryIcon, CategoryMark } from '@/components/CategoryIcon';
import { Ledger } from '@/components/Ledger';
import { MonthSwitcher } from '@/components/MonthSwitcher';
import { TourTarget } from '@/components/tour/TourTarget';
import { Amount, EmptyState, ProgressBar, Screen, SectionHeader, Text } from '@/components/ui';
import { billDueLabel } from '@/lib/bills';
import { BUDGET_COLORS, budgetStatusLabel, sortByAttention } from '@/lib/budgets';
import { monthName, shortDate, toMonth } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { balanceCaption } from '@/lib/summary';
import { colors, font, radius, space } from '@/theme/tokens';

/** O resumo do mês: o saldo em destaque e, embaixo, o que precisa de atenção primeiro. Puxar para baixo atualiza. */
export default function SummaryScreen() {
  const [month, setMonth] = useState(toMonth(new Date()));
  const query = useSummary(month);

  return (
    <Screen edges={['top']}>
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={query.isRefetching}
            onRefresh={() => query.refetch()}
            tintColor={colors.accent}
            colors={[colors.accent]}
            progressBackgroundColor={colors.surface}
          />
        }
      >
        <MonthSwitcher month={month} onChange={setMonth} />

        {query.isPending ? (
          <ActivityIndicator color={colors.accent} style={styles.status} />
        ) : query.isError ? (
          <Text tone="danger" style={styles.status}>
            {query.error.message}
          </Text>
        ) : (
          <SummaryContent summary={query.data} />
        )}
      </ScrollView>
    </Screen>
  );
}

function SummaryContent({ summary }: { summary: Summary }) {
  const empty = summary.income_cents === 0 && summary.expense_cents === 0;

  return (
    <>
      <TourTarget id="summary.balance">
        <View style={styles.hero}>
          <Text variant="overline" tone="muted">
            Saldo do mês
          </Text>
          <Amount cents={summary.balance_cents} kind="balance" variant="display" />
          <Text variant="caption" tone="muted">
            {balanceCaption(summary.income_cents, summary.expense_cents)}
          </Text>
        </View>

        <Ledger
          cells={[
            {
              label: 'Receitas',
              value: <Amount cents={summary.income_cents} kind="income" variant="figure" />,
              onPress: () => openTransactions(summary.month, 'income'),
            },
            {
              label: 'Despesas',
              value: <Amount cents={summary.expense_cents} variant="figure" />,
              onPress: () => openTransactions(summary.month, 'expense'),
            },
          ]}
        />
      </TourTarget>

      {empty ? (
        <EmptyState
          icon={Receipt}
          title={`Nada lançado em ${monthName(summary.month)}`}
          description="Os gastos e as receitas do mês aparecem aqui."
        />
      ) : null}

      <Budgets budgets={summary.budgets} />
      <UpcomingBills bills={summary.upcoming_bills} />
      <Invoices invoices={summary.invoices} />
      <Categories summary={summary} />
      <Members summary={summary} />
    </>
  );
}

/**
 * Os orçamentos do mês, do que precisa de atenção para o resto, com a frase pronta embaixo da
 * barra. A frase fica em Grafite, para ler fácil: o estado (perto do limite, estourado) é a cor da barra.
 */
function Budgets({ budgets }: { budgets: BudgetUsage[] }) {
  if (budgets.length === 0) {
    return (
      <>
        <SectionHeader title="Orçamentos" action={{ label: 'Definir', onPress: () => router.push('/budgets') }} />
        <Text variant="caption" tone="muted">
          Defina um limite por categoria (Mercado, Delivery…) para acompanhar aqui quanto ainda dá para gastar.
        </Text>
      </>
    );
  }

  return (
    <>
      <SectionHeader title="Orçamentos" action={{ label: 'Editar', onPress: () => router.push('/budgets') }} />
      {sortByAttention(budgets).map((budget) => (
        <View key={budget.category.id} style={styles.budget}>
          <View style={styles.line}>
            <View style={[styles.details, styles.shrink]}>
              <CategoryIcon icon={budget.category.icon} color={budget.category.color} size={20} />
              <Text variant="strong" numberOfLines={1} style={styles.shrink}>
                {budget.category.name}
              </Text>
            </View>
            <Text variant="amount">
              {formatCents(budget.spent_cents)}
              <Text variant="amount" tone="muted" style={styles.regular}>
                {` de ${formatCents(budget.limit_cents)}`}
              </Text>
            </Text>
          </View>
          <ProgressBar percent={budget.percent} color={BUDGET_COLORS[budget.status]} />
          <Text variant="caption" tone="muted">
            {budgetStatusLabel(budget)}
          </Text>
        </View>
      ))}
    </>
  );
}

/** As próximas três contas (as atrasadas primeiro, como vêm da API). Toque abre a conta. */
function UpcomingBills({ bills }: { bills: BillOccurrence[] }) {
  if (bills.length === 0) {
    return null;
  }

  return (
    <>
      <SectionHeader title="Próximas contas" action={{ label: 'Agenda', onPress: () => router.push('/agenda') }} />
      {bills.slice(0, 3).map((occurrence) => {
        const due = billDueLabel(occurrence);

        return (
          <Pressable
            key={`${occurrence.bill.id}-${occurrence.month}`}
            onPress={() => router.push({ pathname: '/bills/[id]', params: { id: String(occurrence.bill.id) } })}
            style={({ pressed }) => [styles.row, pressed && styles.pressed]}
            accessibilityRole="button"
          >
            <CategoryIcon icon={occurrence.bill.category.icon} color={occurrence.bill.category.color} />
            <View style={styles.texts}>
              <Text variant="strong" numberOfLines={1}>
                {occurrence.bill.name}
              </Text>
              <Text variant="caption" tone={due.tone} numberOfLines={2}>
                {due.text}
                {occurrence.is_estimate ? ' · valor estimado' : ''}
              </Text>
            </View>
            {occurrence.amount_cents === null ? (
              <Text variant="caption" tone="muted">
                valor variável
              </Text>
            ) : (
              <Amount cents={occurrence.amount_cents} estimate={occurrence.is_estimate} />
            )}
          </Pressable>
        );
      })}
    </>
  );
}

/** As faturas que vencem no mês, com o cartão na cor dele. Toque abre a fatura do cartão. */
function Invoices({ invoices }: { invoices: Summary['invoices'] }) {
  const cards = useCards();

  if (invoices.length === 0) {
    return null;
  }

  return (
    <>
      <SectionHeader title="Faturas do mês" />
      {invoices.map((invoice) => (
        <Pressable
          key={invoice.card.id}
          onPress={() => router.push({ pathname: '/cards/[id]', params: { id: String(invoice.card.id) } })}
          style={({ pressed }) => [styles.row, pressed && styles.pressed]}
          accessibilityRole="button"
        >
          <View style={styles.iconColumn}>
            <CreditCard
              size={20}
              color={cards.data?.find((card) => card.id === invoice.card.id)?.color ?? colors.muted}
              strokeWidth={1.6}
            />
          </View>
          <View style={styles.texts}>
            <Text variant="strong" numberOfLines={1}>
              {invoice.card.name} · {invoice.card.owner}
            </Text>
            <Text variant="caption" tone="muted">
              fecha {shortDate(invoice.closes_on)} · vence {shortDate(invoice.due_on)}
            </Text>
          </View>
          <Amount cents={invoice.total_cents} />
        </Pressable>
      ))}
    </>
  );
}

/**
 * Para onde foi o dinheiro: uma barra só, em Tinta, proporcional à maior categoria (o que importa é
 * o comprimento; a cor da categoria fica no ícone). Toque abre os lançamentos da categoria.
 */
function Categories({ summary }: { summary: Summary }) {
  if (summary.by_category.length === 0) {
    return null;
  }

  const biggest = summary.by_category[0]?.total_cents ?? 0;

  return (
    <>
      <SectionHeader title="Gastos por categoria" detail="% das despesas" />
      {summary.by_category.map((category) => (
        <Pressable
          key={category.id}
          onPress={() =>
            router.push({
              pathname: '/transactions',
              params: { month: summary.month, category_id: String(category.id), category_name: category.name },
            })
          }
          style={({ pressed }) => [styles.category, pressed && styles.pressed]}
          accessibilityRole="button"
        >
          <View style={styles.line}>
            <View style={[styles.details, styles.shrink]}>
              <CategoryIcon icon={category.icon} color={category.color} size={20} />
              <Text variant="strong" numberOfLines={1} style={styles.shrink}>
                {category.name}
              </Text>
            </View>
            <Amount cents={category.total_cents} />
          </View>
          <View style={styles.share}>
            <View style={styles.shareBar}>
              <ProgressBar percent={(category.total_cents / biggest) * 100} color={colors.ink} trackless />
            </View>
            <Text variant="caption" tone="muted" style={styles.percent}>
              {Math.round((category.total_cents / summary.expense_cents) * 100)}%
            </Text>
          </View>
        </Pressable>
      ))}
    </>
  );
}

// As fatias de "Quem gastou": Tinta, Grafite e, numa casa maior, a Borda e a Pauta.
const MEMBER_COLORS = [colors.ink, colors.muted, colors.border, colors.line];

/** Quanto cada pessoa da casa gastou: a barra dividida e uma linha por pessoa. Só com mais de uma. */
function Members({ summary }: { summary: Summary }) {
  if (summary.by_member.length < 2) {
    return null;
  }

  return (
    <>
      <SectionHeader title="Quem gastou" />
      <View style={styles.split}>
        {summary.by_member.map((member, index) => (
          <View
            key={member.id}
            style={[styles.slice, { flex: member.total_cents, backgroundColor: MEMBER_COLORS[index % MEMBER_COLORS.length] }]}
          />
        ))}
      </View>
      {summary.by_member.map((member, index) => (
        <View key={member.id} style={styles.row}>
          <View style={[styles.details, styles.texts]}>
            <CategoryMark color={MEMBER_COLORS[index % MEMBER_COLORS.length]} />
            <Text variant="strong" numberOfLines={1} style={styles.shrink}>
              {member.name}
            </Text>
          </View>
          <Text variant="caption" tone="muted" style={styles.tabular}>
            {Math.round((member.total_cents / summary.expense_cents) * 100)}%
          </Text>
          <Amount cents={member.total_cents} />
        </View>
      ))}
    </>
  );
}

/** A lista de lançamentos do mês, só receitas ou só despesas quando vem de um dos totais. */
function openTransactions(month: string, kind?: CategoryKind) {
  router.push({ pathname: '/transactions', params: kind ? { month, kind } : { month } });
}

const styles = StyleSheet.create({
  content: { paddingBottom: space.xxxl },
  status: { marginTop: space.xxl },
  hero: { marginTop: space.xl, marginBottom: space.lg, gap: space.xs },
  line: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.md },
  shrink: { flexShrink: 1 },
  regular: { fontFamily: font.regular },
  budget: { gap: space.sm, paddingVertical: space.md, borderBottomWidth: 1, borderBottomColor: colors.line },
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
  details: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  iconColumn: { width: 24, alignItems: 'center' },
  category: { gap: space.sm, paddingVertical: space.md, borderBottomWidth: 1, borderBottomColor: colors.line },
  share: { flexDirection: 'row', alignItems: 'center', gap: space.md },
  shareBar: { flex: 1 },
  percent: { width: 40, textAlign: 'right', fontVariant: ['tabular-nums'] },
  tabular: { fontVariant: ['tabular-nums'] },
  split: { flexDirection: 'row', gap: space.xs, height: 6, marginTop: space.md, marginBottom: space.xs },
  slice: { borderRadius: radius.xs },
});
