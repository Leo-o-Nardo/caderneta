import { router } from 'expo-router';
import { ChevronRight, Compass, CreditCard, House, ListChecks, Repeat, Target, TrendingUp, Users, type LucideIcon } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { updateOnboarding, useOnboarding } from '@/api/onboarding';
import { AuthHeader } from '@/components/AuthHeader';
import { useTourEntry } from '@/components/tour/useTourEntry';
import { Button, Form, Screen, Text } from '@/components/ui';
import { UsageExample } from '@/components/UsageExample';
import { colors, space } from '@/theme/tokens';

type Page = 'proposal' | 'features' | 'start';

// O que mais o app faz, uma linha por recurso (tela 2 das boas-vindas).
const FEATURES: { icon: LucideIcon; title: string; caption: string }[] = [
  { icon: CreditCard, title: 'Cartões e faturas', caption: 'cada compra cai na fatura certa' },
  { icon: Repeat, title: 'Contas fixas', caption: 'aviso na véspera e no dia' },
  { icon: TrendingUp, title: 'Receitas fixas', caption: 'o salário entra sozinho' },
  { icon: Target, title: 'Orçamentos', caption: 'aviso aos 80% e ao passar' },
  { icon: ListChecks, title: 'Listas e lembretes', caption: 'o mercado e o que não pode esquecer' },
  { icon: Users, title: 'Para a casa toda', caption: 'cada pessoa com o seu login' },
];

/**
 * As boas-vindas (guia 1.7): na primeira entrada em cada casa, três telas curtas com "Pular" em
 * todas. Quem chega por convite encontra a casa montada e vê duas. Nada é obrigatório: pular ou
 * escolher um caminho marca como visto, e elas não aparecem de novo.
 */
export default function OnboardingWelcomeScreen() {
  const { data: onboarding } = useOnboarding();
  const joined = onboarding?.joined ?? false;
  // O "Só leitura" não tem o que cadastrar: não há primeiros passos para ele.
  const canSetUp = (onboarding?.total ?? 0) > 0;
  const pages: Page[] = joined ? ['proposal', 'start'] : ['proposal', 'features', 'start'];
  const [index, setIndex] = useState(0);
  const page = pages[index];
  const tour = useTourEntry();

  // Marca como visto sem esperar a resposta: se falhar, as boas-vindas só voltam na próxima abertura.
  function finish(then: () => void) {
    updateOnboarding({ welcome_seen: true }).catch(() => undefined);
    then();
  }

  const close = () => finish(() => router.back());
  const next = () => setIndex(index + 1);
  const header = {
    step: { label: 'Boas-vindas', current: index + 1, total: pages.length },
    onBack: index > 0 ? () => setIndex(index - 1) : null,
    action:
      page === 'start' ? undefined : (
        <Pressable onPress={close} hitSlop={12} accessibilityRole="button" accessibilityLabel="Pular as boas-vindas">
          <Text variant="label" tone="muted">
            Pular
          </Text>
        </Pressable>
      ),
  };

  if (page === 'proposal') {
    return (
      <Screen>
        <Form submit={<Button title="Continuar" onPress={next} />}>
          <AuthHeader
            {...header}
            title="Fale o que gastou."
            subtitle="O Caderneta entende, monta a ficha com os lançamentos e só grava depois que você confere."
          />
          <UsageExample style={styles.example} />
        </Form>
      </Screen>
    );
  }

  if (page === 'features') {
    return (
      <Screen>
        <Form submit={<Button title="Continuar" onPress={next} />}>
          <AuthHeader
            {...header}
            title="A casa toda num lugar."
            subtitle="Além de lançar, o app acompanha o que se repete e avisa antes de vencer."
          />
          {FEATURES.map((feature, position) => (
            <View key={feature.title} style={[styles.row, position === FEATURES.length - 1 && styles.last]}>
              <feature.icon size={20} color={colors.muted} strokeWidth={1.6} />
              <View style={styles.texts}>
                <Text variant="strong">{feature.title}</Text>
                <Text variant="caption" tone="muted">
                  {feature.caption}
                </Text>
              </View>
            </View>
          ))}
        </Form>
      </Screen>
    );
  }

  const subtitle = !joined
    ? 'Nada é obrigatório: as categorias já estão prontas e dá para lançar agora mesmo.'
    : canSetUp
      ? 'Quem convidou você já deixou as categorias e as listas montadas. Falta só o que é seu.'
      : 'Você entrou para acompanhar: dá para ver os gastos, o resumo e a agenda da casa.';

  return (
    <Screen scroll>
      <AuthHeader {...header} title={joined ? 'A casa já está pronta.' : 'Por onde começar?'} subtitle={subtitle} />

      {canSetUp ? (
        <Choice
          icon={ListChecks}
          title="Configurar o essencial"
          caption={joined ? 'seus cartões e o que entra todo mês' : 'cartões, salário e contas fixas · uns 3 minutos'}
          onPress={() => finish(() => router.replace('/onboarding'))}
        />
      ) : null}
      <Choice
        icon={Compass}
        title="Conhecer o app"
        caption="um tour de 1 minuto pelas telas"
        onPress={() => finish(tour.start)}
      />
      <Choice
        icon={House}
        title="Explorar sozinho"
        caption={canSetUp ? 'os primeiros passos ficam no Início' : 'é só tocar nas abas lá embaixo'}
        onPress={close}
      />

      {canSetUp ? (
        <Text variant="caption" tone="muted" style={styles.note}>
          Tudo isto fica em Configurações › Primeiros passos.
        </Text>
      ) : null}
    </Screen>
  );
}

type ChoiceProps = {
  icon: LucideIcon;
  title: string;
  caption: string;
  onPress: () => void;
};

/** Um caminho da última tela: uma linha com divisória, e o toque já leva ao destino. */
function Choice({ icon: Icon, title, caption, onPress }: ChoiceProps) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole="button"
      accessibilityLabel={`${title}: ${caption}`}
    >
      <Icon size={20} color={colors.muted} strokeWidth={1.6} />
      <View style={styles.texts}>
        <Text variant="strong">{title}</Text>
        <Text variant="caption" tone="muted">
          {caption}
        </Text>
      </View>
      <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  // Encosta no rodapé, como na capa: em tela alta, sobra papel entre a frase e o exemplo.
  example: { marginTop: 'auto', paddingTop: space.xl },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  last: { borderBottomWidth: 0 },
  pressed: { backgroundColor: colors.pressed },
  texts: { flex: 1, gap: space.xs },
  note: { marginTop: space.lg },
});
