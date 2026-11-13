import Constants from 'expo-constants';

/**
 * Identidade da marca, definida em marcas/<marca>/marca.json e injetada no build
 * por mobile/shared/marca-config.js (MARCA=vaide55 ...). Sem marca definida o app
 * usa os valores do AppChama (marca modelo).
 */
type MarcaExtra = {
  slug?: string;
  nome?: string;
  appNome?: string;
  tagline?: string | null;
  tema?: Partial<Record<'ink' | 'ink2' | 'amber' | 'amberDark', string>>;
  tenantSlug?: string;
  apiUrl?: string;
  cidadePadrao?: { cidade?: string; uf?: string } | null;
};

const marca: MarcaExtra =
  ((Constants.expoConfig?.extra as { marca?: MarcaExtra } | undefined)?.marca) ?? {};

export const theme = {
  ink: marca.tema?.ink ?? '#14213D',
  ink2: marca.tema?.ink2 ?? '#1D2E52',
  amber: marca.tema?.amber ?? '#FF9F1C',
  amberDark: marca.tema?.amberDark ?? '#C97400',
  mint: '#0F9E8D',
  mintBg: '#E3F5F2',
  bg: '#EEF1F4',
  paper: '#FFFFFF',
  line: '#E2E6EA',
  text: '#171A21',
  textSecondary: '#5B6472',
  textMuted: '#9AA2AD',
  danger: '#E24B4A',
};

/** Nome da marca (ex.: "Vai de 55") e do app (ex.: "Vai de 55 Motorista"). */
export const BRAND_NAME = marca.nome ?? 'Chama';
export const APP_NAME = marca.appNome ?? 'Chama';
export const BRAND_TAGLINE = marca.tagline ?? null;
/** Cidade/UF sugeridas no cadastro (vazio = o usuário escolhe). */
export const DEFAULT_CITY = marca.cidadePadrao?.cidade ?? '';
export const DEFAULT_STATE = marca.cidadePadrao?.uf ?? '';

// Variáveis EXPO_PUBLIC_* continuam valendo para desenvolvimento (sobrepõem a marca).
export const API_URL = process.env.EXPO_PUBLIC_API_URL ?? marca.apiUrl ?? 'http://localhost:8088/api/v1';
export const TENANT_SLUG = process.env.EXPO_PUBLIC_TENANT_SLUG ?? marca.tenantSlug ?? 'chama-demo';
export const MAPBOX_TOKEN = process.env.EXPO_PUBLIC_MAPBOX_TOKEN ?? '';
export const REVERB_KEY = process.env.EXPO_PUBLIC_REVERB_KEY ?? 'appchama-key';
export const REVERB_HOST = process.env.EXPO_PUBLIC_REVERB_HOST ?? 'localhost';
export const REVERB_PORT = process.env.EXPO_PUBLIC_REVERB_PORT ?? '8080';
