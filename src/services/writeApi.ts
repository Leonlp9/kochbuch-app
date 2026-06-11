import { apiUrl } from '@/config'

// Generische Helfer fuer schreibende (online-pflichtige) Aufrufe.
// Werfen bei Fehlern, damit Views sie sauber abfangen koennen.

async function jsonFetch<T = unknown>(url: string, init?: RequestInit): Promise<T> {
  const res = await fetch(url, init)
  const text = await res.text()
  let data: unknown
  try {
    // Robustes Parsen: PHP-Warnings/Notices können dem JSON vorangestellt sein
    // search() findet das erste '{' oder '[', egal welches zuerst kommt
    const jsonStart = text.search(/[{[]/)
    const jsonText = jsonStart >= 0 ? text.slice(jsonStart) : text
    data = jsonText ? JSON.parse(jsonText) : {}
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
  imageWarning?: string
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

export function deleteEinkaufsliste(id: number | string) {
  return jsonFetch(apiUrl('deleteEinkaufsliste', { id: String(id) }))
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

export function mergeZutaten(keepId: number, mergeIds: number[]) {
  return jsonFetch<{ success: boolean; updated_recipes: number; deleted_ingredients: number }>(
    apiUrl('mergeZutaten', { keep_id: String(keepId), merge_ids: mergeIds.join(',') }),
  )
}

export function fixIngredientUnit(zutatId: number, newUnit: string) {
  return jsonFetch<{ success: boolean }>(
    apiUrl('fixIngredientUnit', { zutat_id: String(zutatId), new_unit: newUnit }),
  )
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

// ---------- KI-Rezepterkennung ----------
export interface AIRecipeIngredient {
  ingredient_id: number
  ingredient_name: string
  quantity: number
  unit: string
  additional_info: string
}
export interface AIRecipeTable {
  table_name: string
  ingredients: AIRecipeIngredient[]
}
export interface AIRecipeResult {
  recipe_name: string
  category_id: number
  prep_time_minutes: number
  portions: number
  instructions: string
  kitchen_appliance_ids: number[]
  optional_infos: { title: string; content: string }[]
  ingredient_tables: AIRecipeTable[]
}
export interface AIAnalysisResponse {
  success: boolean
  recipe?: AIRecipeResult
  error?: string
}

export async function analyzeRecipeWithAI(file: File): Promise<AIAnalysisResponse> {
  const fd = new FormData()
  fd.append('file', file)
  return jsonFetch<AIAnalysisResponse>(apiUrl('geminiAnalyzeRecipe'), { method: 'POST', body: fd })
}

// ---------- KI-Zutaten aus Text extrahieren ----------
export interface ExtractIngredientsResponse {
  success: boolean
  ingredient_tables?: AIRecipeTable[]
  error?: string
}

export async function extractIngredientsFromText(text: string): Promise<ExtractIngredientsResponse> {
  const fd = new FormData()
  fd.append('text', text)
  return jsonFetch<ExtractIngredientsResponse>(apiUrl('geminiExtractIngredients'), { method: 'POST', body: fd })
}

// ---------- KI-Zutaten-Icon generieren & speichern ----------

export async function generateIngredientIcon(name: string): Promise<GeneratedImageResponse> {
  const fd = new FormData()
  fd.append('name', name)
  return jsonFetch<GeneratedImageResponse>(apiUrl('geminiGenerateIngredientIcon'), { method: 'POST', body: fd })
}

export async function saveIngredientIcon(
  zutatId: number,
  imageData: string,
): Promise<{ success: boolean; icon?: string; icon_name?: string }> {
  const fd = new FormData()
  fd.append('zutat_id', String(zutatId))
  fd.append('image_data', imageData)
  return jsonFetch<{ success: boolean; icon?: string; icon_name?: string }>(
    apiUrl('saveIngredientIcon'),
    { method: 'POST', body: fd },
  )
}

// ---------- KI-Bildgenerierung (Modell: gemini-2.5-flash-image) ----------
export interface GeneratedImageResponse {
  success: boolean
  image_data?: string   // base64
  mime_type?: string
  error?: string
}

export async function generateRecipeImage(
  recipeName: string,
  recipeDescription: string,
  mode: 'text' | 'image',
  referenceImage?: File | null,
): Promise<GeneratedImageResponse> {
  const fd = new FormData()
  fd.append('recipe_name', recipeName)
  fd.append('recipe_description', recipeDescription)
  fd.append('mode', mode)
  if (mode === 'image' && referenceImage) fd.append('reference_image', referenceImage)
  return jsonFetch<GeneratedImageResponse>(apiUrl('geminiGenerateImage'), { method: 'POST', body: fd })
}

// ---------- KI-Chat ----------
export interface ChatHistoryItem {
  role: 'user' | 'model'
  content: string
}

export interface ChatReply {
  message: string
  recipe_links: { id: number; name: string }[]
  has_draft: boolean
  recipe_draft?: AIRecipeResult
}

export interface ChatResponse {
  success: boolean
  reply?: ChatReply
  error?: string
}

export function sendChatMessage(
  message: string,
  history: ChatHistoryItem[],
  recipeId?: number | string | null,
): Promise<ChatResponse> {
  return jsonFetch<ChatResponse>(apiUrl('geminiChat'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message, history, recipe_id: recipeId ?? null }),
  })
}

