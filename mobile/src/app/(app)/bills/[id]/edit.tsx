import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { useBill } from '@/api/bills';
import { BillForm } from '@/components/BillForm';
import { Header, Screen, Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

export default function EditBillScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const query = useBill(Number(id));

  return (
    <Screen>
      <Header title="Editar conta" leading="close" />

      {query.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : query.isError ? (
        <Text tone="danger" style={styles.status}>
          {query.error.message}
        </Text>
      ) : (
        <BillForm bill={query.data} />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
