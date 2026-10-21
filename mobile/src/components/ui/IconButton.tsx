import type { LucideIcon } from 'lucide-react-native';
import { Pressable, StyleSheet } from 'react-native';

import { colors, radius, type ColorName } from '@/theme/tokens';

type Props = {
  icon: LucideIcon;
  // O nome para o leitor de tela ("Configurações", "Limpar a conversa").
  label: string;
  onPress: () => void;
  tone?: ColorName;
  // No canto da tela: o desenho de 24 encosta na margem, e a área de toque passa dela.
  edge?: boolean;
};

// A área de toque mínima do guia e quanto ela passa do desenho de 24 de cada lado.
const TOUCH = 44;
const INSET = (TOUCH - 24) / 2;

/**
 * O botão que é só o ícone (guia, parte 04): engrenagem, lixeira, +, "…". Desenho de 24 com traço
 * 1,6 e 44 de área de toque, sem empurrar a linha onde está.
 */
export function IconButton({ icon: Icon, label, onPress, tone = 'ink', edge = true }: Props) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.button, edge && styles.edge, pressed && styles.pressed]}
      accessibilityRole="button"
      accessibilityLabel={label}
    >
      <Icon size={24} color={colors[tone]} strokeWidth={1.6} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    width: TOUCH,
    height: TOUCH,
    marginVertical: -INSET,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  edge: { marginRight: -INSET },
  pressed: { backgroundColor: colors.pressed },
});
