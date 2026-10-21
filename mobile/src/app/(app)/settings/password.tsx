import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Check } from 'lucide-react-native';
import { useRef, useState } from 'react';
import { Alert, type TextInput } from 'react-native';

import { changePassword } from '@/api/auth';
import { PasswordField } from '@/components/PasswordField';
import { Button, Form, Header, Screen, TextField } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { colors } from '@/theme/tokens';

/**
 * Troca a senha sabendo a atual. A nova é uma só, com o olho e as marcas da regra (guia 1.4).
 * Quem esqueceu usa o "Esqueci a senha" da tela de entrar.
 */
export default function ChangePasswordScreen() {
  const [current, setCurrent] = useState('');
  const [password, setPassword] = useState('');
  const passwordRef = useRef<TextInput>(null);

  const save = useMutation({
    mutationFn: () => changePassword({ current_password: current, password }),
    onSuccess: () => {
      router.back();
      Alert.alert('Senha trocada', 'Os outros aparelhos foram desconectados. Neste, você continua logado.');
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;

  return (
    <Screen>
      <Header title="Trocar senha" leading="close" />

      <Form
        error={error?.messageOutside(['current_password', 'password'])}
        submit={
          <Button
            title="Trocar senha"
            icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
            loading={save.isPending}
            onPress={() => save.mutate()}
          />
        }
      >
        <TextField
          label="Senha atual"
          value={current}
          onChangeText={setCurrent}
          password
          autoComplete="current-password"
          textContentType="password"
          autoFocus
          returnKeyType="next"
          submitBehavior="submit"
          onSubmitEditing={() => passwordRef.current?.focus()}
          error={error?.field('current_password')}
        />
        <PasswordField
          ref={passwordRef}
          label="Nova senha"
          value={password}
          onChangeText={setPassword}
          returnKeyType="go"
          onSubmitEditing={() => save.mutate()}
          error={error?.field('password')}
        />
      </Form>
    </Screen>
  );
}
