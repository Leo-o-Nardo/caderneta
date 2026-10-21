import { router } from 'expo-router';
import { Check } from 'lucide-react-native';
import { StyleSheet, useWindowDimensions, View } from 'react-native';

import { Wordmark } from '@/components/AuthHeader';
import { FichaHeader, FichaItem } from '@/components/cards/Ficha';
import { CategoryIcon } from '@/components/CategoryIcon';
import { Amount, Button, Card, Form, Screen, Text } from '@/components/ui';
import { pigment } from '@/lib/categories';
import { fitFontSize } from '@/lib/fit';
import { colors, radius, space } from '@/theme/tokens';

// A largura de "Sua casa em ordem." (a linha mais longa) a cada ponto de tamanho, medida na Fraunces
// SemiBold, com 3% de folga: a medida não conta o kerning.
const HEADLINE_PER_POINT = 9.45;
// O tamanho Display do guia: onde a linha cabe, o título fica nele.
const HEADLINE_MAX = 40;

/**
 * A capa (guia 1.4): a primeira tela sem login. O nome, o slogan e um exemplo de uso de verdade
 * (uma fala e a ficha que o app devolve), com "Criar conta" e "Já tenho conta" presos embaixo.
 */
export default function WelcomeScreen() {
  const { width, fontScale } = useWindowDimensions();
  // Nos celulares estreitos, o slogan diminui só o necessário para cada frase caber numa linha.
  const headlineSize = fitFontSize(width - space.xl * 2, HEADLINE_PER_POINT, HEADLINE_MAX, fontScale);

  return (
    <Screen>
      <Form
        submit={
          <>
            <Button title="Criar conta" onPress={() => router.push('/register')} />
            <Button title="Já tenho conta" variant="secondary" onPress={() => router.push('/login')} />
          </>
        }
      >
        <View style={styles.top}>
          <Wordmark />
          {/* A quebra depois de "ordem." deixa as duas frases inteiras, em vez de partir em "ordem. Sem". */}
          <Text variant="display" style={[styles.headline, { fontSize: headlineSize, lineHeight: Math.round(headlineSize * 1.1) }]}>
            {'Sua casa em ordem.\nSem complicação.'}
          </Text>
          <Text tone="muted">
            Registre seus gastos, acompanhe suas receitas e despesas e mantenha as contas da sua casa organizadas de forma prática e intuitiva.
          </Text>
        </View>

        <Example />
      </Form>
    </Screen>
  );
}

/** O exemplo da capa: a fala da pessoa, a ficha "Lançado" e a frase do orçamento, como no Chat. */
function Example() {
  return (
    <View
      style={styles.example}
      accessible
      accessibilityLabel="Exemplo: você fala “Gastei 42 no almoço, no débito”, e o app lança o almoço em Alimentação, R$ 42,00, e diz quanto resta no orçamento."
    >
      <View style={styles.bubble}>
        <Text>Gastei 42 no almoço, no débito</Text>
      </View>
      <Card>
        <FichaHeader icon={Check} title="Lançado" tone="accent" />
        <FichaItem
          icon={<CategoryIcon icon="utensils" color={pigment('Açafrão')} />}
          title="Almoço"
          detail="Alimentação · Débito · hoje"
          value={<Amount cents={4200} />}
        />
      </Card>
      <Text tone="muted">Restam R$ 115,70 em Alimentação este mês.</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  top: { paddingTop: space.lg },
  headline: { marginTop: space.xxl, marginBottom: space.lg },
  // Encosta no rodapé: em tela alta, sobra papel entre a frase e o exemplo.
  example: { marginTop: 'auto', gap: space.md, paddingTop: space.xl },
  // O balão da pessoa, igual ao do Chat: Folha com a pauta em volta e o canto reto do lado dela.
  bubble: {
    alignSelf: 'flex-end',
    maxWidth: '86%',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.lg,
    borderBottomRightRadius: radius.xs,
    paddingHorizontal: space.md,
    paddingVertical: space.sm,
  },
});
