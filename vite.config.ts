import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// =============================================================
//  WICHTIG: Adresse deines Raspberry-Pi-Servers hier eintragen.
//  Beispiel Tailscale:   http://100.x.y.z/Kochbuch/
//  Beispiel lokal:       http://192.168.178.50/Kochbuch/
//  (Im Browser-Dev-Modus wird darueber per Proxy zugegriffen,
//   damit kein CORS-Problem entsteht.)
// =============================================================
const PI_SERVER = 'http://192.168.178.143/KochbuchNewApi/'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: true,
    proxy: {
      // Dev-Preview im Browser: /pi-api -> dein Server (umgeht CORS)
      '/pi-api': {
        target: PI_SERVER,
        changeOrigin: true,
        rewrite: (p) => p.replace(/^\/pi-api\//, ''),
      },
    },
  },
})
