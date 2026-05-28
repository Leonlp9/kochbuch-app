import { ref } from 'vue'
import { Network } from '@capacitor/network'
import { apiUrl } from '@/config'

// Reaktiver Online-Status. Funktioniert in der App (Capacitor Network)
// und im Browser (Web-Implementierung des Plugins / navigator.onLine).

export const isOnline = ref<boolean>(
  typeof navigator !== 'undefined' ? navigator.onLine : true,
)
export const hasServerConnection = ref<boolean>(true)

let serverProbeTimer: ReturnType<typeof setInterval> | undefined

async function probeServer(timeoutMs = 4500): Promise<boolean> {
  if (!isOnline.value) return false

  const controller = new AbortController()
  const timer = window.setTimeout(() => controller.abort(), timeoutMs)
  try {
    const res = await fetch(apiUrl('getKategorien'), {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
      signal: controller.signal,
    })
    return res.ok
  } catch {
    return false
  } finally {
    clearTimeout(timer)
  }
}

export async function refreshServerConnection(): Promise<void> {
  hasServerConnection.value = await probeServer()
}

function startServerProbeLoop() {
  if (serverProbeTimer) clearInterval(serverProbeTimer)
  serverProbeTimer = setInterval(() => {
    void refreshServerConnection()
  }, 30000)
}

export async function initNetwork(): Promise<void> {
  try {
    const status = await Network.getStatus()
    isOnline.value = status.connected
    if (isOnline.value) {
      await refreshServerConnection()
      startServerProbeLoop()
    } else {
      hasServerConnection.value = false
    }

    Network.addListener('networkStatusChange', (s) => {
      isOnline.value = s.connected
      if (s.connected) {
        void refreshServerConnection()
      } else {
        hasServerConnection.value = false
      }
    })
  } catch {
    // Fallback rein ueber den Browser
    window.addEventListener('online', () => {
      isOnline.value = true
      void refreshServerConnection()
    })
    window.addEventListener('offline', () => {
      isOnline.value = false
      hasServerConnection.value = false
    })

    if (isOnline.value) {
      await refreshServerConnection()
      startServerProbeLoop()
    } else {
      hasServerConnection.value = false
    }
  }
}
