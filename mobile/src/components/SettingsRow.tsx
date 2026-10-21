import { ChevronRight, type LucideIcon } from 'lucide-react-native';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

type Props = {
  icon: LucideIcon;
  label: string;
  // O valor atual à direita, em Grafite ("Casa do Leo", "4 de 4 ligados").
  detail?: string;
  onPress: () => void;
  // "danger" para sair: Tijolo e sem a seta (não abre outra tela).
  tone?: 'ink' | 'danger';
  loading?: boolean;
};

/**
 * Uma linha das Configurações (guia, parte 06: menu): o ícone de 20 em Grafite, o nome, o valor
 * atual e a seta fina, direto no papel com a pauta embaixo.
 */
export function SettingsRow({ icon: Icon, label, detail, onPress, tone = 'ink', loading = false }: Props) {
  return (
    <Pressable
      onPress={onPress}
      disabled={loading}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole="button"
      accessibilityLabel={detail ? `${label}, ${detail}` : label}
      accessibilityState={{ busy: loading }}
    >
      <Icon size={20} color={tone === 'danger' ? colors.danger : colors.muted} strokeWidth={1.6} />
      <Text variant="strong" tone={tone} style={detail ? styles.label : styles.grow} numberOfLines={1}>
        {label}
      </Text>
      {detail ? (
        <Text variant="label" tone="muted" style={styles.detail} numberOfLines={1}>
          {detail}
        </Text>
      ) : null}
      <View style={styles.trailing}>
        {loading ? (
          <ActivityIndicator size="small" color={colors.muted} />
        ) : tone === 'ink' ? (
          <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    minHeight: 48,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  pressed: { backgroundColor: colors.pressed },
  label: { flexShrink: 0, maxWidth: '60%' },
  grow: { flex: 1 },
  detail: { flex: 1, textAlign: 'right' },
  trailing: { minWidth: 18, alignItems: 'flex-end' },
});
