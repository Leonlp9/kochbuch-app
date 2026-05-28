import { get, set, del, keys } from 'idb-keyval'

// Duenner Wrapper um IndexedDB fuer den Offline-Datencache.
// Schluessel-Konvention:  "rezept:42", "search:<hash>", "kategorien", ...

export async function cacheGet<T>(key: string): Promise<T | undefined> {
  try {
    return (await get(key)) as T | undefined
  } catch {
    return undefined
  }
}

export async function cacheSet<T>(key: string, value: T): Promise<void> {
  try {
    await set(key, value)
  } catch {
    /* Speicher voll / nicht verfuegbar – still ignorieren */
  }
}

export async function cacheDel(key: string): Promise<void> {
  try {
    await del(key)
  } catch {
    /* ignore */
  }
}

export async function cacheKeys(): Promise<string[]> {
  try {
    return (await keys()) as string[]
  } catch {
    return []
  }
}
