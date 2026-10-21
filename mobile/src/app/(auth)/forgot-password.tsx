import { useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useRef, useState } from 'react';
import { StyleSheet, type TextInput } from 'react-native';

import { requestPasswordCode, resetPassword } from '@/api/auth';
import { AuthHeader } from '@/components/AuthHeader';
import { PasswordField } from '@/components/PasswordField';
import { Button, Form, Screen, TextField } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { font } from '@/theme/tokens';

/**
 * Em duas etapas: primeiro o e-mail (a API manda um código de 6 dígitos), depois o código e a
 * senha nova. A API responde igual se o e-mail não existir, para não revelar quem tem conta.
 */
export default function ForgotPasswordScreen() {
  const params = useLocalSearchParams<{ email?: string }>();
  const [step, setStep] = useState<'email' | 'code'>('email');
  const [email, setEmail] = useState(params.email ?? '');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const passwordRef = useRef<TextInput>(null);

  const sendCode = useMutation({
    mutationFn: () => requestPasswordCode(email),
    onSuccess: () => setStep('code'),
  });

  const reset = useMutation({
    mutationFn: () => resetPassword({ email, code, password }),
    // Volta para a tela de entrar que abriu esta, já com o e-mail.
    onSuccess: () => router.dismissTo({ pathname: '/login', params: { email } }),
  });

  const error = [sendCode.error, reset.error].find((item) => item instanceof ApiError) as ApiError | undefined;

  if (step === 'email') {
    return (
      <Screen>
        <AuthHeader title="Esqueci a senha" subtitle="Mandamos um código de 6 números para o seu e-mail. Ele vale por 15 minutos." />
        <Form
          error={error?.messageOutside(['email'])}
          submit={<Button title="Enviar código" loading={sendCode.isPending} onPress={() => sendCode.mutate()} />}
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
            returnKeyType="send"
            onSubmitEditing={() => sendCode.mutate()}
            error={error?.field('email')}
          />
        </Form>
      </Screen>
    );
  }

  return (
    <Screen>
      <AuthHeader
        title="Nova senha"
        subtitle={`Se ${email.trim()} tiver conta, o código chegou lá.`}
        // Volta ao e-mail, para corrigir um endereço digitado errado.
        onBack={() => setStep('email')}
      />
      <Form
        error={error?.messageOutside(['code', 'password'])}
        submit={<Button title="Salvar nova senha" loading={reset.isPending} onPress={() => reset.mutate()} />}
      >
        <TextField
          label="Código"
          value={code}
          onChangeText={(text) => setCode(text.replace(/\D/g, ''))}
          keyboardType="number-pad"
          maxLength={6}
          autoComplete="one-time-code"
          textContentType="oneTimeCode"
          autoFocus
          style={styles.code}
          returnKeyType="next"
          submitBehavior="submit"
          onSubmitEditing={() => passwordRef.current?.focus()}
          error={error?.field('code')}
        />
        <PasswordField
          ref={passwordRef}
          label="Nova senha"
          value={password}
          onChangeText={setPassword}
          returnKeyType="go"
          onSubmitEditing={() => reset.mutate()}
          error={error?.field('password')}
        />
        <Button title="Não recebi o código" variant="ghost" loading={sendCode.isPending} onPress={() => sendCode.mutate()} />
      </Form>
    </Screen>
  );
}

const styles = StyleSheet.create({
  // Os 6 números espaçados, fáceis de conferir com o e-mail.
  code: { fontFamily: font.semibold, letterSpacing: 6 },
});
