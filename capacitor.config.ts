import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'de.kochbuch.app',
  appName: 'Kochbuch',
  webDir: 'dist',
  server: {
    // Erlaubt HTTP-Zugriffe (ohne TLS) in Android WebView, z.B. auf lokale Pi-IP.
    cleartext: true,
  },
  plugins: {
    // Laesst fetch()-Aufrufe nativ laufen -> kein CORS-Problem auf dem Handy.
    CapacitorHttp: {
      enabled: true,
    },
  },
}

export default config
