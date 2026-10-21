import * as Haptics from 'expo-haptics';
import { ArrowUp, Mic, X } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, TextInput, View } from 'react-native';

import type { VoiceRecording } from '@/api/assistant';
import { Text } from '@/components/ui';
import { formatDuration } from '@/lib/chat';
import { MAX_VOICE_MS, useVoiceRecorder } from '@/lib/useVoiceRecorder';
import { colors, font, radius, space } from '@/theme/tokens';

type Props = {
  onSend: (text: string) => Promise<void>;
  onSendVoice: (recording: VoiceRecording) => Promise<void>;
};

/**
 * O campo de escrever do chat (guia, parte 05): contorno da Borda de campo e botões quadrados em
 * Tinta. Com texto, o botão é enviar; vazio, é o microfone: um toque começa
 * a gravar (a barra vira o contador, com cancelar e enviar), aos 60 s a gravação vai sozinha. Se o
 * envio falhar, o texto volta para o campo, para não perder o que foi escrito.
 */
export function Composer({ onSend, onSendVoice }: Props) {
  const [text, setText] = useState('');
  const [sending, setSending] = useState(false);
  const hasText = text.trim() !== '';

  async function send() {
    const value = text.trim();

    if (value === '' || sending) {
      return;
    }

    setSending(true);
    setText('');

    try {
      await onSend(value);
    } catch (error) {
      setText(value);
      Alert.alert('Não deu para enviar', error instanceof Error ? error.message : 'Tente de novo.');
    } finally {
      setSending(false);
    }
  }

  async function sendVoice(recording: VoiceRecording | null) {
    if (recording === null) {
      Alert.alert('Gravação curta demais', 'Toque no microfone, fale e toque em enviar.');

      return;
    }

    setSending(true);

    try {
      await onSendVoice(recording);
    } catch (error) {
      Alert.alert('Não deu para enviar o áudio', error instanceof Error ? error.message : 'Tente de novo.');
    } finally {
      setSending(false);
    }
  }

  // Aos 60 s a gravação para e vai sozinha.
  const voice = useVoiceRecorder((recording) => sendVoice(recording));

  if (voice.isRecording) {
    return (
      <View style={styles.bar}>
        <Pressable
          onPress={() => voice.cancel()}
          style={({ pressed }) => [styles.button, styles.outline, pressed && styles.outlinePressed]}
          accessibilityRole="button"
          accessibilityLabel="Cancelar a gravação"
        >
          <X size={20} color={colors.ink} strokeWidth={1.6} />
        </Pressable>

        <View style={[styles.input, styles.recording]}>
          <View style={styles.dot} />
          <Text variant="strong" style={styles.tabular}>
            {formatDuration(voice.durationMs)}
          </Text>
          <Text variant="caption" tone="muted">
            de {formatDuration(MAX_VOICE_MS)} · gravando
          </Text>
        </View>

        <Pressable
          onPress={async () => sendVoice(await voice.finish())}
          style={({ pressed }) => [styles.button, styles.ink, pressed && styles.inkPressed]}
          accessibilityRole="button"
          accessibilityLabel="Enviar o áudio"
        >
          <ArrowUp size={20} color={colors.canvas} strokeWidth={2} />
        </Pressable>
      </View>
    );
  }

  return (
    <View style={styles.bar}>
      <View style={styles.input}>
        <TextInput
          value={text}
          onChangeText={setText}
          placeholder="Mensagem"
          placeholderTextColor={colors.faint}
          selectionColor={colors.accent}
          cursorColor={colors.accent}
          multiline
          maxLength={2000}
          style={styles.field}
          accessibilityLabel="Mensagem para o assistente"
        />
      </View>

      {hasText || sending ? (
        <Pressable
          onPress={send}
          disabled={sending}
          style={({ pressed }) => [styles.button, styles.ink, pressed && styles.inkPressed]}
          accessibilityRole="button"
          accessibilityLabel="Enviar"
        >
          {sending ? <ActivityIndicator size="small" color={colors.canvas} /> : <ArrowUp size={20} color={colors.canvas} strokeWidth={2} />}
        </Pressable>
      ) : (
        <Pressable
          onPress={() => {
            // Uma vibração leve confirma que começou a gravar.
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            voice.start();
          }}
          style={({ pressed }) => [styles.button, styles.ink, pressed && styles.inkPressed]}
          accessibilityRole="button"
          accessibilityLabel="Gravar uma mensagem de voz"
        >
          <Mic size={20} color={colors.canvas} strokeWidth={1.6} />
        </Pressable>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: space.sm,
    paddingVertical: space.sm,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  input: {
    flex: 1,
    minHeight: 44,
    maxHeight: 120,
    justifyContent: 'center',
    paddingHorizontal: space.md,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
  },
  recording: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-start', gap: space.sm },
  dot: { width: 10, height: 10, borderRadius: radius.pill, backgroundColor: colors.danger },
  tabular: { fontVariant: ['tabular-nums'] },
  field: { color: colors.ink, fontFamily: font.regular, fontSize: 16, paddingVertical: space.sm },
  // Os botões quadrados do guia, com o mesmo canto de 6 dos outros botões.
  button: { width: 44, height: 44, borderRadius: radius.md, alignItems: 'center', justifyContent: 'center' },
  ink: { backgroundColor: colors.ink },
  inkPressed: { backgroundColor: colors.inkPressed, transform: [{ translateY: 1 }] },
  outline: { borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface },
  outlinePressed: { backgroundColor: colors.pressed },
});
