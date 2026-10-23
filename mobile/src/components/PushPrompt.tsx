import { router, useFocusEffect } from 'expo-router';
import { Bell } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { StyleSheet } from 'react-native';

import { BottomSheet, Button, Text } from '@/components/ui';
import { enablePush, markAsked, pushStatus, wasAsked } from '@/lib/push';
import { useTour } from '@/stores/tour';
import { colors, space } from '@/theme/tokens';

/**
 * "Receber avisos no celular?": a folha que explica as notificações antes do pedido do sistema
 * (que, no iPhone, só pode ser feito uma vez). Aparece uma vez por aparelho, no Início, depois das
 * boas-vindas e fora do tour. Quem disser "Agora não" ativa depois em Configurações › Avisos.
 */
export function PushPrompt({ ready }: { ready: boolean }) {
  const [visible, setVisible] = useState(false);
  const [busy, setBusy] = useState(false);
  const touring = useTour((state) => state.active);

  // Só com o Início na frente: a folha é um Modal e apareceria por cima de qualquer outra tela.
  useFocusEffect(
    useCallback(() => {
      if (!ready || touring) {
        return;
      }

      let cancelled = false;

      Promise.all([pushStatus(), wasAsked()])
        .then(([status, asked]) => {
          if (!cancelled && status === 'undetermined' && !asked) {
            setVisible(true);
          }
        })
        .catch(() => undefined);

      return () => {
        cancelled = true;
      };
    }, [ready, touring]),
  );

  function dismiss() {
    markAsked().catch(() => undefined);
    setVisible(false);
  }

  async function enable() {
    setBusy(true);

    try {
      await enablePush();
    } catch (error) {
      // Sem o Firebase configurado no build, por exemplo. A pessoa tenta de novo em Configurações.
      if (__DEV__) {
        console.warn('[push] não deu para ativar:', error);
      }
    } finally {
      setBusy(false);
      setVisible(false);
    }
  }

  return (
    <BottomSheet visible={visible} onClose={dismiss} closeLabel="Agora não">
      <Bell size={28} color={colors.ink} strokeWidth={1.6} />
      <Text variant="subtitle" accessibilityRole="header" style={styles.title}>
        Receber avisos no celular?
      </Text>
      <Text tone="muted" style={styles.text}>
        Contas vencendo, lembretes na hora marcada, orçamento perto do limite e o resumo da semana. Os avisos também
        continuam no Chat.
      </Text>
      <Text variant="caption" tone="muted" style={styles.text}>
        Você escolhe quais avisos quer em{' '}
        <Text
          variant="caption"
          style={styles.link}
          onPress={() => {
            dismiss();
            router.push('/settings/alerts');
          }}
        >
          Configurações › Avisos
        </Text>
        .
      </Text>
      <Button
        title="Ativar avisos"
        icon={<Bell size={18} color={colors.canvas} strokeWidth={1.6} />}
        loading={busy}
        onPress={enable}
        style={styles.primary}
      />
      <Button title="Agora não" variant="ghost" disabled={busy} onPress={dismiss} style={styles.secondary} />
    </BottomSheet>
  );
}

const styles = StyleSheet.create({
  title: { marginTop: space.md },
  text: { marginTop: space.sm },
  link: { color: colors.accent, textDecorationLine: 'underline' },
  primary: { marginTop: space.xl },
  secondary: { marginTop: space.sm },
});
