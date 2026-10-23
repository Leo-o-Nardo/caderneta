import { useMutation } from '@tanstack/react-query';
import { Trash2 } from 'lucide-react-native';
import { useState } from 'react';
import { Alert, StyleSheet, View } from 'react-native';

import { deleteAccount } from '@/api/auth';
import { Button, Form, Header, Notice, Screen, Text, TextField } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

/**
 * Excluir a conta (as lojas exigem que dê para fazer pelo app). Pede a senha e confirma na folha.
 * O que sai e o que fica é o do AccountService; a mesma coisa sem o app é a página /excluir-conta.
 */
export default function DeleteAccountScreen() {
  const [password, setPassword] = useState('');

  const remove = useMutation({
    mutationFn: () => deleteAccount(password),
    // A sessão acabou: o app volta sozinho para a tela de entrar.
    onSuccess: () => Alert.alert('Conta excluída', 'Seus dados foram apagados. Obrigado por ter usado a Caderneta.'),
  });

  const error = remove.error instanceof ApiError ? remove.error : null;

  async function submit() {
    const confirmed = await confirm({
      title: 'Excluir a conta?',
      message: 'Seus dados saem agora e não dá para desfazer.',
      confirmLabel: 'Excluir conta',
      destructive: true,
    });

    if (confirmed) {
      remove.mutate();
    }
  }

  return (
    <Screen>
      <Header title="Excluir conta" leading="close" />

      <Form
        error={error?.messageOutside(['password'])}
        submit={
          <Button
            title="Excluir conta"
            icon={<Trash2 size={18} color={colors.canvas} strokeWidth={1.6} />}
            loading={remove.isPending}
            disabled={password === ''}
            onPress={submit}
          />
        }
      >
        <View style={styles.points}>
          <Text>
            <Text variant="strong">Sai na hora: </Text>
            seu login em todos os celulares, suas conversas com o assistente, seus avisos, lembretes e cartões.
          </Text>
          <Text>
            <Text variant="strong">Nas casas que você divide: </Text>
            os lançamentos, as contas e as listas continuam para as outras pessoas, sem o seu nome. Se você for a única
            pessoa dona, a casa passa para o membro mais antigo. Se ninguém mais puder editar, a casa é apagada.
          </Text>
        </View>

        <Notice tone="danger" icon={Trash2}>
          Não dá para desfazer. Para voltar a usar, é preciso criar uma conta nova.
        </Notice>

        <TextField
          label="Sua senha"
          value={password}
          onChangeText={setPassword}
          password
          autoComplete="current-password"
          textContentType="password"
          returnKeyType="go"
          onSubmitEditing={submit}
          error={error?.field('password')}
        />
      </Form>
    </Screen>
  );
}

const styles = StyleSheet.create({
  points: { gap: space.md },
});
