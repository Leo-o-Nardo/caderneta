import type { ReactNode } from 'react';
import { View, type StyleProp, type ViewStyle } from 'react-native';

// Os elementos que o tour pode destacar, pelo id ("home.mic"). Só os que estão na tela agora.
const targets = new Map<string, View>();

/** O elemento registrado com esse id, para o tour medir onde ele está. */
export function tourTarget(id: string): View | undefined {
  return targets.get(id);
}

type Props = {
  id: string;
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
};

/**
 * Marca um pedaço de verdade da tela para o tour (guia 1.7): o recorte fica em volta dele. Não muda
 * nada na tela: é só uma View em volta, que não some na otimização do Android (collapsable).
 */
export function TourTarget({ id, children, style }: Props) {
  return (
    <View
      collapsable={false}
      style={style}
      ref={(node) => {
        if (node) {
          targets.set(id, node);
        }

        return () => {
          if (targets.get(id) === node) {
            targets.delete(id);
          }
        };
      }}
    >
      {children}
    </View>
  );
}
