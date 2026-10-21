import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Check, HousePlus } from 'lucide-react-native';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';

import { resetAfterHouseholdChange, switchHousehold, useHouseholds } from '@/api/households';
import { Button, Header, Screen, Text } from '@/components/ui';
import { ROLES } from '@/lib/households';
import { colors, space } from '@/theme/tokens';

/**
 * Todas as casas da pessoa (a dela e as que entrou por convite). Tocar numa troca a casa atual: o
 * app inteiro passa a mostrar os dados dela.
 */
export default function HouseholdsScreen() {
  const households = useHouseholds();

  const select = useMutation({
    mutationFn: switchHousehold,
    onSuccess: () => {
      router.back();
      resetAfterHouseholdChange();
    },
    onError: (error) => Alert.alert('Não deu para trocar', error.message),
  });

  return (
    <Screen scroll>
      <Header title="Suas casas" leading="close" />

      {households.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : households.isError ? (
        <Text tone="danger" style={styles.status}>
          {households.error.message}
        </Text>
      ) : (
        <View style={styles.list}>
          {households.data.map((household) => {
            const people = household.members_count === 1 ? '1 pessoa' : `${household.members_count} pessoas`;
            const switching = select.isPending && select.variables === household.id;

            return (
              <Pressable
                key={household.id}
                onPress={() => select.mutate(household.id)}
                disabled={household.is_current || select.isPending}
                style={({ pressed }) => [styles.row, pressed && styles.pressed]}
                accessibilityRole="radio"
                accessibilityState={{ checked: household.is_current }}
              >
                <View style={styles.texts}>
                  <Text variant="strong" tone={household.is_current ? 'accent' : 'ink'}>
                    {household.name}
                  </Text>
                  <Text variant="caption" tone="muted">
                    {[ROLES[household.role].label, people, household.is_current ? 'aberta agora' : null].filter(Boolean).join(' · ')}
                  </Text>
                </View>
                {switching ? (
                  <ActivityIndicator size="small" color={colors.accent} />
                ) : household.is_current ? (
                  <Check size={20} color={colors.accent} strokeWidth={1.6} />
                ) : null}
              </Pressable>
            );
          })}

          <Text variant="caption" tone="muted" style={styles.note}>
            Cada casa tem os próprios lançamentos, cartões, contas e listas. Por exemplo: uma para a família e outra para
            dividir com quem mora com você.
          </Text>

          <Button
            title="Criar outra casa"
            variant="secondary"
            icon={<HousePlus size={18} color={colors.ink} strokeWidth={1.6} />}
            onPress={() => router.push({ pathname: '/settings/household-form', params: { mode: 'create' } })}
            style={styles.create}
          />
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  // A pauta em cima da primeira casa; as outras têm a de baixo de cada linha.
  list: { marginTop: space.sm, borderTopWidth: 1, borderTopColor: colors.line },
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
  texts: { flex: 1, gap: space.xs },
  note: { marginTop: space.lg },
  create: { marginTop: space.xl },
});
