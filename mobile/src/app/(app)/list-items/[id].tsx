import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { useListItems, useLists } from '@/api/lists';
import { ListItemForm } from '@/components/ListItemForm';
import { Header, Screen, Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

/** Editar um item. Ele vem dos itens que a tela da lista já carregou (a API não tem rota de um só). */
export default function EditListItemScreen() {
  const params = useLocalSearchParams<{ id: string; list_id: string }>();
  const listId = Number(params.list_id);
  const lists = useLists();
  const items = useListItems(listId);
  const list = lists.data?.find((item) => item.id === listId);
  const item = items.data?.find((entry) => entry.id === Number(params.id));

  return (
    <Screen>
      <Header title="Item" leading="close" />

      {lists.isPending || items.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : list && item ? (
        <ListItemForm item={item} kind={list.kind} listName={list.name} />
      ) : (
        <Text tone="muted" style={styles.status}>
          Este item não está mais na lista.
        </Text>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
