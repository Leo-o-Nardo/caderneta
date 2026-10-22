import { router, useLocalSearchParams } from 'expo-router';
import { Plus, Receipt } from 'lucide-react-native';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, SectionList, StyleSheet, View } from 'react-native';

import { useCanWrite } from '@/api/auth';
import { useCategories } from '@/api/categories';
import { useTransactions } from '@/api/transactions';
import type { CategoryKind, Transaction } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { MonthSwitcher } from '@/components/MonthSwitcher';
import { TransactionRow } from '@/components/TransactionRow';
import { Chip, EmptyState, Header, Screen, SegmentedControl, Text, TextField } from '@/components/ui';
import { dayHeading, monthName, toMonth } from '@/lib/dates';
import { useDebouncedValue } from '@/lib/hooks';
import { formatCents } from '@/lib/money';
import { colors, radius, space } from '@/theme/tokens';

type Section = {
  title: string;
  spentCents: number;
  data: Transaction[];
};

/**
 * Todos os lançamentos, agrupados por dia, 50 por vez (carrega mais ao chegar no fim).
 * O Resumo abre esta tela já filtrada pelo mês e, às vezes, por categoria ou por tipo (receitas/despesas).
 */
export default function TransactionsScreen() {
  const params = useLocalSearchParams<{ month?: string; kind?: CategoryKind; category_id?: string; category_name?: string }>();
  const canWrite = useCanWrite();
  const [month, setMonth] = useState(params.month ?? toMonth(new Date()));
  const [kind, setKind] = useState<'all' | CategoryKind>(params.kind ?? 'all');
  const [categoryId, setCategoryId] = useState(params.category_id ? Number(params.category_id) : undefined);
  const [search, setSearch] = useState('');
  const text = useDebouncedValue(search.trim());
  // O ícone e a cor da categoria do filtro (o Resumo manda só o id e o nome).
  const categories = useCategories();
  const filterCategory = categories.data?.find((category) => category.id === categoryId);

  const query = useTransactions({
    month,
    kind: kind === 'all' ? undefined : kind,
    category_id: categoryId,
    q: text || undefined,
  });

  const sections = useMemo(() => groupByDay(query.data?.pages.flatMap((page) => page.data) ?? []), [query.data]);

  return (
    <Screen>
      <Header title="Lançamentos" />

      <SectionList
        sections={sections}
        keyExtractor={(item) => String(item.id)}
        stickySectionHeadersEnabled={false}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.content}
        ListHeaderComponent={
          <View style={styles.filters}>
            <MonthSwitcher month={month} onChange={setMonth} />
            <SegmentedControl
              options={[
                { value: 'all', label: 'Tudo' },
                { value: 'expense', label: 'Despesas' },
                { value: 'income', label: 'Receitas' },
              ]}
              value={kind}
              onChange={setKind}
            />
            <TextField label="Buscar" placeholder="Pela descrição" value={search} onChangeText={setSearch} returnKeyType="search" />
            {categoryId ? (
              <View style={styles.row}>
                <Chip
                  label={filterCategory?.name ?? params.category_name ?? 'Categoria'}
                  icon={filterCategory ? <CategoryIcon icon={filterCategory.icon} color={filterCategory.color} size={20} /> : undefined}
                  detail="✕"
                  selected
                  onPress={() => setCategoryId(undefined)}
                />
              </View>
            ) : null}
          </View>
        }
        renderSectionHeader={({ section }) => (
          <View style={styles.dayHeader}>
            <Text variant="overline" tone="muted">
              {section.title}
            </Text>
            {section.spentCents > 0 ? (
              <Text variant="caption" tone="muted" style={styles.tabular}>
                {formatCents(section.spentCents)}
              </Text>
            ) : null}
          </View>
        )}
        renderItem={({ item }) => <TransactionRow transaction={item} />}
        onEndReached={() => {
          if (query.hasNextPage && !query.isFetchingNextPage) {
            query.fetchNextPage();
          }
        }}
        onEndReachedThreshold={0.4}
        refreshControl={
          <RefreshControl
            refreshing={query.isRefetching && !query.isFetchingNextPage}
            onRefresh={() => query.refetch()}
            tintColor={colors.accent}
            colors={[colors.accent]}
            progressBackgroundColor={colors.surface}
          />
        }
        ListEmptyComponent={
          query.isPending ? (
            <ActivityIndicator color={colors.accent} style={styles.status} />
          ) : (
            <View style={styles.empty}>
              <EmptyState
                icon={Receipt}
                title={query.isError ? 'Não deu para carregar' : `Nada lançado em ${monthName(month)}`}
                description={query.isError ? query.error.message : 'Os lançamentos que combinarem com os filtros aparecem aqui.'}
              />
            </View>
          )
        }
        ListFooterComponent={query.isFetchingNextPage ? <ActivityIndicator color={colors.accent} style={styles.status} /> : null}
      />

      {/* O + flutuante: um quadrado de Tinta, com o canto de 6 dos botões e sem sombra. Não para o "Só leitura". */}
      {canWrite ? (
        <Pressable
          onPress={() => router.push('/transactions/new')}
          style={({ pressed }) => [styles.fab, pressed && styles.fabPressed]}
          accessibilityRole="button"
          accessibilityLabel="Novo lançamento"
        >
          <Plus size={24} color={colors.canvas} strokeWidth={1.6} />
        </Pressable>
      ) : null}
    </Screen>
  );
}

/** Junta os lançamentos (já vêm do mais novo para o mais antigo) por dia, com o total gasto no dia. */
function groupByDay(items: Transaction[]): Section[] {
  const sections: (Section & { date: string })[] = [];

  for (const item of items) {
    let section = sections.at(-1);

    if (!section || section.date !== item.occurred_on) {
      section = { date: item.occurred_on, title: dayHeading(item.occurred_on), spentCents: 0, data: [] };
      sections.push(section);
    }

    section.data.push(item);

    if (item.kind === 'expense') {
      section.spentCents += item.amount_cents;
    }
  }

  return sections;
}

const FAB_SIZE = 52;

const styles = StyleSheet.create({
  // Espaço no fim da lista para a última linha não ficar embaixo do +.
  content: { paddingBottom: FAB_SIZE + space.xxl },
  filters: { gap: space.md, paddingBottom: space.sm },
  row: { flexDirection: 'row' },
  // O título do dia com o traço de Tinta embaixo, como uma seção.
  dayHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
    paddingTop: space.xl,
    paddingBottom: space.sm,
    borderBottomWidth: 1,
    borderBottomColor: colors.ink,
    backgroundColor: colors.canvas,
  },
  tabular: { fontVariant: ['tabular-nums'] },
  status: { marginVertical: space.xl },
  empty: { paddingTop: space.xxl },
  fab: {
    position: 'absolute',
    right: space.xl,
    bottom: space.xl,
    width: FAB_SIZE,
    height: FAB_SIZE,
    borderRadius: radius.md,
    backgroundColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  fabPressed: { backgroundColor: colors.inkPressed, transform: [{ translateY: 1 }] },
});
