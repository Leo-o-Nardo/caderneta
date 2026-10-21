import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Check } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet } from 'react-native';

import { updateProfile, useMe } from '@/api/auth';
import { invalidateHousehold } from '@/api/households';
import { Button, Form, Header, Screen, TextField } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { colors } from '@/theme/tokens';

/** O nome que aparece na saudação, nos lançamentos e para os outros membros da casa. */
export default function ProfileScreen() {
  const { data: user } = useMe();
  const [name, setName] = useState(user?.name ?? '');

  const save = useMutation({
    mutationFn: () => updateProfile({ name }),
    onSuccess: () => {
      router.back();
      // A lista de membros mostra o nome.
      invalidateHousehold();
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;

  return (
    <Screen>
      <Header title="Perfil" leading="close" />

      <Form
        error={error?.messageOutside(['name'])}
        submit={
          <Button
            title="Salvar"
            icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
            loading={save.isPending}
            onPress={() => save.mutate()}
          />
        }
      >
        <TextField
          label="Nome"
          value={name}
          onChangeText={setName}
          autoComplete="name"
          textContentType="name"
          maxLength={80}
          error={error?.field('name')}
        />

        <TextField
          label="E-mail"
          value={user?.email ?? ''}
          editable={false}
          hint="O e-mail de login não muda por aqui."
          style={styles.readOnly}
        />
      </Form>
    </Screen>
  );
}

const styles = StyleSheet.create({
  // Só leitura: o texto em Grafite, para não parecer um campo para digitar.
  readOnly: { color: colors.muted },
});
