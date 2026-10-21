import { Stack } from 'expo-router';

import { colors } from '@/theme/tokens';

// Sem login, o app abre na capa (guia 1.4), de onde saem "Criar conta" e "Já tenho conta".
export const unstable_settings = {
  initialRouteName: 'welcome',
};

export default function AuthLayout() {
  return (
    <Stack
      screenOptions={{
        headerShown: false,
        animation: 'fade',
        contentStyle: { backgroundColor: colors.canvas },
      }}
    />
  );
}
