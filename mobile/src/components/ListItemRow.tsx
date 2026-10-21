import { Check, ExternalLink } from 'lucide-react-native';
import { Alert, Linking, Pressable, StyleSheet, View } from 'react-native';

import type { ListItem, ListKind } from '@/api/types';
import { Amount, Text } from '@/components/ui';
import { shortDate } from '@/lib/dates';
import { colors, radius, space } from '@/theme/tokens';

type Props = {
  item: ListItem;
  kind: ListKind;
  onToggle: () => void;
  onEdit: () => void;
};

/**
 * Um item com área de toque grande (a linha inteira): no mercado dá para marcar com o polegar,
 * sem mirar. Segurar abre a edição. A caixa de marcar tem o contorno da Borda de campo e, marcada,
 * fica preenchida de Musgo; o item pego fica riscado, em Lápis.
 */
export function ListItemRow({ item, kind, onToggle, onEdit }: Props) {
  const details = [
    item.quantity,
    item.due_on ? `até ${shortDate(item.due_on)}` : null,
    item.note,
    item.is_checked && item.checked_by ? `pego por ${item.checked_by.name}` : null,
  ]
    .filter(Boolean)
    .join(' · ');

  return (
    <Pressable
      onPress={onToggle}
      onLongPress={onEdit}
      delayLongPress={350}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole="checkbox"
      accessibilityState={{ checked: item.is_checked }}
      accessibilityHint="Toque para marcar. Segure para editar."
    >
      <View style={[styles.box, item.is_checked && styles.boxChecked]}>
        {item.is_checked ? <Check size={16} color={colors.canvas} strokeWidth={2} /> : null}
      </View>

      <View style={styles.texts}>
        <Text tone={item.is_checked ? 'faint' : 'ink'} style={item.is_checked ? styles.checked : undefined}>
          {item.text}
        </Text>
        {details ? (
          <Text variant="caption" tone="muted" numberOfLines={1}>
            {details}
          </Text>
        ) : null}
      </View>

      {kind === 'wishlist' && item.price_cents ? (
        <Amount cents={item.price_cents} tone={item.is_checked ? 'faint' : 'muted'} />
      ) : null}

      {item.url ? <LinkButton url={item.url} /> : null}
    </Pressable>
  );
}

/**
 * Abre o link do item (a loja do produto, a receita…) no navegador ou no app dele. O toque aqui não
 * marca o item: o botão de dentro recebe o toque antes da linha.
 */
function LinkButton({ url }: { url: string }) {
  const open = () => Linking.openURL(url).catch(() => Alert.alert('Não deu para abrir o link', url));

  return (
    <Pressable
      onPress={open}
      style={({ pressed }) => [styles.link, pressed && styles.linkPressed]}
      accessibilityRole="link"
      accessibilityLabel="Abrir link"
    >
      <ExternalLink size={20} color={colors.accent} strokeWidth={1.6} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    minHeight: 56,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  pressed: { backgroundColor: colors.pressed },
  box: {
    width: 24,
    height: 24,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  boxChecked: { backgroundColor: colors.accent, borderColor: colors.accent },
  texts: { flex: 1, gap: space.xs },
  checked: { textDecorationLine: 'line-through' },
  // 44 de área de toque, como os outros botões de ícone.
  link: { width: 44, height: 44, borderRadius: radius.md, alignItems: 'center', justifyContent: 'center' },
  linkPressed: { backgroundColor: colors.surface },
});
