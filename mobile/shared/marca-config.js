/**
 * Configuração por marca (white label) — roda no Node, na hora do build/start do Expo.
 *
 * Uso: cada app (passenger/driver) tem um app.config.js que chama aplicar().
 * A marca vem da variável de ambiente MARCA (padrão: appchama):
 *   MARCA=vaide55 npx expo start
 *   MARCA=vaide55 eas build --profile vaide55 --platform android
 *
 * Fonte de verdade: marcas/<marca>/marca.json + marcas/<marca>/assets/.
 * Não coloque segredos aqui nem em marca.json (chaves vão em variáveis do EAS).
 */
const fs = require('fs');
const path = require('path');

const RAIZ = path.resolve(__dirname, '..', '..');
const APP_PARA_CHAVE = { passenger: 'passageiro', driver: 'motorista' };

function lerMarca(slug) {
  const arquivo = path.join(RAIZ, 'marcas', slug, 'marca.json');
  if (!fs.existsSync(arquivo)) {
    throw new Error(`Marca "${slug}" não existe: falta ${arquivo}`);
  }
  return JSON.parse(fs.readFileSync(arquivo, 'utf8'));
}

function relativoDoApp(appDir, arquivoAbsoluto) {
  // Expo resolve os caminhos a partir da pasta do app; usamos "./.." relativo e barras normais.
  return path.relative(appDir, arquivoAbsoluto).split(path.sep).join('/');
}

function asset(slug, appDir, nome) {
  if (!nome) return undefined;
  const abs = path.join(RAIZ, 'marcas', slug, nome);
  return fs.existsSync(abs) ? relativoDoApp(appDir, abs) : undefined;
}

/**
 * @param {object} config  conteúdo do app.json (expo)
 * @param {'passenger'|'driver'} app
 * @param {string} appDir  __dirname do app.config.js
 */
function aplicar(config, app, appDir) {
  const slug = (process.env.MARCA || 'appchama').trim();
  const marca = lerMarca(slug);
  const chave = APP_PARA_CHAVE[app];
  const dadosApp = (marca.apps || {})[chave] || {};
  const nome = dadosApp.nome || config.name;
  const pacote = dadosApp.pacote || (config.android && config.android.package);
  const tema = marca.tema || {};
  const fundo = tema.ink || (config.splash && config.splash.backgroundColor) || '#14213D';

  const icone = asset(slug, appDir, (marca.assets || {}).icone);
  const iconeAdaptativo = asset(slug, appDir, (marca.assets || {}).icone_adaptativo);
  const splash = asset(slug, appDir, (marca.assets || {}).splash);

  const apiBase = String(process.env.EXPO_PUBLIC_API_URL || `${(marca.api_url || '').replace(/\/+$/, '')}/api/v1`);
  const versaoCodigo = Number(process.env.APP_VERSION_CODE || dadosApp.versao_codigo || 1);

  const android = {
    ...(config.android || {}),
    package: pacote,
    versionCode: versaoCodigo,
    adaptiveIcon: {
      ...((config.android || {}).adaptiveIcon || {}),
      ...(iconeAdaptativo ? { foregroundImage: iconeAdaptativo } : {}),
      backgroundColor: (marca.assets || {}).fundo_icone_adaptativo || fundo,
    },
  };
  // Mapa nativo (Google Maps): a chave vem do ambiente/EAS, nunca do repositório.
  if (process.env.GOOGLE_MAPS_API_KEY) {
    android.config = { googleMaps: { apiKey: process.env.GOOGLE_MAPS_API_KEY } };
  }

  return {
    ...config,
    name: nome,
    slug: `${slug}-${app}`,
    scheme: `${slug}-${app}`,
    version: process.env.APP_VERSION || config.version || '1.0.0',
    ...(icone ? { icon: icone } : {}),
    splash: {
      ...(config.splash || {}),
      ...(splash ? { image: splash, resizeMode: 'contain' } : {}),
      backgroundColor: fundo,
    },
    ios: { ...(config.ios || {}), bundleIdentifier: pacote },
    android,
    web: { ...(config.web || {}), name: nome },
    extra: {
      ...(config.extra || {}),
      // Cada app de cada marca é um projeto próprio no Expo (id público, não é segredo).
      eas: dadosApp.eas_project_id ? { projectId: dadosApp.eas_project_id } : undefined,
      marca: {
        slug,
        nome: marca.nome,
        appNome: nome,
        tagline: marca.tagline || null,
        tema,
        tenantSlug: marca.tenant_slug,
        apiUrl: apiBase,
        cidadePadrao: marca.cidade_padrao || null,
      },
    },
  };
}

module.exports = { aplicar, lerMarca };
