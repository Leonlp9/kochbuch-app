import { ref } from 'vue'
import { Network } from '@capacitor/network'

// Reaktiver Online-Status. Funktioniert in der App (Capacitor Network)
// und im Browser (Web-Implementierung des Plugins / navigator.onLine).

export const isOnline = ref<boolean>(
  typeof navigator !== 'undefined' ? navigator.onLine : true,
)

export async function initNetwork(): Promise<void> {
  try {
    const status = await Network.getStatus()
    isOnline.value = status.connected
    Network.addListener('networkStatusChange', (s) => {
      isOnline.value = s.connected
    })
  } catch {
    // Fallback rein ueber den Browser
    window.addEventListener('online', () => (isOnline.value = true))
    window.addEventListener('offline', () => (isOnline.value = false))
  }
}
