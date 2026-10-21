import { Tabs } from 'expo-router';
import { PlatformPressable } from 'expo-router/react-navigation';
import { CalendarDays, ChartPie, House, ListChecks, MessageCircle } from 'lucide-react-native';
import type { ComponentProps } from 'react';
import { StyleSheet, View } from 'react-native';

import { useUnreadAlerts } from '@/api/alerts';
import { Text } from '@/components/ui';
import { colors, font } from '@/theme/tokens';

/**
 * As 5 abas: Início, Chat, Resumo, Agenda e Lista, com ícone fino e o nome embaixo (parte 06 do
 * guia). A aba aberta fica em Tinta, com o traço mais grosso e uma linha em cima, então não
 * depende só da cor. A aba Chat mostra quantos avisos do app (lembretes, contas vencendo,
 * orçamento, resumo da semana) chegaram desde a última vez que ela foi aberta.
 */
export default function TabsLayout() {
  const unread = useUnreadAlerts();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.ink,
        tabBarInactiveTintColor: colors.muted,
        tabBarStyle: { backgroundColor: colors.canvas, borderTopColor: colors.line, borderTopWidth: 1 },
        tabBarLabel: ({ focused, color, children }) => (
          <Text variant="label" style={[styles.label, { color }, focused && styles.labelFocused]}>
            {children}
          </Text>
        ),
        tabBarButton: (props) => <TabButton {...props} />,
        tabBarBadgeStyle: styles.badge,
        sceneStyle: { backgroundColor: colors.canvas },
        // Com o teclado aberto (chat, itens da lista), a barra some e o campo sobe colado nele.
        tabBarHideOnKeyboard: true,
      }}
    >
      {/* Ícones de 20 px com traço 1,6, que engrossa para 2 na aba aberta. */}
      <Tabs.Screen
        name="index"
        options={{
          title: 'Início',
          tabBarIcon: ({ color, focused }) => <House size={20} color={color} strokeWidth={stroke(focused)} />,
        }}
      />
      <Tabs.Screen
        name="chat"
        options={{
          title: 'Chat',
          tabBarIcon: ({ color, focused }) => <MessageCircle size={20} color={color} strokeWidth={stroke(focused)} />,
          tabBarBadge: unread.data ? unread.data : undefined,
        }}
      />
      <Tabs.Screen
        name="summary"
        options={{
          title: 'Resumo',
          tabBarIcon: ({ color, focused }) => <ChartPie size={20} color={color} strokeWidth={stroke(focused)} />,
        }}
      />
      <Tabs.Screen
        name="agenda"
        options={{
          title: 'Agenda',
          tabBarIcon: ({ color, focused }) => <CalendarDays size={20} color={color} strokeWidth={stroke(focused)} />,
        }}
      />
      <Tabs.Screen
        name="lists"
        options={{
          title: 'Lista',
          tabBarIcon: ({ color, focused }) => <ListChecks size={20} color={color} strokeWidth={stroke(focused)} />,
        }}
      />
    </Tabs>
  );
}

function stroke(focused: boolean): number {
  return focused ? 2 : 1.6;
}

/** O botão de cada aba: o padrão do Expo Router, com a linha de Tinta em cima da aba aberta. */
function TabButton({ children, ...props }: ComponentProps<typeof PlatformPressable>) {
  return (
    <PlatformPressable {...props}>
      {props['aria-selected'] ? <View style={styles.indicator} /> : null}
      {children}
    </PlatformPressable>
  );
}

const styles = StyleSheet.create({
  label: { fontSize: 12, lineHeight: 16 },
  labelFocused: { fontFamily: font.semibold },
  indicator: { position: 'absolute', top: 0, left: '50%', width: 28, height: 2, marginLeft: -14, backgroundColor: colors.ink },
  badge: { backgroundColor: colors.attention, color: colors.ink, fontFamily: font.semibold, fontSize: 11 },
});
