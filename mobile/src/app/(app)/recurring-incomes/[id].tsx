import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { useRecurringIncome } from '@/api/recurringIncomes';
import { RecurringIncomeForm } from '@/components/RecurringIncomeForm';
import { Header, Screen, Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

/** Uma receita fixa: abre pela Agenda (aba Contas) ou pelo lançamento que ela gerou. */
export default function RecurringIncomeScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const query = useRecurringIncome(Number(id));

  return (
    <Screen>
      <Header title="Receita fixa" leading="close" />

      {query.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : query.isError ? (
        <Text tone="danger" style={styles.status}>
          {query.error.message}
        </Text>
      ) : (
        <RecurringIncomeForm income={query.data} />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
