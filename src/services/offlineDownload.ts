import { search } from './api'
import { getRezept } from './api'
import { preloadImages, cachedImageCount } from './imageCache'
import { cacheKeys } from './cache'
import type { Rezept } from '@/types/models'

export type ProgressCallback = (done: number, total: number, label?: string) => void

/** Anzahl gecachter Rezepte (vollständige Rezept-JSONs). */
export async function cachedRecipeCount(): Promise<number> {
  const all = await cacheKeys()
  return all.filter((k) => k.startsWith('rezept:')).length
}

/** Alle Rezepte (JSON-Daten) herunterladen und im IndexedDB-Cache speichern. */
export async function downloadAllRecipes(onProgress?: ProgressCallback): Promise<void> {
  const { data: results } = await search({ search: '' })
  const total = results.length
  let done = 0
  for (const r of results) {
    try {
      await getRezept(r.rezepte_ID)
    } catch {
      /* einzelnes Rezept fehlgeschlagen – weiter */
    }
    onProgress?.(++done, total, r.Name)
  }
}

/** Alle Rezeptbilder (Fotos, keine Icons) herunterladen. */
export async function downloadAllRecipeImages(onProgress?: ProgressCallback): Promise<void> {
  // Zuerst alle Rezepte laden (aus Cache falls vorhanden)
  const { data: results } = await search({ search: '' })
  const total = results.length
  let done = 0
  const allImageUrls: string[] = []

  for (const r of results) {
    try {
      const { data: rezept } = await getRezept(r.rezepte_ID)
      const urls = (rezept as Rezept).Bilder.map((b) => b.Image).filter(Boolean)
      allImageUrls.push(...urls)
    } catch {
      /* ignorieren */
    }
    onProgress?.(++done, total, r.Name)
  }

  // Jetzt alle gesammelten Bild-URLs in den Cache laden
  await preloadImages(allImageUrls, (imgDone, imgTotal) => {
    onProgress?.(imgDone, imgTotal, 'Bilder werden gespeichert…')
  })
}

export { cachedImageCount }

