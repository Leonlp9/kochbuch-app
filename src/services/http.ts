import { cacheGet, cacheSet } from './cache'

export interface FetchResult<T> {
  data: T
  fromCache: boolean
}

/**
 * Holt JSON nach dem Prinzip "network-first":
 *  1. Versuche das Netzwerk. Erfolg -> Antwort cachen und zurueckgeben.
 *  2. Kein Netz / Fehler -> letzten gecachten Stand zurueckgeben (Offline-Lesen).
 *  3. Weder Netz noch Cache -> Fehler werfen.
 *
 * `cacheKey` bestimmt, unter welchem Schluessel der Stand abgelegt wird.
 * Bei `cacheKey === null` wird nicht gecacht (z.B. fuer Schreibaktionen).
 */
export async function getJSON<T>(
  url: string,
  cacheKey: string | null,
): Promise<FetchResult<T>> {
  try {
    const res = await fetch(url, { headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = (await res.json()) as T
    if (cacheKey) await cacheSet(cacheKey, data)
    return { data, fromCache: false }
  } catch (err) {
    if (cacheKey) {
      const cached = await cacheGet<T>(cacheKey)
      if (cached !== undefined) return { data: cached, fromCache: true }
    }
    throw err
  }
}
