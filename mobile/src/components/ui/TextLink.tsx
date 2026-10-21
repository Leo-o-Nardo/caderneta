import { Pressable, StyleSheet } from 'react-native';

import { font } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  label: string;
  onPress: () => void;
  // sm: ao lado do título de uma seção ("Editar", "Ver todos").
  size?: 'md' | 'sm';
};

/** O link do guia: texto em Musgo sublinhado, com 44 de área de toque mesmo sendo baixo. */
export function TextLink({ label, onPress, size = 'md' }: Props) {
  return (
    <Pressable
      onPress={onPress}
      hitSlop={12}
      accessibilityRole="link"
      style={({ pressed }) => pressed && styles.pressed}
    >
      <Text variant="label" tone="accent" style={[styles.text, size === 'md' && styles.medium]}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  text: { textDecorationLine: 'underline' },
  medium: { fontFamily: font.medium, fontSize: 15 },
  pressed: { opacity: 0.7 },
});
