import { router, useLocalSearchParams } from 'expo-router';
import { Ticket } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { Wordmark } from '@/components/AuthHeader';
import { InvitationPreview } from '@/components/InvitationPreview';
import { Button, Form, Header, Notice, Screen, Text } from '@/components/ui';
import { normalizeInviteCode } from '@/lib/households';
import { useInvite } from '@/stores/invite';
import { useSession } from '@/stores/session';
import { space } from '@/theme/tokens';

/**
 * O link do convite (".../convite/XK7P2M"). Fica fora das rotas protegidas porque precisa abrir
 * com ou sem login: logado, mostra o convite para aceitar; sem login, é uma versão da capa que leva
 * para criar a conta (já com o código) ou para entrar e voltar aqui depois.
 */
export default function InviteScreen() {
  const params = useLocalSearchParams<{ code: string }>();
  const code = normalizeInviteCode(params.code ?? '');
  const status = useSession((state) => state.status);

  // Aberto pelo link com o app fechado, não há tela atrás: vai para o começo do app.
  const close = () => (router.canGoBack() ? router.back() : router.replace('/'));

  if (status === 'signedIn') {
    return (
      <Screen>
        <Header title="Convite" leading="close" onLeadingPress={close} />
        <InvitationPreview code={code} onAccepted={close} onCancel={close} />
      </Screen>
    );
  }

  const createAccount = () => router.replace({ pathname: '/register', params: { invite: code } });

  const signIn = () => {
    // Depois do login, o Início vê o código guardado e abre o convite de novo.
    useInvite.getState().setPendingCode(code);
    router.replace('/login');
  };

  return (
    <Screen>
      <Form
        submit={
          <>
            <Button title="Criar conta" onPress={createAccount} />
            <Button title="Já tenho conta" variant="secondary" onPress={signIn} />
          </>
        }
      >
        <View style={styles.top}>
          <Wordmark />
          <Text variant="display" style={styles.headline}>
            Você recebeu um convite.
          </Text>
          <Text tone="muted">
            Alguém te chamou para dividir as contas da casa na Caderneta. Crie sua conta com o código, ou entre na que você já
            tem, e a casa aparece para você.
          </Text>
        </View>
        <Notice icon={Ticket}>Código do convite: {code}. Com ele, você entra na hora, sem esperar liberação.</Notice>
      </Form>
    </Screen>
  );
}

const styles = StyleSheet.create({
  top: { paddingTop: space.lg },
  headline: { marginTop: space.xxl, marginBottom: space.lg },
});
