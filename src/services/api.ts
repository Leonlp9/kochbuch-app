import { apiUrl, mediaUrl } from '@/config'
import { getJSON, type FetchResult } from './http'
import type {
  SearchResult,
  Rezept,
  Kategorie,
  KalenderEintrag,
  KitchenAppliance,
  EinkaufslisteItem,
} from '@/types/models'

// Hilfsfunktion: stabilen Cache-Key aus Suchparametern bauen.
function hashParams(params: Record<string, string>): string {
  return Object.entries(params)
    .filter(([, v]) => v !== '' && v != null)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([k, v]) => `${k}=${v}`)
    .join('&')
}

export interface SearchOptions {
  search?: string
  order?: string
  zeit?: string
  kategorie?: string
  random?: boolean
  neueste?: boolean
  last_visit?: boolean
  kitchenAppliances?: string[]
  blacklistIngredients?: string
  whitelistIngredients?: string
  profileID?: string
}

export async function search(
  opts: SearchOptions = {},
): Promise<FetchResult<SearchResult[]>> {
  const params: Record<string, string> = { search: opts.search ?? '' }
  if (opts.order) params.order = opts.order
  if (opts.zeit) params.zeit = opts.zeit
  if (opts.kategorie) params.kategorie = opts.kategorie
  if (opts.random) params.random = 'true'
  if (opts.neueste) params.neueste = 'true'
  if (opts.last_visit) params.last_visit = 'true'
  if (opts.profileID) params.profileID = opts.profileID
  if (opts.blacklistIngredients) params.blacklistIngredients = opts.blacklistIngredients
  if (opts.whitelistIngredients) params.whitelistIngredients = opts.whitelistIngredients

  const url = apiUrl('search', params)
  const key = `search:${hashParams(params)}`
  const res = await getJSON<SearchResult[]>(url, key)
  res.data = res.data.map((r) => ({ ...r, Image: mediaUrl(r.Image) }))
  return res
}

export async function getRezept(id: number | string): Promise<FetchResult<Rezept>> {
  const url = apiUrl('getRezept', { id: String(id), zutaten: '' })
  const res = await getJSON<Rezept[]>(url, `rezept:${id}`)
  const rezept = res.data[0]
  // Medienpfade absolut machen
  rezept.Bilder = (rezept.Bilder ?? []).map((b) => ({ ...b, Image: mediaUrl(b.Image) }))
  rezept.Zutaten_JSON = (rezept.Zutaten_JSON ?? []).map((z) => ({
    ...z,
    Image: mediaUrl(z.Image),
  }))
  return { data: rezept, fromCache: res.fromCache }
}

export async function getKategorien(
  includeCount = false,
): Promise<FetchResult<Kategorie[]>> {
  const params: Record<string, string> = includeCount ? { includeCount: 'true' } : {}
  return getJSON<Kategorie[]>(apiUrl('getKategorien', params), 'kategorien')
}

export async function getKalender(
  showPast = false,
): Promise<FetchResult<KalenderEintrag[]>> {
  const params: Record<string, string> = showPast ? { showPast: 'true' } : {}
  const res = await getJSON<KalenderEintrag[]>(
    apiUrl('getKalender', params),
    `kalender:${showPast}`,
  )
  res.data = res.data.map((k) => ({ ...k, Image: k.Image ? mediaUrl(k.Image) : null }))
  return res
}

export interface ZutatSuche {
  ID: number
  Name: string
  Image: string
  unit: string
}

export async function searchZutaten(name: string): Promise<ZutatSuche[]> {
  const res = await getJSON<ZutatSuche[]>(apiUrl('getZutaten', { name }), `zutaten:${name}`)
  return res.data.map((z) => ({ ...z, Image: mediaUrl(z.Image) }))
}

export async function getKitchenAppliances(): Promise<FetchResult<KitchenAppliance[]>> {
  const res = await getJSON<KitchenAppliance[]>(apiUrl('getKitchenAppliances'), 'appliances')
  res.data = res.data.map((a) => ({ ...a, Image: mediaUrl(a.Image) }))
  return res
}

export async function getEinkaufsliste(): Promise<FetchResult<EinkaufslisteItem[]>> {
  const res = await getJSON<EinkaufslisteItem[]>(apiUrl('getEinkaufsliste'), 'einkaufsliste')
  res.data = res.data.map((item) => ({ ...item, Image: mediaUrl(item.Image) }))
  return res
}

