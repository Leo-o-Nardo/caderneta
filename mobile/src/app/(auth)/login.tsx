import { useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { CircleX, Clock } from 'lucide-react-native';
import { useRef, useState } from 'react';
import type { TextInput } from 'react-native';

import { login } from '@/api/auth';
import { AuthHeader } from '@/components/AuthHeader';
import { Button, Form, Notice, Screen, TextField } from '@/components/ui';
import { ApiError } from '@/lib/apiError';

// Os erros que a tela mostra no lugar certo: nos campos ou no aviso da liberação.
const VISIBLE_FIELDS = ['email', 'password', 'access_pending', 'access_rejected'];

/** Entrar (guia 1.4): e-mail e senha, "Esqueci a senha" e o botão preso embaixo. */
export default function LoginScreen() {
  // Depois de redefinir a senha, a tela já abre com o e-mail preenchido.
  const params = useLocalSearchParams<{ email?: string }>();
  const [email, setEmail] = useState(params.email ?? '');
  const [password, setPassword] = useState('');
  const passwordRef = useRef<TextInput>(null);

  const mutation = useMutation({ mutationFn: login });
  const error = mutation.error instanceof ApiError ? mutation.error : null;
  // Senha certa, mas o cadastro ainda espera a liberação (ou foi recusado): aviso, e não erro de campo.
  const pending = error?.field('access_pending');
  const rejected = error?.field('access_rejected');

  const submit = () => mutation.mutate({ email, password });

  return (
    <Screen>
      <AuthHeader title="Entrar" subtitle="Que bom te ver de novo." />

      <Form
        error={error?.messageOutside(VISIBLE_FIELDS)}
        submit={
          <>
            {pending ? (
              <Notice icon={Clock} tone="warn">
                {pending}
              </Notice>
            ) : null}
            {rejected ? (
              <Notice icon={CircleX} tone="danger">
                {rejected}
              </Notice>
            ) : null}
            <Button title="Entrar" loading={mutation.isPending} onPress={submit} />
          </>
        }
      >
        <TextField
          label="E-mail"
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          autoComplete="email"
          keyboardType="email-address"
          textContentType="emailAddress"
          autoFocus={!params.email}
          returnKeyType="next"
          submitBehavior="submit"
          onSubmitEditing={() => passwordRef.current?.focus()}
          error={error?.field('email')}
        />
        <TextField
          ref={passwordRef}
          label="Senha"
          value={password}
          onChangeText={setPassword}
          password
          autoComplete="current-password"
          textContentType="password"
          autoFocus={Boolean(params.email)}
          returnKeyType="go"
          onSubmitEditing={submit}
          error={error?.field('password')}
        />
        <Button
          title="Esqueci a senha"
          variant="ghost"
          onPress={() => router.push({ pathname: '/forgot-password', params: { email } })}
        />
      </Form>
    </Screen>
  );
}
