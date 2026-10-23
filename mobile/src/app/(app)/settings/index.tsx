import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  ArrowLeftRight,
  Bell,
  ChevronRight,
  Compass,
  KeyRound,
  ListChecks,
  LogOut,
  MonitorSmartphone,
  Shapes,
  ShieldCheck,
  Target,
  Ticket,
  UserX,
  Users,
} from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Alert, Linking, Pressable, StyleSheet, View } from 'react-native';

import { signOut, signOutEverywhere, useMe } from '@/api/auth';
import { useBudgets } from '@/api/budgets';
import { useHouseholds } from '@/api/households';
import { useOnboarding } from '@/api/onboarding';
import { SettingsRow } from '@/components/SettingsRow';
import { useTourEntry } from '@/components/tour/useTourEntry';
import { Header, Monogram, Screen, Text } from '@/components/ui';
import { siteUrl } from '@/lib/api';
import { toMonth } from '@/lib/dates';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

/**
 * As Configurações: os primeiros passos, a conta, a casa atual e as outras casas, categorias,
 * orçamentos e avisos, sair e excluir a conta.
 */
export default function SettingsScreen() {
  const { data: user } = useMe();
  const households = useHouseholds();
  const budgets = useBudgets(toMonth(new Date()));
  const onboarding = useOnboarding();
  const tour = useTourEntry();
  const current = households.data?.find((household) => household.is_current);

  // O valor atual de cada linha, como no guia: "2 pessoas", "4 categorias", "4 de 4 ligados".
  const people = current ? (current.members_count === 1 ? '1 pessoa' : `${current.members_count} pessoas`) : undefined;
  const budgetCount = budgets.data?.length ?? 0;
  const alerts = user ? Object.values(user.alert_settings) : [];

  const everywhere = useMutation({
    mutationFn: signOutEverywhere,
    onError: (error) => Alert.alert('Não deu para sair', error.message),
  });

  async function confirmSignOut() {
    const confirmed = await confirm({
      title: 'Sair da conta?',
      message: 'Você sai só neste aparelho. Tudo continua guardado: para voltar, é só entrar de novo.',
      confirmLabel: 'Sair',
      destructive: true,
    });

    if (confirmed) {
      signOut();
    }
  }

  async function confirmSignOutEverywhere() {
    const confirmed = await confirm({
      title: 'Sair de todos os aparelhos?',
      message: 'A conta é desconectada deste e de todos os outros celulares. Para voltar, é só entrar de novo.',
      confirmLabel: 'Sair de todos',
      destructive: true,
    });

    if (confirmed) {
      everywhere.mutate();
    }
  }

  return (
    <Screen scroll>
      <Header title="Configurações" />

      {user ? (
        <Pressable
          onPress={() => router.push('/settings/profile')}
          style={({ pressed }) => [styles.profile, pressed && styles.pressed]}
          accessibilityRole="button"
          accessibilityLabel={`Perfil: ${user.name}, ${user.email}`}
        >
          <Monogram name={user.name} />
          <View style={styles.texts}>
            <Text variant="strong" numberOfLines={1}>
              {user.name}
            </Text>
            <Text variant="caption" tone="muted" numberOfLines={1}>
              {user.email}
            </Text>
          </View>
          <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
        </Pressable>
      ) : null}

      {/* Os passos ficam aqui mesmo depois de escondidos do Início. O "Só leitura" não tem passos, só o tour. */}
      <MenuGroup title="Primeiros passos">
        {onboarding.data && onboarding.data.total > 0 ? (
          <SettingsRow
            icon={ListChecks}
            label="Primeiros passos"
            detail={`${onboarding.data.done} de ${onboarding.data.total}`}
            onPress={() => router.push('/onboarding')}
          />
        ) : null}
        <SettingsRow icon={Compass} label={tour.label} detail={tour.detail} onPress={tour.start} />
      </MenuGroup>

      <MenuGroup title="Casa">
        <SettingsRow icon={Users} label="Membros e convites" detail={people} onPress={() => router.push('/settings/household')} />
        <SettingsRow icon={ArrowLeftRight} label="Suas casas" detail={current?.name} onPress={() => router.push('/settings/households')} />
        <SettingsRow icon={Ticket} label="Entrar com um código" onPress={() => router.push('/settings/join')} />
      </MenuGroup>

      <MenuGroup title="Finanças">
        <SettingsRow icon={Shapes} label="Categorias" onPress={() => router.push('/categories')} />
        <SettingsRow
          icon={Target}
          label="Orçamentos"
          detail={budgetCount > 0 ? (budgetCount === 1 ? '1 categoria' : `${budgetCount} categorias`) : undefined}
          onPress={() => router.push('/budgets')}
        />
        <SettingsRow
          icon={Bell}
          label="Avisos"
          detail={user ? `${alerts.filter(Boolean).length} de ${alerts.length} ligados` : undefined}
          onPress={() => router.push('/settings/alerts')}
        />
      </MenuGroup>

      <MenuGroup title="Conta">
        <SettingsRow icon={KeyRound} label="Trocar senha" onPress={() => router.push('/settings/password')} />
        <SettingsRow icon={ShieldCheck} label="Política de privacidade" onPress={() => Linking.openURL(siteUrl('/privacidade'))} />
        <SettingsRow icon={LogOut} label="Sair" tone="danger" onPress={confirmSignOut} />
        <SettingsRow
          icon={MonitorSmartphone}
          label="Sair de todos os aparelhos"
          tone="danger"
          loading={everywhere.isPending}
          onPress={confirmSignOutEverywhere}
        />
        <SettingsRow icon={UserX} label="Excluir conta" tone="danger" onPress={() => router.push('/settings/delete-account')} />
      </MenuGroup>
    </Screen>
  );
}

/** Um grupo do menu: o nome em caixa alta com o traço de Tinta embaixo, e as linhas. */
function MenuGroup({ title, children }: { title: string; children: ReactNode }) {
  return (
    <View style={styles.group}>
      <Text variant="overline" tone="muted" accessibilityRole="header" style={styles.groupTitle}>
        {title}
      </Text>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  profile: { flexDirection: 'row', alignItems: 'center', gap: space.lg, paddingTop: space.lg, paddingBottom: space.xl },
  pressed: { backgroundColor: colors.pressed },
  texts: { flex: 1, gap: space.xs },
  group: { marginBottom: space.xl },
  groupTitle: { paddingBottom: space.sm, borderBottomWidth: 1, borderBottomColor: colors.ink },
});
