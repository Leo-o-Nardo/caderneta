import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { ActivityIndicator, Alert, StyleSheet, View } from 'react-native';

import { updateAlertSettings, useMe } from '@/api/auth';
import type { AlertSettings } from '@/api/types';
import { Header, Screen, Text, ToggleRow } from '@/components/ui';
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

/** Configurações › Avisos: liga e desliga cada tipo de aviso que chega no Chat. Os lembretes sempre avisam. */
export default function AlertSettingsScreen() {
  const { data: user } = useMe();

  return (
    <Screen scroll>
      <Header title="Avisos" />

      {user ? <AlertToggles initial={user.alert_settings} /> : <ActivityIndicator color={colors.accent} style={styles.status} />}
    </Screen>
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
        Os avisos chegam como mensagens no Chat, com um número na aba.
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
  intro: { marginTop: space.sm },
  list: { marginTop: space.lg, borderTopWidth: 1, borderTopColor: colors.line },
  option: { paddingVertical: space.md, borderBottomWidth: 1, borderBottomColor: colors.line },
  note: { marginTop: space.lg },
});
