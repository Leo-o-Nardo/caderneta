import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { useTransaction } from '@/api/transactions';
import { TransactionForm } from '@/components/TransactionForm';
import { Header, Screen, Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

export default function EditTransactionScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const query = useTransaction(Number(id));

  return (
    <Screen>
      <Header title={query.data?.installment ? 'Compra parcelada' : 'Lançamento'} leading="close" />

      {query.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : query.isError ? (
        <Text tone="danger" style={styles.status}>
          {query.error.message}
        </Text>
      ) : (
        <TransactionForm transaction={query.data} />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
