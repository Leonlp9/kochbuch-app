import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'de.kochbuch.app',
  appName: 'Kochbuch',
  webDir: 'dist',
  plugins: {
    // Laesst fetch()-Aufrufe nativ laufen -> kein CORS-Problem auf dem Handy.
    CapacitorHttp: {
      enabled: true,
    },
  },
}

export default config
