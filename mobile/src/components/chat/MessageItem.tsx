import { Bell, CircleAlert, Mic, MicOff } from 'lucide-react-native';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import type { ChatMessage } from '@/api/types';
import { ChatCardView } from '@/components/cards/ChatCardView';
import { Text } from '@/components/ui';
import { formatDuration, messageDayLabel, messageTime, type StreamState } from '@/lib/chat';
import { colors, radius, space } from '@/theme/tokens';

type Props = {
  message: ChatMessage;
  // A primeira mensagem de um dia ganha o separador ("HOJE", "ONTEM", "SEG, 05/10") em cima.
  showDay: boolean;
  // Só nas respostas em andamento: o status e o texto que já chegou.
  stream?: StreamState;
};

/**
 * Uma mensagem do chat (guia, parte 05): a da pessoa num balão à direita, com o canto reto do lado
 * dela; a do assistente direto no papel, sem balão; as fichas embaixo.
 */
export function MessageItem({ message, showDay, stream }: Props) {
  return (
    <View style={styles.item}>
      {showDay ? <DaySeparator label={messageDayLabel(message)} /> : null}

      {message.role === 'user' ? (
        <UserBubble message={message} />
      ) : message.role === 'event' ? (
        <EventMessage message={message} />
      ) : (
        <AssistantMessage message={message} stream={stream} />
      )}
    </View>
  );
}

/** "HOJE" entre duas pautas. */
function DaySeparator({ label }: { label: string }) {
  return (
    <View style={styles.day}>
      <View style={styles.dayLine} />
      <Text variant="overline" tone="muted">
        {label}
      </Text>
      <View style={styles.dayLine} />
    </View>
  );
}

/**
 * Um aviso do próprio app (lembrete, conta vencendo, orçamento). Com ficha, só a ficha: o texto do
 * aviso repetiria o que ela já mostra, e a hora vai no topo dela. Sem ficha, o sininho e o texto.
 */
function EventMessage({ message }: { message: ChatMessage }) {
  if (message.cards.length > 0) {
    return (
      <View style={styles.group}>
        {message.cards.map((card, index) => (
          <ChatCardView key={`${card.type}-${index}`} card={card} messageId={message.id} time={messageTime(message)} />
        ))}
      </View>
    );
  }

  return (
    <View style={styles.event}>
      <Bell size={16} color={colors.muted} strokeWidth={1.6} />
      <Text variant="caption" tone="muted" style={styles.shrink}>
        {message.content}
      </Text>
      <Text variant="caption" tone="muted" style={styles.time}>
        {messageTime(message)}
      </Text>
    </View>
  );
}

/**
 * A mensagem da pessoa. De voz: "Transcrevendo… 0:08" até o texto chegar, ou "Não entendi o
 * áudio" se não deu; pronta, o texto e, embaixo do balão, a hora com um microfone pequeno.
 */
function UserBubble({ message }: { message: ChatMessage }) {
  const voice = message.kind === 'audio';

  if (voice && message.status !== 'completed') {
    const failed = message.status === 'failed';

    return (
      <View style={[styles.bubble, styles.voice]}>
        {failed ? (
          <MicOff size={18} color={colors.danger} strokeWidth={1.6} />
        ) : (
          <ActivityIndicator size="small" color={colors.accent} />
        )}
        <Text tone={failed ? 'danger' : 'muted'}>{failed ? 'Não entendi o áudio' : 'Transcrevendo…'}</Text>
        <Text variant="caption" tone="muted" style={styles.time}>
          {formatDuration(message.duration_ms ?? 0)}
        </Text>
      </View>
    );
  }

  return (
    <View style={styles.userGroup}>
      <View style={styles.bubble}>
        <Text>{message.content}</Text>
      </View>
      <View style={styles.meta}>
        {voice ? <Mic size={12} color={colors.muted} strokeWidth={1.6} /> : null}
        <Text variant="caption" tone="muted" style={styles.time}>
          {messageTime(message)}
        </Text>
      </View>
    </View>
  );
}

function AssistantMessage({ message, stream }: { message: ChatMessage; stream?: StreamState }) {
  if (message.status === 'pending') {
    // Antes do primeiro pedaço de texto: a linha do que a IA está fazendo.
    if (!stream?.text) {
      return (
        <View style={styles.status}>
          <ActivityIndicator size="small" color={colors.accent} />
          <Text variant="caption" tone="muted">
            {stream?.status ?? 'Pensando…'}
          </Text>
        </View>
      );
    }

    return (
      <View style={styles.assistant}>
        <Text>
          {stream.text}
          {/* O cursor em Musgo: o texto ainda está chegando. */}
          <Text tone="accent">▍</Text>
        </Text>
      </View>
    );
  }

  if (message.status === 'failed') {
    return (
      <View style={[styles.assistant, styles.failed]}>
        <CircleAlert size={18} color={colors.danger} strokeWidth={1.6} />
        <Text tone="danger" style={styles.shrink}>
          {message.content}
        </Text>
      </View>
    );
  }

  return (
    <View style={styles.group}>
      {message.content ? (
        <View style={styles.assistant}>
          <Text>{message.content}</Text>
          <Text variant="caption" tone="muted" style={styles.time}>
            {messageTime(message)}
          </Text>
        </View>
      ) : null}

      {message.cards.map((card, index) => (
        <ChatCardView key={'action' in card ? card.action.id : `${card.type}-${index}`} card={card} messageId={message.id} />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  item: { gap: space.lg, paddingVertical: space.sm },
  day: { flexDirection: 'row', alignItems: 'center', gap: space.md },
  dayLine: { flex: 1, height: 1, backgroundColor: colors.line },
  group: { gap: space.md, alignItems: 'stretch' },
  userGroup: { alignSelf: 'flex-end', maxWidth: '84%', alignItems: 'flex-end', gap: space.xs },
  // O balão da pessoa: Folha com a pauta em volta e o canto reto do lado dela.
  bubble: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.lg,
    borderBottomRightRadius: radius.xs,
    paddingHorizontal: space.md,
    paddingVertical: space.sm,
  },
  voice: { alignSelf: 'flex-end', flexDirection: 'row', alignItems: 'center', gap: space.sm },
  meta: { flexDirection: 'row', alignItems: 'center', gap: space.xs },
  // O assistente fala direto no papel.
  assistant: { alignSelf: 'flex-start', maxWidth: '92%', gap: space.xs },
  failed: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  shrink: { flexShrink: 1 },
  time: { fontVariant: ['tabular-nums'] },
  event: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  status: { flexDirection: 'row', alignItems: 'center', gap: space.sm, paddingVertical: space.xs },
});
