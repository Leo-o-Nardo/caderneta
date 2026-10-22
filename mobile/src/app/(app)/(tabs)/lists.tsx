import { router } from 'expo-router';
import { ListChecks, Plus } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet } from 'react-native';

import { useCanWrite } from '@/api/auth';
import { useLists } from '@/api/lists';
import { CategoryIcon } from '@/components/CategoryIcon';
import { ListView } from '@/components/ListView';
import { TourTarget } from '@/components/tour/TourTarget';
import { Chip, EmptyState, IconButton, Screen, Text } from '@/components/ui';
import { LIST_KINDS } from '@/lib/lists';
import { colors, space } from '@/theme/tokens';

/**
 * As listas da casa: as tags no topo trocam de lista (com o ícone na cor do tipo e quantos itens
 * faltam), e o "+" cria uma nova.
 */
export default function ListsScreen() {
  const lists = useLists();
  const canWrite = useCanWrite();
  const [selectedId, setSelectedId] = useState<number | null>(null);
  // Sem escolha (ou se a escolhida foi apagada), abre a lista padrão.
  const selected =
    lists.data?.find((list) => list.id === selectedId) ?? lists.data?.find((list) => list.is_default) ?? lists.data?.[0];

  return (
    <Screen edges={['top']}>
      <TourTarget id="lists.chips" style={styles.chipsRow}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
          {lists.data?.map((list) => (
            <Chip
              key={list.id}
              label={list.name}
              icon={<CategoryIcon icon={list.icon} color={LIST_KINDS[list.kind].color} size={20} />}
              detail={list.open_items_count > 0 ? String(list.open_items_count) : undefined}
              selected={list.id === selected?.id}
              onPress={() => setSelectedId(list.id)}
            />
          ))}
        </ScrollView>
        {canWrite ? <IconButton icon={Plus} label="Nova lista" onPress={() => router.push('/lists/new')} /> : null}
      </TourTarget>

      {lists.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : lists.isError ? (
        <Text tone="danger" style={styles.status}>
          {lists.error.message}
        </Text>
      ) : selected ? (
        // A chave faz o campo de adicionar e os avisos recomeçarem ao trocar de lista.
        <ListView key={selected.id} list={selected} />
      ) : (
        <EmptyState icon={ListChecks} title="Nenhuma lista" description={canWrite ? 'Crie uma lista no +.' : undefined} />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  chipsRow: { flexDirection: 'row', alignItems: 'center', gap: space.sm, paddingTop: space.lg, paddingBottom: space.md },
  chips: { gap: space.sm, paddingRight: space.sm },
  status: { marginTop: space.xxl },
});
