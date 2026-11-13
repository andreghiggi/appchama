import React from 'react';
import { Image, Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { BRAND_NAME, BRAND_TAGLINE, theme } from './theme';
import { useBrand, whatsappUrl } from './brand';

/** Topo das telas de entrada: logo da marca (se houver) ou o nome em texto. */
export function BrandHeader({ title, dark = false }: { title?: string; dark?: boolean }) {
  const brand = useBrand();
  const color = dark ? '#fff' : theme.ink;

  return (
    <View style={styles.wrap}>
      {brand?.logo_url ? (
        <Image source={{ uri: brand.logo_url }} style={styles.logo} resizeMode="contain" accessibilityLabel={brand.name} />
      ) : (
        <Text style={[styles.title, { color }]}>{title ?? BRAND_NAME}</Text>
      )}
      {(brand?.tagline ?? BRAND_TAGLINE) ? (
        <Text style={[styles.tagline, { color: dark ? theme.amber : theme.textSecondary }]}>
          {brand?.tagline ?? BRAND_TAGLINE}
        </Text>
      ) : null}
    </View>
  );
}

/** Rodapé das telas de entrada: suporte, privacidade, termos e exclusão de conta (exigidos pelas lojas). */
export function BrandFooter({ dark = false }: { dark?: boolean }) {
  const brand = useBrand();
  if (!brand) return null;

  const links: { label: string; url: string }[] = [];
  const zap = whatsappUrl(brand);
  if (zap) links.push({ label: 'Suporte', url: zap });
  if (brand.legal?.privacy_url) links.push({ label: 'Privacidade', url: brand.legal.privacy_url });
  if (brand.legal?.terms_url) links.push({ label: 'Termos', url: brand.legal.terms_url });
  if (brand.legal?.delete_account_url) links.push({ label: 'Excluir conta', url: brand.legal.delete_account_url });
  if (links.length === 0) return null;

  return (
    <View style={styles.footer}>
      {links.map((l) => (
        <Pressable key={l.label} onPress={() => Linking.openURL(l.url)} hitSlop={8}>
          <Text style={[styles.link, { color: dark ? theme.mintBg : theme.textSecondary }]}>{l.label}</Text>
        </Pressable>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { alignItems: 'center', marginBottom: 16 },
  logo: { width: 220, height: 150 },
  title: { fontSize: 28, fontWeight: '700', marginBottom: 4 },
  tagline: { fontSize: 14, fontWeight: '600', marginTop: 4 },
  footer: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 16, marginTop: 20 },
  link: { fontSize: 12, textDecorationLine: 'underline' },
});
