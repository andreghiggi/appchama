export const DRIVER_STORAGE_KEYS = {
  remember: 'driver_remember_login',
  phone: 'driver_saved_phone',
  loginMode: 'driver_login_mode',
  secret: 'driver_saved_secret',
} as const;

export type DriverLoginMode = 'password' | 'code';
