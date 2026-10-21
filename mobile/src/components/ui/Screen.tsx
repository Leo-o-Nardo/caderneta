import type { ReactNode } from 'react';
import { KeyboardAvoidingView, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView, type Edge } from 'react-native-safe-area-context';

import { colors, space } from '@/theme/tokens';

type Props = {
  children: ReactNode;
  // Formulários rolam (o teclado pode cobrir os campos); telas de lista cuidam da própria rolagem.
  scroll?: boolean;
  // Nas abas, a barra de baixo já respeita a área segura; nas telas soltas, a tela respeita.
  edges?: Edge[];
};

/** O papel e as margens de 24 de toda tela, com a área segura (entalhe, barra de gestos) e o teclado. */
export function Screen({ children, scroll = false, edges = ['top', 'bottom'] }: Props) {
  return (
    <SafeAreaView style={styles.safe} edges={edges}>
      <KeyboardAvoidingView style={styles.flex} behavior="padding">
        {scroll ? (
          <ScrollView
            contentContainerStyle={styles.scrollContent}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            {children}
          </ScrollView>
        ) : (
          <View style={styles.content}>{children}</View>
        )}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.canvas },
  flex: { flex: 1 },
  content: { flex: 1, paddingHorizontal: space.xl },
  scrollContent: { flexGrow: 1, paddingHorizontal: space.xl, paddingBottom: space.xxl },
});
