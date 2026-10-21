import { router } from 'expo-router';
import { ChevronRight } from 'lucide-react-native';
import { Pressable, StyleSheet, View } from 'react-native';

import { useOnboarding } from '@/api/onboarding';
import { ProgressBar, Text } from '@/components/ui';
import { lowerFirst, nextStep, progressPercent, showsHomeBlock, STEP_COPY } from '@/lib/onboarding';
import { colors, space } from '@/theme/tokens';

/**
 * A linha dos primeiros passos no Início (guia 1.7): o traço Musgo, a contagem, a barra e o próximo
 * passo, no lugar do aviso de atraso. Some quando tudo está feito ou ignorado, ou se a pessoa
 * escondeu; os passos continuam em Configurações.
 */
export function OnboardingBlock() {
  const { data } = useOnboarding();

  if (!data || !showsHomeBlock(data)) {
    return null;
  }

  const next = nextStep(data);
  const title = `Primeiros passos · ${data.done} de ${data.total}`;
  const caption = next ? `Próximo: ${lowerFirst(STEP_COPY[next.key].title)}` : null;

  return (
    <Pressable
      onPress={() => router.push('/onboarding')}
      style={({ pressed }) => [styles.block, pressed && styles.pressed]}
      accessibilityRole="button"
      accessibilityLabel={caption ? `${title}. ${caption}` : title}
    >
      <View style={styles.texts}>
        <Text variant="strong">{title}</Text>
        <View style={styles.bar}>
          <ProgressBar percent={progressPercent(data)} />
        </View>
        {caption ? (
          <Text variant="caption" tone="muted">
            {caption}
          </Text>
        ) : null}
      </View>
      <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  block: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    marginTop: space.lg,
    paddingVertical: space.sm,
    paddingLeft: space.md,
    borderLeftWidth: 2,
    borderLeftColor: colors.accent,
  },
  pressed: { backgroundColor: colors.pressed },
  texts: { flex: 1, gap: space.xs },
  bar: { paddingVertical: space.xs },
});
