import { useMutation } from '@tanstack/react-query';
import { router, useFocusEffect } from 'expo-router';
import { Check, ChevronRight, Compass } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';

import { updateOnboarding, useOnboarding } from '@/api/onboarding';
import type { OnboardingChange, OnboardingStep, OnboardingStepKey } from '@/api/types';
import { SettingsRow } from '@/components/SettingsRow';
import { useTourEntry } from '@/components/tour/useTourEntry';
import { BottomSheet, Button, Header, ProgressBar, Screen, Text } from '@/components/ui';
import { progressLabel, progressPercent, STEP_COPY } from '@/lib/onboarding';
import { colors, radius, space } from '@/theme/tokens';

/**
 * Os primeiros passos (guia 1.7): o que deixa o app com a cara da casa, nada obrigatório. "Feito"
 * vem dos cadastros de verdade; tocar num passo abre a folha, que explica e leva à tela de cadastro
 * que já existe. Ao voltar dela, a lista é buscada de novo.
 */
export default function OnboardingScreen() {
  const onboarding = useOnboarding();
  const [open, setOpen] = useState<OnboardingStepKey | null>(null);
  const tour = useTourEntry();
  const { refetch } = onboarding;

  useFocusEffect(
    useCallback(() => {
      refetch();
    }, [refetch]),
  );

  const change = useMutation({
    mutationFn: (value: OnboardingChange) => updateOnboarding(value),
    onError: (error) => Alert.alert('Não deu para salvar', error.message),
  });

  if (onboarding.isPending) {
    return (
      <Screen>
        <Header title="Primeiros passos" />
        <ActivityIndicator color={colors.accent} style={styles.status} />
      </Screen>
    );
  }

  if (onboarding.isError) {
    return (
      <Screen>
        <Header title="Primeiros passos" />
        <View style={styles.status}>
          <Text tone="danger">{onboarding.error.message}</Text>
          <Button title="Tentar de novo" variant="ghost" onPress={() => refetch()} style={styles.left} />
        </View>
      </Screen>
    );
  }

  const state = onboarding.data;
  const allSet = state.total > 0 && state.steps.every((step) => step.status !== 'pending');
  const step = open ? state.steps.find((item) => item.key === open) : undefined;
  const copy = open ? STEP_COPY[open] : undefined;
  const secondary = copy?.secondary;

  function go(route: (typeof STEP_COPY)[OnboardingStepKey]['route']) {
    setOpen(null);
    router.push(route);
  }

  function skip(key: OnboardingStepKey) {
    setOpen(null);
    change.mutate({ skip: key });
  }

  return (
    <Screen scroll>
      <Header title="Primeiros passos" />

      <Text tone="muted" style={styles.intro}>
        {allSet ? 'Tudo pronto. O app já está com a cara da sua casa.' : 'Nada aqui é obrigatório. Cada passo deixa o app mais com a cara da sua casa.'}
      </Text>

      {state.total > 0 ? (
        <>
          <View style={styles.progress} accessible accessibilityLabel={progressLabel(state)}>
            <Text variant="caption" tone="muted">
              {progressLabel(state)}
            </Text>
            <ProgressBar percent={progressPercent(state)} />
          </View>

          <View style={styles.list}>
            {state.steps.map((item) => (
              <StepRow
                key={item.key}
                step={item}
                onPress={() => setOpen(item.key)}
                onUnskip={() => change.mutate({ unskip: item.key })}
              />
            ))}
          </View>

          <View style={styles.group}>
            <Text variant="overline" tone="muted" accessibilityRole="header" style={styles.groupTitle}>
              Conhecer o app
            </Text>
            <SettingsRow icon={Compass} label={tour.label} detail={tour.detail} onPress={tour.start} />
          </View>

          <Button
            title={state.hidden ? 'Mostrar no Início' : 'Esconder do Início'}
            variant="ghost"
            onPress={() => change.mutate({ hidden: !state.hidden })}
            style={styles.left}
          />
        </>
      ) : null}

      {/* O passo tocado: o porquê, o caminho para a tela de cadastro e, se der, ignorar. */}
      <BottomSheet visible={step !== undefined} onClose={() => setOpen(null)} closeLabel="Agora não">
        {step && copy ? (
          <>
            <Text variant="subtitle" accessibilityRole="header">
              {copy.title}
            </Text>
            <Text tone="muted" style={styles.explain}>
              {copy.explain}
            </Text>
            <View style={styles.sheetButtons}>
              <Button title={copy.action} onPress={() => go(copy.route)} />
              {secondary ? <Button title={secondary.label} variant="secondary" onPress={() => go(secondary.route)} /> : null}
              {copy.skipLabel && step.status === 'pending' ? (
                <Button title={copy.skipLabel} variant="secondary" onPress={() => skip(step.key)} />
              ) : null}
              <Button title="Agora não" variant="ghost" onPress={() => setOpen(null)} style={styles.center} />
            </View>
          </>
        ) : null}
      </BottomSheet>
    </Screen>
  );
}

