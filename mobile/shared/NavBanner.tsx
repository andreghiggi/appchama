import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import type { MensagemCorrida } from './navegacao';

/** Faixa preta no topo do mapa, como nos apps de corrida: o que fazer agora + tempo estimado. */
export default function NavBanner({ mensagem }: { mensagem: MensagemCorrida | null }) {
  if (!mensagem) return null;
  return (
    <View style={estilos.faixa} pointerEvents="none">
      <Text style={estilos.titulo} numberOfLines={2}>
        {mensagem.titulo}
      </Text>
      {mensagem.subtitulo ? (
        <Text style={estilos.subtitulo} numberOfLines={2}>
          {mensagem.subtitulo}
        </Text>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  faixa: {
    position: 'absolute',
    top: 10,
    left: 10,
    right: 10,
    backgroundColor: '#111111',
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    elevation: 8,
    zIndex: 30,
    gap: 2,
  },
  titulo: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
  subtitulo: { color: '#C9CED6', fontSize: 12.5, lineHeight: 17 },
});
