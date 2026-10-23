import { useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { MailCheck, Ticket } from 'lucide-react-native';
import { useRef, useState } from 'react';
import { Linking, StyleSheet, View, type TextInput } from 'react-native';

import { register } from '@/api/auth';
import { AuthHeader } from '@/components/AuthHeader';
import { PasswordField } from '@/components/PasswordField';
import { Button, Form, Notice, Screen, Text, TextField } from '@/components/ui';
import { siteUrl } from '@/lib/api';
import { ApiError } from '@/lib/apiError';
import { firstName } from '@/lib/dates';
import { normalizeInviteCode } from '@/lib/households';
import { useInvite } from '@/stores/invite';
import { colors, font, space } from '@/theme/tokens';

const STEP_LABEL = 'Criar conta';

/**
 * Criar conta (guia 1.4), numa conversa em dois passos: primeiro o nome, sozinho; depois o e-mail
 * e a senha, já chamando a pessoa pelo nome. Com convite, a conta entra direto na casa; sem
 * convite, espera a liberação e a tela vira o "Cadastro recebido".
 */
export default function RegisterScreen() {
  // Vindo do link do convite, a tela já abre com o código preenchido.
  const params = useLocalSearchParams<{ invite?: string }>();
  const [step, setStep] = useState<1 | 2>(1);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [hasInvite, setHasInvite] = useState(Boolean(params.invite));
  const [inviteCode, setInviteCode] = useState(normalizeInviteCode(params.invite ?? ''));
  const passwordRef = useRef<TextInput>(null);
  const inviteRef = useRef<TextInput>(null);

  const mutation = useMutation({ mutationFn: register });
  const error = mutation.error instanceof ApiError ? mutation.error : null;
  const withInvite = hasInvite && inviteCode !== '';
  const named = name.trim() !== '';

  const submit = () => {
    // O convite vai junto com o cadastro: não precisa abrir de novo depois de entrar.
    useInvite.getState().setPendingCode(null);
    mutation.mutate({
      name,
      email,
      password,
      // Com convite, a conta entra direto na casa de quem convidou, sem criar "Minha casa".
      ...(withInvite ? { invite_code: inviteCode } : {}),
    });
  };

  // Sem convite, a conta espera a liberação: em vez de entrar, a tela avisa.
  if (mutation.data?.pending) {
    return <Received name={name} email={email} />;
  }

  if (step === 1) {
    return (
      <Screen>
        <AuthHeader
          step={{ label: STEP_LABEL, current: 1, total: 2 }}
          title="Como você se chama?"
          subtitle="É assim que o app fala com você e que a sua casa vê os seus lançamentos."
        />
        <Form submit={<Button title="Continuar" disabled={!named} onPress={() => setStep(2)} />}>
          <TextField
            label="Nome"
            value={name}
            onChangeText={setName}
            autoComplete="name"
            textContentType="name"
            autoCapitalize="words"
            maxLength={80}
            autoFocus
            returnKeyType="next"
            onSubmitEditing={() => named && setStep(2)}
          />
        </Form>
      </Screen>
    );
  }

  return (
    <Screen>
      <AuthHeader
        step={{ label: STEP_LABEL, current: 2, total: 2 }}
        title={`Prazer, ${firstName(name)}.`}
        subtitle="Agora, o e-mail e uma senha para entrar."
        // Volta ao nome sem perder o que já foi digitado aqui.
        onBack={() => setStep(1)}
      />
      <Form
        // O nome não aparece neste passo: um erro nele vem como mensagem geral, em cima do botão.
        error={error?.messageOutside(['email', 'password', ...(hasInvite ? ['invite_code'] : [])])}
        submit={<Button title={withInvite ? 'Criar conta e entrar' : 'Criar conta'} loading={mutation.isPending} onPress={submit} />}
      >
        <TextField
          label="E-mail"
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          autoComplete="email"
          keyboardType="email-address"
          textContentType="emailAddress"
          autoFocus
          returnKeyType="next"
          submitBehavior="submit"
          onSubmitEditing={() => passwordRef.current?.focus()}
          error={error?.field('email')}
        />
        <PasswordField
          ref={passwordRef}
          label="Senha"
          value={password}
          onChangeText={setPassword}
          returnKeyType={hasInvite ? 'next' : 'go'}
          submitBehavior={hasInvite ? 'submit' : undefined}
          onSubmitEditing={() => (hasInvite ? inviteRef.current?.focus() : submit())}
          error={error?.field('password')}
        />

        {hasInvite ? (
          <TextField
            ref={inviteRef}
            label="Código de convite"
            value={inviteCode}
            onChangeText={(text) => setInviteCode(normalizeInviteCode(text))}
            autoCapitalize="characters"
            autoCorrect={false}
            hint="Os 6 caracteres que vieram no convite (ou cole o link)."
            style={styles.code}
            returnKeyType="go"
            onSubmitEditing={submit}
            error={error?.field('invite_code')}
          />
        ) : (
          <Button title="Tenho um código de convite" variant="ghost" onPress={() => setHasInvite(true)} />
        )}

        {/* O que acontece depois, dito antes de enviar. */}
        {withInvite ? (
          <Notice icon={Ticket}>Com o convite, você entra na casa de quem te chamou na hora, sem esperar liberação.</Notice>
        ) : (
          <Notice>Sem convite, o cadastro passa por uma liberação. Você recebe um e-mail quando puder entrar.</Notice>
        )}

        <Text variant="caption" tone="muted">
          O que o app guarda e para quem envia está na{' '}
          <Text
            variant="caption"
            style={styles.link}
            accessibilityRole="link"
            onPress={() => Linking.openURL(siteUrl('/privacidade'))}
          >
            política de privacidade
          </Text>
          .
        </Text>
      </Form>
    </Screen>
  );
}

/** O fim do cadastro sem convite: quem é, para onde vai o e-mail da liberação e a volta para a capa. */
function Received({ name, email }: { name: string; email: string }) {
  return (
    <Screen>
      <Form
        submit={<Button title="Voltar para o começo" variant="secondary" onPress={() => router.dismissTo('/welcome')} />}
      >
        <View style={styles.received}>
          <MailCheck size={32} color={colors.muted} strokeWidth={1.6} />
          <Text variant="title" accessibilityRole="header">
            Cadastro recebido, {firstName(name)}
          </Text>
          <Text tone="muted">Assim que ele for liberado, chega um e-mail em {email.trim()} e você já pode entrar.</Text>
        </View>
      </Form>
    </Screen>
  );
}

const styles = StyleSheet.create({
  // O código em letras espaçadas, como aparece no convite.
  code: { fontFamily: font.semibold, letterSpacing: 4 },
  link: { color: colors.accent, textDecorationLine: 'underline' },
  received: { gap: space.md, paddingTop: space.xxxl },
});