type StepRowProps = {
  step: OnboardingStep;
  onPress: () => void;
  onUnskip: () => void;
};

/** Uma linha do checklist: a marca do estado, o passo e o que foi feito (ou o porquê dele). */
function StepRow({ step, onPress, onUnskip }: StepRowProps) {
  const copy = STEP_COPY[step.key];
  const done = step.status === 'done';
  const skipped = step.status === 'skipped';
  const caption = done ? (step.detail ?? 'Feito') : skipped ? 'Ignorado' : copy.hint;
  const state = done ? 'feito' : skipped ? 'ignorado' : 'a fazer';

  return (
    <Pressable
      onPress={onPress}
      disabled={done}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole="button"
      accessibilityLabel={`${copy.title}, ${state}. ${caption}`}
      accessibilityState={{ disabled: done }}
    >
      <StepMark status={step.status} />
      <View style={styles.texts}>
        <Text variant={done || skipped ? 'body' : 'strong'} tone={done || skipped ? 'muted' : 'ink'}>
          {copy.title}
        </Text>
        <Text variant="caption" tone="muted" numberOfLines={1}>
          {caption}
        </Text>
      </View>
      {skipped ? <Button title="Desfazer" variant="ghost" size="sm" onPress={onUnskip} /> : null}
      {step.status === 'pending' ? <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} /> : null}
    </Pressable>
  );
}

/** ✓ em Musgo para feito, o quadrado de Borda da Lista para a fazer e um traço para ignorado. */
function StepMark({ status }: { status: OnboardingStep['status'] }) {
  return (
    <View style={styles.mark}>
      {status === 'done' ? <Check size={22} color={colors.accent} strokeWidth={2} /> : null}
      {status === 'pending' ? <View style={styles.box} /> : null}
      {status === 'skipped' ? <View style={styles.dash} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xl },
  left: { alignSelf: 'flex-start' },
  center: { alignSelf: 'center' },
  intro: { marginTop: space.sm },
  progress: { gap: space.sm, marginTop: space.xl },
  list: { marginTop: space.lg, marginBottom: space.xl },
  // Como os grupos das Configurações: o nome em caixa alta com o traço de Tinta embaixo.
  group: { marginBottom: space.lg },
  groupTitle: { paddingBottom: space.sm, borderBottomWidth: 1, borderBottomColor: colors.ink },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  pressed: { backgroundColor: colors.pressed },
  texts: { flex: 1, gap: space.xs },
  mark: { width: 24, height: 24, alignItems: 'center', justifyContent: 'center' },
  box: { width: 20, height: 20, borderWidth: 1.5, borderColor: colors.border, borderRadius: radius.sm },
  dash: { width: 12, height: 2, borderRadius: radius.xs, backgroundColor: colors.faint },
  explain: { marginTop: space.sm },
  sheetButtons: { gap: space.md, marginTop: space.xl },
});
