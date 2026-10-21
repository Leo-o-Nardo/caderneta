import type { ReactNode } from 'react';
import { Modal, Pressable, StyleSheet, View } from 'react-native';
import Animated, { Easing, FadeIn, SlideInDown } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, radius, space, withAlpha } from '@/theme/tokens';

// A curva do guia: sobe rápido e desacelera no fim, sem quicar.
const EASING = Easing.bezier(0.2, 0.7, 0.2, 1);

type Props = {
  visible: boolean;
  onClose: () => void;
  children: ReactNode;
  // O nome do toque fora da folha para o leitor de tela ("Cancelar", "Fechar").
  closeLabel?: string;
};

/**
 * A folha que sobe de baixo, perto do polegar (guia, partes 06 e 07): 240 ms para subir, 180 ms
 * para o fundo escurecer. Tocar fora ou no "voltar" do Android fecha. É a base da confirmação
 * (SheetHost) e da folha de categorias.
 */
export function BottomSheet({ visible, onClose, children, closeLabel = 'Fechar' }: Props) {
  const insets = useSafeAreaInsets();

  return (
    <Modal transparent visible={visible} animationType="none" statusBarTranslucent navigationBarTranslucent onRequestClose={onClose}>
      {visible ? (
        <View style={styles.root}>
          <Animated.View entering={FadeIn.duration(180)} style={styles.scrim}>
            <Pressable style={StyleSheet.absoluteFill} onPress={onClose} accessibilityRole="button" accessibilityLabel={closeLabel} />
          </Animated.View>

          <Animated.View
            entering={SlideInDown.duration(240).easing(EASING)}
            style={[styles.sheet, { paddingBottom: space.xl + insets.bottom }]}
            accessibilityViewIsModal
          >
            <View style={styles.handle} />
            {children}
          </Animated.View>
        </View>
      ) : null}
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, justifyContent: 'flex-end' },
  scrim: { ...StyleSheet.absoluteFill, backgroundColor: withAlpha(colors.ink, 0.42) },
  sheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: radius.xl,
    borderTopRightRadius: radius.xl,
    paddingHorizontal: space.xl,
    paddingTop: space.sm,
    // A única sombra do app: a folha fica por cima da tela.
    boxShadow: `0 -12px 32px -16px ${withAlpha(colors.ink, 0.45)}`,
  },
  handle: {
    alignSelf: 'center',
    width: 36,
    height: 4,
    borderRadius: radius.xs,
    backgroundColor: colors.line,
    marginBottom: space.lg,
  },
});
