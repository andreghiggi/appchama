import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

const memory = new Map<string, string>();

async function webGet(key: string): Promise<string | null> {
  try {
    return localStorage.getItem(key);
  } catch {
    return memory.get(key) ?? null;
  }
}

async function webSet(key: string, value: string): Promise<void> {
  try {
    localStorage.setItem(key, value);
  } catch {
    memory.set(key, value);
  }
}

async function webDelete(key: string): Promise<void> {
  try {
    localStorage.removeItem(key);
  } catch {
    memory.delete(key);
  }
}

export const storage = {
  getItem: (key: string) =>
    Platform.OS === 'web' ? webGet(key) : SecureStore.getItemAsync(key),
  setItem: (key: string, value: string) =>
    Platform.OS === 'web' ? webSet(key, value) : SecureStore.setItemAsync(key, value),
  deleteItem: (key: string) =>
    Platform.OS === 'web' ? webDelete(key) : SecureStore.deleteItemAsync(key),
};
