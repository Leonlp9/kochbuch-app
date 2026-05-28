// =====================================================================
//  ZENTRALE KONFIGURATION
//  Hier die Adresse deines Raspberry-Pi-Servers eintragen.
//  (Dieselbe wie in vite.config.ts fuer den Dev-Proxy.)
// =====================================================================

// Voll-URL zu deinem Kochbuch-Server, MIT abschliessendem Slash.
//   Tailscale:  'http://100.x.y.z/Kochbuch/'
//   LAN:        'http://192.168.178.50/Kochbuch/'
export const PI_SERVER = 'http://192.168.178.143/KochbuchNewApi/'

// Im Browser-Dev-Modus laeuft alles ueber den Vite-Proxy ('/pi-api/'),
// damit kein CORS-Problem entsteht. In der gebauten App (Handy) wird
// direkt der Server angesprochen (CapacitorHttp umgeht CORS nativ).
const isDev = import.meta.env.DEV

/** Basis fuer API-Aufrufe (fetch). */
export const API_BASE = isDev ? '/pi-api/' : PI_SERVER

/** Basis fuer Bilder/Medien. Bilder unterliegen keiner CORS-Pruefung,
 *  daher immer die echte Server-URL. */
export const MEDIA_BASE = PI_SERVER

/** Baut die volle URL fuer einen relativen Medienpfad
 *  (z.B. "uploads/x.webp" oder "ingredientIcons/y.svg"). */
export function mediaUrl(path?: string | null): string {
  if (!path) return ''
  if (/^https?:\/\//.test(path)) return path // schon absolut (z.B. dicebear)
  return MEDIA_BASE + path.replace(/^\/+/, '')
}

/** Baut eine API-URL: apiUrl('search', { search: '', random: 'true' }) */
export function apiUrl(task: string, params: Record<string, string> = {}): string {
  const usp = new URLSearchParams({ task, ...params })
  return `${API_BASE}api.php?${usp.toString()}`
}
