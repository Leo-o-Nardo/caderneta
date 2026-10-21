import { useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { Check, Plus } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet } from 'react-native';

import {
  createHousehold,
  invalidateHousehold,
  renameHousehold,
  resetAfterHouseholdChange,
  useCurrentHousehold,
} from '@/api/households';
import { Button, Form, Header, Screen, Text, TextField } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { colors, space } from '@/theme/tokens';

/** O nome da casa: "create" cria uma casa nova (e já entra nela), "rename" renomeia a atual. */
export default function HouseholdFormScreen() {
  const { mode } = useLocalSearchParams<{ mode?: string }>();
  const renaming = mode === 'rename';
  const current = useCurrentHousehold();

  return (
    <Screen>
      <Header title={renaming ? 'Nome da casa' : 'Nova casa'} leading="close" />

      {renaming && current.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : (
        <NameForm renaming={renaming} initialName={renaming ? (current.data?.name ?? '') : ''} />
      )}
    </Screen>
  );
}

function NameForm({ renaming, initialName }: { renaming: boolean; initialName: string }) {
  const [name, setName] = useState(initialName);

  const save = useMutation({
    mutationFn: (): Promise<unknown> => (renaming ? renameHousehold(name) : createHousehold(name)),
    onSuccess: () => {
      if (renaming) {
        router.back();
        invalidateHousehold();
        return;
      }

      // A casa nova já virou a atual: fecha as duas telas por cima e recarrega tudo.
      router.dismissTo('/settings');
      resetAfterHouseholdChange();
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;

  return (
    <Form
      error={error?.messageOutside(['name'])}
      submit={
        <Button
          title={renaming ? 'Salvar' : 'Criar casa'}
          icon={renaming ? <Check size={18} color={colors.canvas} strokeWidth={1.6} /> : <Plus size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={save.isPending}
          onPress={() => save.mutate()}
        />
      }
    >
      <TextField
        label="Nome"
        value={name}
        onChangeText={setName}
        placeholder="Ex.: Casa da praia, Apê com a Ana"
        maxLength={60}
        autoFocus
        error={error?.field('name')}
        onSubmitEditing={() => save.mutate()}
      />

      {renaming ? null : (
        <Text variant="caption" tone="muted">
          A casa nova começa vazia, com as categorias e listas padrão. Você vira o dono e pode convidar quem quiser.
        </Text>
      )}
    </Form>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
