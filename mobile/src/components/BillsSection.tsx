import { router } from 'expo-router';
import { CalendarCheck } from 'lucide-react-native';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { isOpen, useBillsMonth, useUpcomingBills } from '@/api/bills';
import type { BillOccurrence } from '@/api/types';
import { Amount, Button, EmptyState, Text } from '@/components/ui';
import { monthName, toMonth } from '@/lib/dates';
import { colors, space } from '@/theme/tokens';

import { BillRow } from './BillRow';
import { Ledger } from './Ledger';
import { RecurringIncomesBlock } from './RecurringIncomesBlock';

/** A aba Contas da Agenda: as contas fixas do mês e, no fim, as receitas fixas (salário…). */
export function BillsSection() {
  return (
    <View>
      <MonthBills />
      <RecurringIncomesBlock />
    </View>
  );
}

/**
 * As contas fixas do mês, com as atrasadas do mês passado no topo. Em cima, em duas colunas de
 * caderneta: quanto falta pagar (com "~" quando entra alguma estimativa) e quantas já foram pagas.
 */
function MonthBills() {
  const month = toMonth(new Date());
  const monthQuery = useBillsMonth(month);
  const upcoming = useUpcomingBills();

  if (monthQuery.isPending) {
    return <ActivityIndicator color={colors.accent} style={styles.status} />;
  }

  if (monthQuery.isError) {
    return (
      <Text tone="danger" style={styles.status}>
        {monthQuery.error.message}
      </Text>
    );
  }

  // Atrasadas de meses anteriores (o "upcoming" da API inclui o mês passado).
  const earlier = (upcoming.data ?? []).filter((occurrence) => occurrence.month < month);
  const current = sortForList(monthQuery.data);
  const all = [...earlier, ...current];

  if (all.length === 0) {
    return (
      <EmptyState
        icon={CalendarCheck}
        title="Nenhuma conta fixa ainda"
        description="Cadastre luz, aluguel, internet… e o app avisa quando estiverem para vencer."
        action={<Button title="Cadastrar conta" onPress={() => router.push('/bills/new')} />}
      />
    );
  }

  const open = all.filter(isOpen);
  const toPay = open.reduce((total, occurrence) => total + (occurrence.amount_cents ?? 0), 0);
  const approximate = open.some((occurrence) => occurrence.is_estimate || occurrence.amount_cents === null);
  const settled = current.filter((occurrence) => !isOpen(occurrence)).length;

  return (
    <View>
      <Ledger
        cells={[
          {
            label: `A pagar em ${monthName(month)}`,
            value: <Amount cents={toPay} estimate={approximate} variant="figure" />,
          },
          {
            label: 'Pagas',
            value: (
              <Text variant="figure">
                {settled} de {current.length}
              </Text>
            ),
          },
        ]}
      />

      <View style={styles.list}>
        {all.map((occurrence) => (
          <BillRow key={`${occurrence.bill.id}-${occurrence.month}`} occurrence={occurrence} />
        ))}
      </View>
    </View>
  );
}

/** As em aberto primeiro (a que vence antes, no topo); as pagas e puladas no fim. */
function sortForList(occurrences: BillOccurrence[]): BillOccurrence[] {
  return [...occurrences].sort((a, b) => {
    if (isOpen(a) !== isOpen(b)) {
      return isOpen(a) ? -1 : 1;
    }

    return a.due_on.localeCompare(b.due_on);
  });
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  list: { marginTop: space.sm },
});
