import { ArrowUp } from 'lucide-react-native';
import { ActivityIndicator, Pressable, StyleSheet, TextInput, View } from 'react-native';

import { colors, font, radius, space } from '@/theme/tokens';

type Props = {
  value: string;
  onChange: (text: string) => void;
  onSubmit: () => void;
  placeholder: string;
  loading?: boolean;
};

/**
 * O campo de adicionar itens, no mesmo jeito do campo do Chat: contorno da Borda de campo e o botão
 * quadrado em Tinta. Dá para digitar vários de uma vez ("leite, café e pão").
 */
export function AddItemsBar({ value, onChange, onSubmit, placeholder, loading = false }: Props) {
  const canSend = value.trim() !== '' && !loading;

  return (
    <View style={styles.bar}>
      <View style={styles.input}>
        <TextInput
          value={value}
          onChangeText={onChange}
          onSubmitEditing={() => canSend && onSubmit()}
          placeholder={placeholder}
          placeholderTextColor={colors.faint}
          selectionColor={colors.accent}
          cursorColor={colors.accent}
          returnKeyType="send"
          submitBehavior="submit"
          style={styles.field}
          accessibilityLabel="Adicionar itens"
        />
      </View>
      <Pressable
        onPress={onSubmit}
        disabled={!canSend}
        style={({ pressed }) => [styles.send, !canSend && styles.disabled, pressed && styles.sendPressed]}
        accessibilityRole="button"
        accessibilityLabel="Adicionar"
        accessibilityState={{ disabled: !canSend, busy: loading }}
      >
        {loading ? (
          // Enviando, o botão fica desabilitado (Pauta): o indicador vai em Lápis para aparecer.
          <ActivityIndicator size="small" color={colors.faint} />
        ) : (
          <ArrowUp size={20} color={canSend ? colors.canvas : colors.faint} strokeWidth={2} />
        )}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  input: {
    flex: 1,
    minHeight: 44,
    justifyContent: 'center',
    paddingHorizontal: space.md,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
  },
  field: { color: colors.ink, fontFamily: font.regular, fontSize: 16, paddingVertical: space.sm },
  send: { width: 44, height: 44, borderRadius: radius.md, backgroundColor: colors.ink, alignItems: 'center', justifyContent: 'center' },
  sendPressed: { backgroundColor: colors.inkPressed },
  // Sem texto, o botão fica como os desabilitados do guia: Pauta com o desenho em Lápis.
  disabled: { backgroundColor: colors.line },
});
