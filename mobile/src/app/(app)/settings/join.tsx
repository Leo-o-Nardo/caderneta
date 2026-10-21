import { router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet } from 'react-native';

import { InvitationPreview } from '@/components/InvitationPreview';
import { Button, Form, Header, Screen, Text, TextField } from '@/components/ui';
import { normalizeInviteCode } from '@/lib/households';
import { font } from '@/theme/tokens';

/**
 * Entrar numa casa com o código que veio no convite. Enquanto o link não abre o app direto (isso
 * vem com o domínio próprio), este é o caminho de quem já tem conta.
 */
export default function JoinScreen() {
  const [code, setCode] = useState('');
  // O código confirmado: com ele, a tela mostra o convite em vez do campo.
  const [submitted, setSubmitted] = useState<string | null>(null);

  const ready = code.length === 6;

  return (
    <Screen>
      <Header title="Entrar com um código" leading="close" />

      {submitted ? (
        <InvitationPreview
          code={submitted}
          // Entrou na casa nova: volta para as abas, já com os dados dela.
          onAccepted={() => router.dismissAll()}
          onCancel={() => setSubmitted(null)}
        />
      ) : (
        <Form submit={<Button title="Ver convite" disabled={!ready} onPress={() => setSubmitted(code)} />}>
          <Text tone="muted">Digite o código de 6 caracteres que veio no convite, ou cole a mensagem com o link.</Text>

          <TextField
            label="Código do convite"
            value={code}
            // Sem maxLength: colar o link inteiro precisa chegar aqui para o código ser extraído.
            onChangeText={(text) => setCode(normalizeInviteCode(text))}
            autoCapitalize="characters"
            autoCorrect={false}
            autoFocus
            placeholder="Ex.: XK7P2M"
            style={styles.code}
            onSubmitEditing={() => ready && setSubmitted(code)}
          />
        </Form>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  // O código em letras espaçadas, como aparece no convite.
  code: { fontFamily: font.semibold, letterSpacing: 4 },
});
