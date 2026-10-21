import { router } from 'expo-router';
import { ChevronLeft } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui';
import { colors, font, radius, space } from '@/theme/tokens';

type Step = {
  // O nome do caminho ("Criar conta") e onde a pessoa está nele.
  label: string;
  current: number;
  total: number;
};

type Props = {
  title: string;
  subtitle?: string;
  // Num caminho em passos: a barra de progresso e "Criar conta · 1 de 2" em cima do título.
  step?: Step;
  // Voltar: por padrão a tela anterior, ou a capa quando não há nenhuma (vindo do link do convite).
  // null tira a seta (o primeiro passo das boas-vindas não tem para onde voltar).
  onBack?: (() => void) | null;
  // Um botão de texto à direita da seta, como o "Pular" das boas-vindas.
  action?: ReactNode;
};

/** O topo das telas de entrada (guia 1.4): a seta de voltar, o passo, o título em Fraunces e a frase embaixo. */
export function AuthHeader({ title, subtitle, step, onBack, action }: Props) {
  const back = onBack === undefined ? () => (router.canGoBack() ? router.back() : router.replace('/welcome')) : onBack;

  return (
    <View style={styles.container}>
      <View style={styles.top}>
        {back ? (
          <Pressable onPress={back} hitSlop={10} accessibilityRole="button" accessibilityLabel="Voltar">
            <ChevronLeft size={24} color={colors.ink} strokeWidth={1.6} />
          </Pressable>
        ) : (
          <View />
        )}
        {action}
      </View>

      {step ? (
        <View style={styles.step} accessible accessibilityLabel={`${step.label}, passo ${step.current} de ${step.total}`}>
          <View style={styles.progress}>
            {Array.from({ length: step.total }, (_, index) => (
              <View key={index} style={[styles.segment, index < step.current && styles.reached]} />
            ))}
          </View>
          <Text variant="overline" tone="muted">
            {step.label} · {step.current} de {step.total}
          </Text>
        </View>
      ) : null}

      <View style={styles.texts}>
        <Text variant="title" accessibilityRole="header">
          {title}
        </Text>
        {subtitle ? <Text tone="muted">{subtitle}</Text> : null}
      </View>
    </View>
  );
}

/** O nome do app em Fraunces, no topo da capa. Vira o logotipo quando o ícone ficar pronto. */
export function Wordmark() {
  return (
    <Text variant="subtitle" style={styles.wordmark} accessibilityRole="header">
      Caderneta
    </Text>
  );
}

const styles = StyleSheet.create({
  container: { paddingTop: space.md, marginBottom: space.xl },
  // A seta à esquerda e o botão de texto, se houver, à direita; sempre na altura da seta.
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', minHeight: 24 },
  step: { gap: space.sm, marginTop: space.lg },
  progress: { flexDirection: 'row', gap: space.xs },
  segment: { flex: 1, height: 3, borderRadius: radius.xs, backgroundColor: colors.line },
  reached: { backgroundColor: colors.ink },
  texts: { gap: space.sm, marginTop: space.lg },
  wordmark: { fontFamily: font.serif },
});
