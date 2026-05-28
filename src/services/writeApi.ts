import { apiUrl } from '@/config'

// Generische Helfer fuer schreibende (online-pflichtige) Aufrufe.
// Werfen bei Fehlern, damit Views sie sauber abfangen koennen.

async function jsonFetch<T = unknown>(url: string, init?: RequestInit): Promise<T> {
  const res = await fetch(url, init)
  const text = await res.text()
  let data: unknown
  try {
    data = text ? JSON.parse(text) : {}
  } catch {
    throw new Error('Ungültige Antwort vom Server')
  }
  if (data && typeof data === 'object' && 'error' in data && (data as { error?: unknown }).error) {
    throw new Error(String((data as { error: unknown }).error))
  }
  return data as T
}

interface IdResponse {
  success: boolean
  ID?: number
}

// ---------- Rezepte ----------
export function saveRezept(form: FormData, editId?: number): Promise<IdResponse> {
  const url = editId
    ? apiUrl('addRezept', { edit: 'true', rezept: String(editId), app: '1' })
    : apiUrl('addRezept', { app: '1' })
  return jsonFetch<IdResponse>(url, { method: 'POST', body: form })
}

export function deleteRezept(id: number | string) {
  return jsonFetch(apiUrl('deleteRezept', { id: String(id) }))
}

export interface ServerImage {
  ID: number
  Rezept_ID: number
  Image: string
}
export function getImages(rezeptId: number | string) {
  return jsonFetch<ServerImage[]>(apiUrl('getImages', { rezept_id: String(rezeptId) }))
}
export function deleteImage(rezeptId: number | string, imageId: number) {
  return jsonFetch(apiUrl('deleteImage', { rezept_id: String(rezeptId), image: String(imageId) }))
}

// ---------- Bewertungen ----------
export function addEvaluation(rezept: number, rating: number, name: string, text: string) {
  return jsonFetch(apiUrl('addEvaluation', { rezept: String(rezept), rating: String(rating), name, text }))
}
export function editEvaluation(evalId: number, rating: number, name: string, text: string) {
  // Hinweis: Server nutzt hier 'rezept' als ID der Bewertung.
  return jsonFetch(apiUrl('editEvaluation', { rezept: String(evalId), rating: String(rating), name, text }))
}
export function deleteEvaluation(id: number) {
  return jsonFetch(apiUrl('deleteEvaluation', { id: String(id) }))
}

// ---------- Anmerkung ----------
export function saveAnmerkung(rezept: number, html: string) {
  // Als JS-String-Literal speichern (kompatibel zum alten Frontend-Rendering).
  return jsonFetch(apiUrl('anmerkung', { rezept: String(rezept), text: JSON.stringify(html) }))
}

// ---------- Kalender ----------
export function addKalender(date: string, info: string, rezept?: number) {
  const params: Record<string, string> = { date, info }
  if (rezept != null) params.rezept = String(rezept)
  return jsonFetch(apiUrl('addKalender', params))
}
export function updateKalender(id: number, opts: { text?: string; date?: string }) {
  const params: Record<string, string> = { id: String(id) }
  if (opts.text != null) params.text = opts.text
  if (opts.date != null) params.date = opts.date
  return jsonFetch(apiUrl('updateKalender', params))
}
export function deleteKalender(id: number) {
  return jsonFetch(apiUrl('deleteKalender', { id: String(id) }))
}

// ---------- Einkaufsliste (Bring) ----------
export function addToBring(rezeptId: number) {
  return jsonFetch<{ success: boolean; message?: string; error?: string }>(
    apiUrl('bringApiAddMyRecipeIngredients', { recipe_id: String(rezeptId) }),
  )
}

// ---------- Kategorien ----------
export function addKategorie(name: string, color: string) {
  return jsonFetch<IdResponse>(apiUrl('addKategorie', { name, color }))
}
export function editKategorie(id: number, name: string, color: string) {
  return jsonFetch(apiUrl('editKategorie', { id: String(id), name, color }))
}
export function deleteKategorie(id: number) {
  return jsonFetch(apiUrl('deleteKategorie', { id: String(id) }))
}

// ---------- Zutaten ----------
export function addZutat(name: string, unit: string) {
  return jsonFetch<IdResponse>(apiUrl('addZutat', { name, unit }))
}
export function editZutat(id: number, name: string, unit: string, icon?: File | null) {
  const fd = new FormData()
  fd.append('id', String(id))
  fd.append('name', name)
  fd.append('unit', unit)
  if (icon) fd.append('icon', icon)
  return jsonFetch(apiUrl('editZutat'), { method: 'POST', body: fd })
}
export function deleteZutat(id: number) {
  return jsonFetch(apiUrl('deleteZutat', { id: String(id) }))
}

// ---------- Küchengeräte ----------
export function addAppliance(name: string, image: File) {
  const fd = new FormData()
  fd.append('Name', name)
  fd.append('Image', image)
  return jsonFetch<IdResponse>(apiUrl('addKitchenAppliance'), { method: 'POST', body: fd })
}
export function updateAppliance(id: number, name: string, image?: File | null) {
  const fd = new FormData()
  fd.append('id', String(id))
  fd.append('Name', name)
  if (image) fd.append('Image', image)
  return jsonFetch(apiUrl('updateKitchenAppliance'), { method: 'POST', body: fd })
}
export function deleteAppliance(id: number) {
  return jsonFetch(apiUrl('deleteKitchenAppliance', { id: String(id) }))
}
