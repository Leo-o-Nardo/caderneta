import { Stack } from 'expo-router';
import { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';

import { syncAssistantEvents } from '@/api/assistant';
import { useMe } from '@/api/auth';
import { TourOverlay } from '@/components/tour/TourOverlay';
import { useRealtimeConnection } from '@/lib/realtime';
import { usePushNotifications } from '@/lib/usePushNotifications';
import { colors } from '@/theme/tokens';

// Com login, o app abre nas abas.
export const unstable_settings = {
  initialRouteName: '(tabs)',
};

/**
 * Tudo que exige login: as abas e as telas que abrem por cima delas (lançamentos, agenda, listas e
 * configurações). A proteção fica no layout da raiz, uma vez só para o grupo inteiro.
 */
// Formulários abrem por cima, deslizando de baixo; telas de consulta (fatura, detalhe) empilham.
const modal = { presentation: 'modal', animation: 'slide_from_bottom' } as const;

export default function AppLayout() {
  // O canal ao vivo (respostas do assistente) fica aberto enquanto a pessoa está logada, em
  // qualquer tela: uma resposta que chega com o chat fechado já está pronta ao abrir.
  const { data: me } = useMe();
  useRealtimeConnection(me?.id);
  useEffect(() => syncAssistantEvents(), []);
  // As notificações: registra o aparelho e abre o Chat certo ao tocar numa delas.
  usePushNotifications(me);

  return (
    <View style={styles.root}>
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.canvas } }}>
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="transactions/index" />
        <Stack.Screen name="transactions/new" options={modal} />
        <Stack.Screen name="transactions/[id]" options={modal} />
        <Stack.Screen name="bills/[id]/index" />
        <Stack.Screen name="bills/new" options={modal} />
        <Stack.Screen name="bills/pay" options={modal} />
        <Stack.Screen name="bills/[id]/edit" options={modal} />
        <Stack.Screen name="recurring-incomes/new" options={modal} />
        <Stack.Screen name="recurring-incomes/[id]" options={modal} />
        <Stack.Screen name="cards/[id]/index" />
        <Stack.Screen name="cards/new" options={modal} />
        <Stack.Screen name="cards/[id]/edit" options={modal} />
        <Stack.Screen name="reminders/new" options={modal} />
        <Stack.Screen name="reminders/[id]" options={modal} />
        <Stack.Screen name="lists/new" options={modal} />
        <Stack.Screen name="lists/[id]/edit" options={modal} />
        <Stack.Screen name="list-items/[id]" options={modal} />
        <Stack.Screen name="settings/index" />
        <Stack.Screen name="settings/household" />
        <Stack.Screen name="settings/alerts" />
        <Stack.Screen name="settings/profile" options={modal} />
        <Stack.Screen name="settings/password" options={modal} />
        <Stack.Screen name="settings/delete-account" options={modal} />
        <Stack.Screen name="settings/households" options={modal} />
        <Stack.Screen name="settings/household-form" options={modal} />
        <Stack.Screen name="settings/join" options={modal} />
        <Stack.Screen name="settings/member/[id]" options={modal} />
        <Stack.Screen name="categories/index" />
        <Stack.Screen name="categories/new" options={modal} />
        <Stack.Screen name="categories/[id]" options={modal} />
        <Stack.Screen name="budgets/index" />
        <Stack.Screen name="budgets/[categoryId]" options={modal} />
        <Stack.Screen name="onboarding/index" />
        {/* As boas-vindas cobrem a tela toda e só saem por "Pular" ou por um caminho escolhido. */}
        <Stack.Screen name="onboarding/welcome" options={{ presentation: 'fullScreenModal', animation: 'fade', gestureEnabled: false }} />
      </Stack>
      {/* O tour fica por cima de todas as telas do app, mas só aparece quando alguém o começa. */}
      <TourOverlay />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
});
