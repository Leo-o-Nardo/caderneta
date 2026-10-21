import { router, useLocalSearchParams } from 'expo-router';
import { Pencil, Receipt } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { useCardInvoice, useCards } from '@/api/cards';
import { MonthSwitcher } from '@/components/MonthSwitcher';
import { TransactionRow } from '@/components/TransactionRow';
import { Amount, EmptyState, Header, IconButton, Screen, SectionHeader, Tag, Text } from '@/components/ui';
import { shortDate } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { colors, space } from '@/theme/tokens';

/** A fatura de um cartão, mês a mês: total, datas, quanto é de parcelas e as compras. */
export default function CardInvoiceScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const cardId = Number(id);
  const cards = useCards();
  const card = cards.data?.find((item) => item.id === cardId);
  // Sem mês escolhido, a fatura que está em aberto hoje.
  const [month, setMonth] = useState<string | undefined>(undefined);
  const invoice = useCardInvoice(cardId, month);

  return (
    <Screen>
      <Header
        title={card?.name ?? 'Cartão'}
        right={
          card?.is_mine ? (
            <IconButton icon={Pencil} label="Editar cartão" onPress={() => router.push({ pathname: '/cards/[id]/edit', params: { id } })} />
          ) : null
        }
      />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={invoice.isRefetching}
            onRefresh={() => invoice.refetch()}
            tintColor={colors.accent}
            colors={[colors.accent]}
            progressBackgroundColor={colors.surface}
          />
        }
      >
        {invoice.isPending ? (
          <ActivityIndicator color={colors.accent} style={styles.status} />
        ) : invoice.isError ? (
          <Text tone="danger" style={styles.status}>
            {invoice.error.message}
          </Text>
        ) : (
          <>
            <Text variant="caption" tone="muted">
              Fatura de
            </Text>
            <MonthSwitcher month={invoice.data.month} onChange={setMonth} />

            <View style={styles.hero}>
              <Amount cents={invoice.data.total_cents} variant="display" />
              <View style={styles.dates}>
                <Text variant="caption" tone="muted">
                  fecha {shortDate(invoice.data.closes_on)} · vence {shortDate(invoice.data.due_on)}
                </Text>
                {/* Depois do fechamento, novas compras vão para a próxima fatura. */}
                <Tag label={invoice.data.is_closed ? 'fechada' : 'aberta'} />
              </View>
              {invoice.data.installments_cents > 0 ? (
                <Text variant="caption" tone="muted">
                  {formatCents(invoice.data.installments_cents)} em parcelas
                </Text>
              ) : null}
            </View>

            {invoice.data.items.length > 0 ? (
              <>
                <SectionHeader title="Compras" detail={String(invoice.data.items.length)} style={styles.firstHeader} />
                {invoice.data.items.map((item) => (
                  <TransactionRow key={item.id} transaction={item} />
                ))}
              </>
            ) : (
              <EmptyState
                icon={Receipt}
                title="Nenhuma compra nesta fatura"
                description="As compras no cartão aparecem aqui, na fatura em que caem."
              />
            )}
          </>
        )}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  content: { paddingBottom: space.xxl },
  status: { marginTop: space.xxl },
  hero: { gap: space.xs, paddingTop: space.md },
  dates: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  firstHeader: { marginTop: space.xl },
});
