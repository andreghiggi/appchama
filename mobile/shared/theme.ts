export const theme = {
  ink: '#14213D',
  ink2: '#1D2E52',
  amber: '#FF9F1C',
  amberDark: '#C97400',
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

export const API_URL = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8088/api/v1';
export const TENANT_SLUG = process.env.EXPO_PUBLIC_TENANT_SLUG ?? 'chama-demo';
export const REVERB_KEY = process.env.EXPO_PUBLIC_REVERB_KEY ?? 'appchama-key';
export const REVERB_HOST = process.env.EXPO_PUBLIC_REVERB_HOST ?? 'localhost';
export const REVERB_PORT = process.env.EXPO_PUBLIC_REVERB_PORT ?? '8080';
