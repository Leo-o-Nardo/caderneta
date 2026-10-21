import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { useLists } from '@/api/lists';
import { ListForm } from '@/components/ListForm';
import { Header, Screen, Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

export default function EditListScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const lists = useLists();
  const list = lists.data?.find((item) => item.id === Number(id));

  return (
    <Screen>
      <Header title="Lista" leading="close" />

      {lists.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : list ? (
        <ListForm list={list} />
      ) : (
        <Text tone="muted" style={styles.status}>
          Esta lista não existe mais.
        </Text>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
