import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { api, setToken } from '../../shared/api';
import { storage } from '../../shared/storage';
import type { User } from '../../shared/types';

type AuthContextValue = {
  user: User | null;
  loading: boolean;
  login: (token: string, user: User) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      const token = await storage.getItem('token');
      if (token) {
        setToken(token);
        try {
          const me = await api.me();
          setUser(me);
        } catch {
          await storage.deleteItem('token');
          setToken(null);
        }
      }
      setLoading(false);
    })();
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      login: async (token, nextUser) => {
        await storage.setItem('token', token);
        setToken(token);
        setUser(nextUser);
      },
      logout: async () => {
        await storage.deleteItem('token');
        setToken(null);
        setUser(null);
      },
    }),
    [user, loading],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
