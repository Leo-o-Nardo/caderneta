import { CircleX, Clock, Undo2, type LucideIcon } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import type { ActionStatus, CardAction } from '@/api/types';
import { Button, Text } from '@/components/ui';
import { isExpired } from '@/lib/actions';
import { actionStatusLabel } from '@/lib/chat';
import { colors, space } from '@/theme/tokens';

import { FichaFooter, FichaResult } from './Ficha';

type Props = {
  action: CardAction;
  confirmLabel: string;
  cancelLabel?: string;
  // O ícone de 18 do botão principal: ✓ para pagar, lixeira para apagar (guia, parte 04).
  confirmIcon?: LucideIcon;
  // Apagar: o botão principal fica Tijolo.
  danger?: boolean;
  confirmDisabled?: boolean;
  busy: 'confirm' | 'cancel' | 'undo' | null;
  // A mensagem que não pertence a um campo ("essa confirmação expirou").
  error?: string | null;
  onConfirm: () => void;
  onCancel: () => void;
};

/**
 * O pé das fichas que pedem confirmação. Pendente: os dois botões. Depois: o que aconteceu
 * ("✓ Confirmado", "Cancelado", "Expirou"). Uma ficha pendente com mais de 30 minutos já aparece
 * como expirada, sem esperar o servidor.
 */
export function CardActions({
  action,
  confirmLabel,
  cancelLabel = 'Cancelar',
  confirmIcon: ConfirmIcon,
  danger = false,
  confirmDisabled = false,
  busy,
  error,
  onConfirm,
  onCancel,
}: Props) {
  const expired = isExpired(action);
  // Quem viu a ficha pendente vê o ✓ se desenhar ao confirmar; ao rolar o chat, ele já aparece pronto.
  const [startedPending] = useState(action.status === 'pending' && !expired);

  if (action.status === 'pending' && !expired) {
    return (
      <FichaFooter>
        {error ? (
          <Text variant="caption" tone="danger">
            {error}
          </Text>
        ) : null}
        <View style={styles.buttons}>
          <Button
            title={cancelLabel}
            variant="secondary"
            loading={busy === 'cancel'}
            disabled={busy !== null}
            onPress={onCancel}
            style={styles.cancel}
          />
          <Button
            title={confirmLabel}
            variant={danger ? 'danger' : 'primary'}
            icon={ConfirmIcon ? <ConfirmIcon size={18} color={danger ? colors.onDanger : colors.canvas} strokeWidth={1.6} /> : undefined}
            loading={busy === 'confirm'}
            disabled={confirmDisabled || busy !== null}
            onPress={onConfirm}
            style={styles.confirm}
          />
        </View>
      </FichaFooter>
    );
  }

  return <ActionResult action={action} expired={expired} animate={startedPending} />;
}

// O ícone do que houve quando não deu "✓": cancelado, expirou, desfeito.
const RESULT_ICONS: Record<ActionStatus, LucideIcon> = {
  executed: CircleX,
  cancelled: CircleX,
  expired: Clock,
  undone: Undo2,
  pending: Clock,
};

/** A linha final da ficha: ✓ Confirmado em Musgo, ou o que houve em Lápis. */
export function ActionResult({ action, expired = false, animate = false }: { action: CardAction; expired?: boolean; animate?: boolean }) {
  const status = expired ? 'expired' : action.status;

  return (
    <FichaFooter>
      <FichaResult
        done={status === 'executed'}
        icon={RESULT_ICONS[status]}
        animate={animate}
        text={expired ? 'Expirou sem confirmação · peça de novo' : actionStatusLabel(action)}
      />
    </FichaFooter>
  );
}

const styles = StyleSheet.create({
  buttons: { flexDirection: 'row', gap: space.sm },
  cancel: { flex: 0.6 },
  confirm: { flex: 1 },
});
