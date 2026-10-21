import { router } from 'expo-router';
import { ChevronLeft, X } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { colors, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  title: string;
  // "back" para telas empilhadas (seta), "close" para as que abrem por cima (X).
  leading?: 'back' | 'close';
  right?: ReactNode;
  // Para quando não há tela atrás (o convite aberto direto pelo link).
  onLeadingPress?: () => void;
};

/** O topo das telas fora das abas: a seta fina (ou o X), o título em Fraunces e uma ação à direita. */
export function Header({ title, leading = 'back', right, onLeadingPress }: Props) {
  const Icon = leading === 'close' ? X : ChevronLeft;

  return (
    <View style={styles.container}>
      <Pressable
        onPress={onLeadingPress ?? (() => router.back())}
        hitSlop={10}
        accessibilityRole="button"
        accessibilityLabel={leading === 'close' ? 'Fechar' : 'Voltar'}
      >
        <Icon size={24} color={colors.ink} strokeWidth={1.6} />
      </Pressable>
      <Text variant="subtitle" accessibilityRole="header" style={styles.title} numberOfLines={1}>
        {title}
      </Text>
      <View style={styles.right}>{right}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flexDirection: 'row', alignItems: 'center', gap: space.md, paddingVertical: space.md },
  title: { flex: 1 },
  right: { minWidth: 24, alignItems: 'flex-end' },
});
