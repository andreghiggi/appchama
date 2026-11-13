import { useEffect, useState } from 'react';
import { api } from './api';

/** Configuração pública da marca, entregue pelo backend em GET /api/v1/brand-config. */
export type BrandConfig = {
  slug: string;
  name: string;
  tagline: string | null;
  logo_url: string | null;
  theme: Record<string, string>;
  contacts: { whatsapp?: string; email?: string; phone?: string; site?: string };
  legal: { privacy_url?: string; terms_url?: string; delete_account_url?: string };
  payment_methods: Record<string, boolean>;
  features: Record<string, boolean>;
};

let cache: BrandConfig | null = null;
let pending: Promise<BrandConfig | null> | null = null;

function load(): Promise<BrandConfig | null> {
  if (cache) return Promise.resolve(cache);
  pending ??= api
    .brandConfig()
    .then((res) => {
      cache = res.data;
      return cache;
    })
    .catch(() => null)
    .finally(() => {
      pending = null;
    });
  return pending;
}

/** Devolve a configuração da marca (ou null enquanto carrega/sem rede; o app funciona sem ela). */
export function useBrand(): BrandConfig | null {
  const [brand, setBrand] = useState<BrandConfig | null>(cache);

  useEffect(() => {
    let vivo = true;
    load().then((b) => {
      if (vivo && b) setBrand(b);
    });
    return () => {
      vivo = false;
    };
  }, []);

  return brand;
}

/** Link do WhatsApp de suporte, ou null. */
export function whatsappUrl(brand: BrandConfig | null): string | null {
  const digits = (brand?.contacts?.whatsapp ?? '').replace(/\D/g, '');
  if (!digits) return null;
  return `https://wa.me/${digits.length <= 11 ? `55${digits}` : digits}`;
}
