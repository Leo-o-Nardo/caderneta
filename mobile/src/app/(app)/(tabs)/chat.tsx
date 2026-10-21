import { usePathname } from 'expo-router';
import { MessageCircle, Trash2 } from 'lucide-react-native';
import { useEffect } from 'react';
import { ActivityIndicator, Alert, FlatList, StyleSheet, View } from 'react-native';

import { markAlertsSeen, useUnreadAlerts } from '@/api/alerts';
import { clearConversation, sendMessage, sendVoice, useConversation } from '@/api/assistant';
import { useMe } from '@/api/auth';
import { Composer } from '@/components/chat/Composer';
import { MessageItem } from '@/components/chat/MessageItem';
import { Chip, EmptyState, IconButton, Screen, Text } from '@/components/ui';
import { startsDay } from '@/lib/chat';
import { useRealtimeStatus } from '@/lib/realtime';
import { useChatStream } from '@/stores/chatStream';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

const SUGGESTIONS = ['Quanto gastei esse mês?', 'Gastei 12 de café', 'Coloca leite na lista'];

/**
 * O chat com o assistente: sempre a mesma conversa (por casa). As respostas chegam aos pedaços
 * pelo WebSocket; se ele cair, a tela busca sozinha até a resposta ficar pronta.
 */
export default function ChatScreen() {
  const { data: me } = useMe();
  const connected = useRealtimeStatus((state) => state.connected);
  const streams = useChatStream((state) => state.streams);
  const conversation = useConversation({ connected });
  const messages = conversation.data?.pages.flatMap((page) => page.data) ?? [];
  const unread = useUnreadAlerts();
  // A aba fica montada depois de aberta: só conta como "visto" com o Chat na tela.
  const onScreen = usePathname() === '/chat';

  // Com o Chat na tela, os avisos (os que já estavam e os que chegam agora) contam como vistos.
  useEffect(() => {
    if (onScreen && (unread.data ?? 0) > 0) {
      markAlertsSeen().catch(() => {});
    }
  }, [onScreen, unread.data]);

  async function confirmClear() {
    const confirmed = await confirm({
      title: 'Limpar a conversa?',
      message: 'O chat recomeça do zero. A conversa antiga fica guardada, e o que já foi lançado continua.',
      confirmLabel: 'Limpar conversa',
      destructive: true,
    });

    if (confirmed) {
      clearConversation().catch((error: Error) => Alert.alert('Não deu para limpar', error.message));
    }
  }

  return (
    <Screen edges={['top']}>
      <View style={styles.header}>
        <View style={styles.flex}>
          <Text variant="title" style={styles.title}>
            Assistente
          </Text>
          <Text variant="caption" tone="muted" numberOfLines={1}>
            {me?.current_household?.name ?? ' '}
          </Text>
        </View>
        {messages.length > 0 ? (
          <IconButton icon={Trash2} label="Limpar a conversa" tone="muted" onPress={confirmClear} />
        ) : null}
      </View>

      {conversation.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.flex} />
      ) : conversation.isError ? (
        <View style={styles.flex}>
          <Text tone="danger" style={styles.error}>
            {conversation.error.message}
          </Text>
        </View>
      ) : messages.length === 0 ? (
        <Suggestions onPick={(text) => sendMessage(text)} />
      ) : (
        <FlatList
          data={messages}
          // De baixo para cima, como todo chat: a mais nova fica embaixo, perto do campo.
          inverted
          keyExtractor={(message) => String(message.id)}
          renderItem={({ item, index }) => (
            <MessageItem message={item} showDay={startsDay(messages, index)} stream={streams[item.id]} />
          )}
          onEndReached={() => {
            if (conversation.hasNextPage && !conversation.isFetchingNextPage) {
              conversation.fetchNextPage();
            }
          }}
          onEndReachedThreshold={0.4}
          ListFooterComponent={conversation.isFetchingNextPage ? <ActivityIndicator color={colors.accent} /> : null}
          contentContainerStyle={styles.list}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        />
      )}

      <Composer onSend={(text) => sendMessage(text)} onSendVoice={(recording) => sendVoice(recording, 'chat')} />
    </Screen>
  );
}

/** A conversa vazia: o que dá para pedir, com atalhos que já mandam a frase. */
function Suggestions({ onPick }: { onPick: (text: string) => Promise<void> }) {
  const pick = (text: string) => onPick(text).catch((error: Error) => Alert.alert('Não deu para enviar', error.message));

  return (
    <EmptyState
      icon={MessageCircle}
      title="Pergunte ou peça"
      description="Quanto gastou, como está o mês, lançar um gasto, pagar uma conta, colocar algo na lista ou criar um lembrete."
      action={
        <View style={styles.suggestions}>
          {SUGGESTIONS.map((text) => (
            <Chip key={text} label={text} onPress={() => pick(text)} />
          ))}
        </View>
      }
    />
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: space.md,
    paddingTop: space.sm,
    paddingBottom: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  title: { fontSize: 24, lineHeight: 30 },
  flex: { flex: 1 },
  error: { marginTop: space.xxl },
  list: { paddingVertical: space.sm },
  suggestions: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
});
