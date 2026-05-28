import { reactive } from 'vue'
import { get, set, keys, del } from 'idb-keyval'
import { isOnline, hasServerConnection } from './network'

// Bild-Cache: laedt Bilder/Icons einmalig (online) als Blob herunter und legt
// sie in IndexedDB ab. Danach sind sie auch offline verfuegbar.
//
// `cachedSrc(url)` ist reaktiv: gibt sofort die Netzwerk-URL zurueck und
// stoesst im Hintergrund das Cachen an. Sobald der Blob da ist, wird die
// (reaktive) Map aktualisiert und die View zeigt das lokale Bild.

const PREFIX = 'img:'
const resolved = reactive(new Map<string, string>()) // url -> objectURL
const inflight = new Set<string>()

async function doResolve(url: string): Promise<void> {
  if (inflight.has(url) || resolved.has(url)) return
  inflight.add(url)
  try {
    const cached = (await get(PREFIX + url)) as Blob | undefined
    if (cached) {
      resolved.set(url, URL.createObjectURL(cached))
      return
    }
    if (!isOnline.value || !hasServerConnection.value) return
    const res = await fetch(url)
    if (!res.ok) return
    const blob = await res.blob()
    if (!blob || blob.size === 0) return
    await set(PREFIX + url, blob)
    resolved.set(url, URL.createObjectURL(blob))
  } catch {
    /* CORS/Netz – einfach Netzwerk-URL nutzen */
  } finally {
    inflight.delete(url)
  }
}

/** Im Template verwenden: liefert sofort etwas Anzeigbares und cached nebenbei. */
export function cachedSrc(url?: string | null): string {
  if (!url) return ''
  if (/^(data:|blob:)/.test(url)) return url
  if (resolved.has(url)) return resolved.get(url) as string
  void doResolve(url)
  return url
}

/** Mehrere Bilder gezielt vorladen (z. B. alle Zutaten-Icons). */
export async function preloadImages(
  urls: (string | null | undefined)[],
  onProgress?: (done: number, total: number) => void,
): Promise<void> {
  const list = urls.filter((u): u is string => !!u)
  let done = 0
  for (const u of list) {
    await doResolve(u)
    onProgress?.(++done, list.length)
  }
}

/** Anzahl der aktuell offline gespeicherten Bilder. */
export async function cachedImageCount(): Promise<number> {
  try {
    const all = (await keys()) as string[]
    return all.filter((k) => typeof k === 'string' && k.startsWith(PREFIX)).length
  } catch {
    return 0
  }
}

/** Alle gespeicherten Bilder loeschen (Speicher freigeben). */
export async function clearImageCache(): Promise<void> {
  try {
    const all = (await keys()) as string[]
    for (const k of all) {
      if (typeof k === 'string' && k.startsWith(PREFIX)) await del(k)
    }
    resolved.clear()
  } catch {
    /* ignore */
  }
}
