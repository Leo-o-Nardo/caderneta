import type { ReactNode } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';

import { colors, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  // Os campos, que rolam. "Apagar" vai no fim deles, longe do botão principal.
  children: ReactNode;
  // O botão principal, preso embaixo. Sem ele (tela só de leitura), não há rodapé.
  submit?: ReactNode;
  // O erro que não é de nenhum campo, logo acima do botão.
  error?: string | null;
};

/**
 * O formulário do guia 1.3: os campos rolam e o botão principal fica num rodapé preso embaixo, com
 * a pauta em cima, de ponta a ponta. Vai direto dentro da Screen (sem scroll): com o teclado
 * aberto, o rodapé sobe junto.
 */
export function Form({ children, submit, error }: Props) {
  return (
    <View style={styles.flex}>
      <ScrollView
        style={styles.flex}
        contentContainerStyle={styles.fields}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        {children}
      </ScrollView>

      {submit ? (
        <View style={styles.footer}>
          {error ? <Text tone="danger">{error}</Text> : null}
          {submit}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  // Ocupa a altura toda: um bloco com marginTop "auto" (o exemplo da capa) encosta no rodapé.
  fields: { flexGrow: 1, gap: space.lg, paddingBottom: space.xl },
  footer: {
    gap: space.sm,
    // A pauta atravessa a tela inteira, passando da margem de 24 da Screen.
    marginHorizontal: -space.xl,
    paddingHorizontal: space.xl,
    paddingTop: space.md,
    paddingBottom: space.sm,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    backgroundColor: colors.canvas,
  },
});
