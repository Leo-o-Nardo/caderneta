import { StyleSheet, View } from 'react-native';

import { useConfirm } from '@/stores/confirm';
import { space } from '@/theme/tokens';

import { BottomSheet } from './BottomSheet';
import { Button } from './Button';
import { Text } from './Text';

/**
 * A folha de confirmação do guia, aberta por confirm(): o título, o que acontece com os números e
 * os dois botões. Tocar fora ou no "voltar" do Android cancela.
 */
export function SheetHost() {
  const request = useConfirm((state) => state.request);
  const answer = useConfirm((state) => state.answer);
  const cancelLabel = request?.cancelLabel ?? 'Cancelar';

  return (
    <BottomSheet visible={request !== null} onClose={() => answer(false)} closeLabel={cancelLabel}>
      {request ? (
        <>
          <Text variant="subtitle" accessibilityRole="header">
            {request.title}
          </Text>
          {request.message ? <Text style={styles.message}>{request.message}</Text> : null}
          <View style={styles.buttons}>
            <Button title={request.confirmLabel} variant={request.destructive ? 'danger' : 'primary'} onPress={() => answer(true)} />
            <Button title={cancelLabel} variant="secondary" onPress={() => answer(false)} />
          </View>
        </>
      ) : null}
    </BottomSheet>
  );
}

const styles = StyleSheet.create({
  message: { marginTop: space.sm },
  buttons: { gap: space.sm, marginTop: space.xl },
});
