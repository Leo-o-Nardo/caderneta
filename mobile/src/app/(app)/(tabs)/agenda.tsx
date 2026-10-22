import { router } from 'expo-router';
import { Plus } from 'lucide-react-native';
import { useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { useCanWrite } from '@/api/auth';
import { BillsSection } from '@/components/BillsSection';
import { CardsSection } from '@/components/CardsSection';
import { RemindersSection } from '@/components/RemindersSection';
import { TourTarget } from '@/components/tour/TourTarget';
import { IconButton, Screen, SegmentedControl, Text } from '@/components/ui';
import { queryClient } from '@/lib/queryClient';
import { colors, space } from '@/theme/tokens';

type Section = 'bills' | 'cards' | 'reminders';

// Para cada seção: as chaves do cache que "puxar para atualizar" busca de novo e a tela do "+".
const SECTIONS = {
  bills: { queries: ['bills', 'recurring-incomes'], create: '/bills/new', createLabel: 'Nova conta' },
  cards: { queries: ['cards'], create: '/cards/new', createLabel: 'Novo cartão' },
  reminders: { queries: ['reminders'], create: '/reminders/new', createLabel: 'Novo lembrete' },
} as const;

/** O que vence e quando: contas fixas, faturas dos cartões e lembretes. */
export default function AgendaScreen() {
  const [section, setSection] = useState<Section>('bills');
  const [refreshing, setRefreshing] = useState(false);
  const config = SECTIONS[section];
  const canWrite = useCanWrite();

  async function refresh() {
    setRefreshing(true);
    await Promise.all(config.queries.map((prefix) => queryClient.refetchQueries({ queryKey: [prefix] })));
    setRefreshing(false);
  }

  return (
    <Screen edges={['top']}>
      <TourTarget id="agenda.sections">
        <View style={styles.header}>
          <Text variant="title">Agenda</Text>
          {canWrite ? <IconButton icon={Plus} label={config.createLabel} onPress={() => router.push(config.create)} /> : null}
        </View>

        <SegmentedControl
          options={[
            { value: 'bills', label: 'Contas' },
            { value: 'cards', label: 'Cartões' },
            { value: 'reminders', label: 'Lembretes' },
          ]}
          value={section}
          onChange={setSection}
        />
      </TourTarget>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={refresh}
            tintColor={colors.accent}
            colors={[colors.accent]}
            progressBackgroundColor={colors.surface}
          />
        }
      >
        {section === 'bills' ? <BillsSection /> : null}
        {section === 'cards' ? <CardsSection /> : null}
        {section === 'reminders' ? <RemindersSection /> : null}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingTop: space.lg, paddingBottom: space.md },
  scroll: { marginTop: space.lg },
  content: { paddingBottom: space.xxl },
});
