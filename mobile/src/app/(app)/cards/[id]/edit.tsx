import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { useCards } from '@/api/cards';
import { CardForm } from '@/components/CardForm';
import { Header, Screen, Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

export default function EditCardScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const cards = useCards();
  const card = cards.data?.find((item) => item.id === Number(id));

  return (
    <Screen>
      <Header title="Editar cartão" leading="close" />

      {cards.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : card && card.is_mine ? (
        <CardForm card={card} />
      ) : (
        <Text tone="muted" style={styles.status}>
          Só o dono do cartão pode alterá-lo.
        </Text>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
