import { StyleSheet, View } from 'react-native';

import { initials } from '@/lib/households';
import { colors, font, radius } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  name: string;
  // md: o perfil das Configurações e o membro aberto; sm: as linhas da lista de membros.
  size?: 'md' | 'sm';
};

/**
 * As iniciais da pessoa em Fraunces, num círculo de contorno Tinta (guia, parte 06: menu). Um dos
 * três círculos do app, com o microfone e o interruptor.
 */
export function Monogram({ name, size = 'md' }: Props) {
  return (
    <View style={[styles.circle, size === 'sm' && styles.small]} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
      <Text variant={size === 'md' ? 'subtitle' : 'strong'} style={size === 'sm' && styles.smallText}>
        {initials(name)}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  circle: {
    width: 48,
    height: 48,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  small: { width: 36, height: 36 },
  smallText: { fontFamily: font.serifMedium },
});
