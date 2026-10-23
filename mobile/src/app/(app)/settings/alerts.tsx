import { useMutation } from '@tanstack/react-query';
import { Bell } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, AppState, Linking, StyleSheet, View } from 'react-native';

import { updateAlertSettings, useMe } from '@/api/auth';
import type { AlertSettings } from '@/api/types';
import { Button, Header, Screen, Text, ToggleRow } from '@/components/ui';
import { enablePush, pushStatus, type PushStatus } from '@/lib/push';
import { colors, space } from '@/theme/tokens';

const OPTIONS: { kind: keyof AlertSettings; label: string; description: string }[] = [
  { kind: 'bill_due', label: 'Contas a vencer', description: 'Na véspera e no dia do vencimento, a partir das 9h.' },
  {
    kind: 'autopay',
    label: 'Débito automático',
    description: 'Quando a conta é lançada sozinha no dia do vencimento (ou quando falta o valor).',
  },
  { kind: 'budget', label: 'Orçamentos', description: 'Quando uma categoria chega a 80% e a 100% do limite do mês.' },
  { kind: 'weekly', label: 'Resumo da semana', description: 'Domingo, às 19h: o gasto da semana e as contas dos próximos dias.' },
];

/**
 * Configurações › Avisos: as notificações neste celular e, embaixo, cada tipo de aviso que chega no
 * Chat (e no celular). Os lembretes sempre avisam.
 */
export default function AlertSettingsScreen() {
  const { data: user } = useMe();

  return (
    <Screen scroll>
      <Header title="Avisos" />

      <PushBlock />
      {user ? <AlertToggles initial={user.alert_settings} /> : <ActivityIndicator color={colors.accent} style={styles.status} />}
    </Screen>
  );
}

const PUSH_TEXT: Record<PushStatus, string> = {
  granted: 'Ligadas: os avisos chegam neste celular mesmo com o app fechado.',
  undetermined: 'Desligadas. Ative para receber os avisos mesmo com o app fechado.',
  denied: 'Bloqueadas nas configurações do celular. Para receber os avisos, libere as notificações da Caderneta lá.',
  unavailable: 'Este aparelho não recebe notificações (num emulador, por exemplo).',
};

/** O estado das notificações neste celular, com o atalho certo para cada caso. */
function PushBlock() {
  const [status, setStatus] = useState<PushStatus | null>(null);
  const [busy, setBusy] = useState(false);

  // Confere ao abrir e ao voltar para o app (a pessoa pode ter mudado nas configurações do celular).
  useEffect(() => {
    const check = () => {
      pushStatus()
        .then(setStatus)
        .catch(() => setStatus('unavailable'));
    };

    check();
    const subscription = AppState.addEventListener('change', (state) => state === 'active' && check());

    return () => subscription.remove();
  }, []);

  async function enable() {
    setBusy(true);

    try {
      await enablePush();
    } catch (error) {
      Alert.alert('Não deu para ativar', error instanceof Error ? error.message : 'Tente de novo.');
    } finally {
      setBusy(false);
      setStatus(await pushStatus());
    }
  }

  if (status === null) {
    return null;
  }

  return (
    <View style={styles.push}>
      <Text variant="strong">Notificações no celular</Text>
      <Text variant="caption" tone="muted" style={styles.pushText}>
        {PUSH_TEXT[status]}
      </Text>
      {status === 'undetermined' ? (
        <Button
          title="Ativar notificações"
          icon={<Bell size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={busy}
          onPress={enable}
          style={styles.pushButton}
        />
      ) : status === 'denied' ? (
        <Button title="Abrir configurações do celular" variant="secondary" onPress={() => Linking.openSettings()} style={styles.pushButton} />
      ) : null}
    </View>
  );
}

function AlertToggles({ initial }: { initial: AlertSettings }) {
  // A chave muda na hora; se a API recusar, volta como estava.
  const [settings, setSettings] = useState(initial);

  const save = useMutation({
    mutationFn: ({ kind, value }: Toggle) => updateAlertSettings({ [kind]: value }),
    onError: (error, { kind, value }) => {
      setSettings((current) => ({ ...current, [kind]: !value }));
      Alert.alert('Não deu para salvar', error.message);
    },
  });

  const toggle = (kind: keyof AlertSettings, value: boolean) => {
    setSettings((current) => ({ ...current, [kind]: value }));
    save.mutate({ kind, value });
  };

  return (
    <>
      <Text tone="muted" style={styles.intro}>
        Os avisos chegam como mensagens no Chat, com um número na aba, e no celular, com as notificações ligadas.
      </Text>

      {/* Uma opção por linha, direto no papel, com a pauta entre elas. */}
      <View style={styles.list}>
        {OPTIONS.map((option) => (
          <View key={option.kind} style={styles.option}>
            <ToggleRow
              label={option.label}
              description={option.description}
              value={settings[option.kind]}
              onChange={(value) => toggle(option.kind, value)}
            />
          </View>
        ))}
      </View>

      <Text variant="caption" tone="muted" style={styles.note}>
        Os lembretes sempre avisam na hora marcada: eles existem para isso.
      </Text>
    </>
  );
}

type Toggle = { kind: keyof AlertSettings; value: boolean };

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  push: { marginTop: space.sm, paddingBottom: space.lg, borderBottomWidth: 1, borderBottomColor: colors.line },
  pushText: { marginTop: space.xs },
  pushButton: { marginTop: space.md },
  intro: { marginTop: space.lg },
  list: { marginTop: space.lg, borderTopWidth: 1, borderTopColor: colors.line },
  option: { paddingVertical: space.md, borderBottomWidth: 1, borderBottomColor: colors.line },
  note: { marginTop: space.lg },
});
