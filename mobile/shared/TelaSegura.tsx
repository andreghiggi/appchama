import React, { useEffect, useState } from 'react';
import { Keyboard, Platform, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { theme } from './theme';

/** Altura do teclado aberto (0 com o teclado fechado). */
export function useAlturaTeclado(): number {
  const [altura, setAltura] = useState(0);
  useEffect(() => {
    const mostrar = Keyboard.addListener(Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow', (e: { endCoordinates?: { height?: number } }) =>
      setAltura(e.endCoordinates?.height ?? 0),
    );
    const esconder = Keyboard.addListener(Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide', () =>
      setAltura(0),
    );
    return () => {
      mostrar.remove();
      esconder.remove();
    };
  }, []);
  return altura;
}

/**
 * Com a API 35+ do Android o app desenha por baixo da barra de status, da barra de navegacao e do teclado.
 * Esta tela afasta o conteudo dessas areas: barra de cima, barra de baixo e teclado aberto.
 */
export function TelaSegura({ children }: { children: React.ReactNode }) {
  const insets = useSafeAreaInsets();
  const teclado = useAlturaTeclado();
  return (
    <View style={estilos.fundo}>
      <View style={{ height: insets.top }} />
      <View style={[estilos.conteudo, { paddingBottom: teclado > 0 ? teclado : insets.bottom }]}>{children}</View>
    </View>
  );
}

const estilos = StyleSheet.create({
  // O fundo escuro combina com a barra de status clara; o conteudo mantem o fundo normal.
  fundo: { flex: 1, backgroundColor: theme.ink },
  conteudo: { flex: 1, backgroundColor: theme.bg },
});
