import { Eye, EyeOff } from 'lucide-react-native';
import { useState, type Ref } from 'react';
import { Pressable, StyleSheet, TextInput, View, type TextInputProps } from 'react-native';

import { colors, font, radius, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = TextInputProps & {
  label: string;
  // Mensagem de erro do campo (vinda da validação da API).
  error?: string;
  hint?: string;
  // Campo de senha com o botão de mostrar/esconder.
  password?: boolean;
  // Para o "Próximo" do teclado levar ao campo seguinte (ref.current?.focus()).
  ref?: Ref<TextInput>;
};

/**
 * Campo de texto do guia: rótulo sempre visível em cima, contorno da Borda de campo. No foco, a
 * borda engrossa em Musgo e o rótulo acompanha; com erro, Tijolo e a mensagem embaixo.
 */
export function TextField({ label, error, hint, password = false, style, onFocus, onBlur, ref, ...input }: Props) {
  const [focused, setFocused] = useState(false);
  const [hidden, setHidden] = useState(true);
  const invalid = Boolean(error);

  return (
    <View style={styles.wrapper}>
      <Text variant="label" tone={focused ? 'accent' : 'ink'}>
        {label}
      </Text>

      <View style={[styles.box, focused && styles.focused, invalid && styles.invalid]}>
        <TextInput
          ref={ref}
          placeholderTextColor={colors.faint}
          selectionColor={colors.accent}
          cursorColor={colors.accent}
          secureTextEntry={password && hidden}
          autoCapitalize={password ? 'none' : input.autoCapitalize}
          style={[styles.input, style]}
          accessibilityLabel={label}
          {...input}
          onFocus={(event) => {
            setFocused(true);
            onFocus?.(event);
          }}
          onBlur={(event) => {
            setFocused(false);
            onBlur?.(event);
          }}
        />

        {password && (
          <Pressable
            onPress={() => setHidden((value) => !value)}
            hitSlop={12}
            accessibilityRole="button"
            accessibilityLabel={hidden ? 'Mostrar senha' : 'Esconder senha'}
          >
            {hidden ? (
              <Eye size={20} color={colors.muted} strokeWidth={1.6} />
            ) : (
              <EyeOff size={20} color={colors.muted} strokeWidth={1.6} />
            )}
          </Pressable>
        )}
      </View>

      {error ? (
        <Text variant="caption" tone="danger">
          {error}
        </Text>
      ) : hint ? (
        <Text variant="caption" tone="muted">
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { gap: space.sm },
  box: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.sm,
    minHeight: 48,
    paddingHorizontal: space.md,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
  },
  // A borda "engrossa" por dentro (sombra interna), sem empurrar o texto.
  focused: { borderColor: colors.accent, boxShadow: `inset 0 0 0 1px ${colors.accent}` },
  invalid: { borderColor: colors.danger, boxShadow: `inset 0 0 0 1px ${colors.danger}` },
  input: {
    flex: 1,
    color: colors.ink,
    fontFamily: font.regular,
    fontSize: 16,
    paddingVertical: space.md,
  },
});
