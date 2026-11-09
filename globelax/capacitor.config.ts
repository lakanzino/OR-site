import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Globe.Lax — Capacitor shell around the fully offline single-file web build.
 * The web bundle is served from the app's own assets, so the app never needs
 * the INTERNET permission: no remote server, no CDN, no telemetry.
 */
const config: CapacitorConfig = {
  appId: 'com.globelax.app',
  appName: 'Globe.Lax',
  webDir: 'dist',
  android: {
    // Offline-first: never load anything that is not shipped inside the APK.
    allowMixedContent: false,
    captureInput: true,
    webContentsDebuggingEnabled: false,
    backgroundColor: '#040C24',
  },
  server: {
    androidScheme: 'https',
  },
};

export default config;
