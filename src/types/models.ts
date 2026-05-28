// Datenmodelle – spiegeln die Antworten deiner bestehenden api.php wider.

export interface SearchResult {
  rezepte_ID: number
  Name: string
  Image: string
  Zeit: string
  Rating: number
  RatingCount: number
}

export interface Zutat {
  ID: number
  Menge: number
  unit: string
  Name: string
  Image: string
  additionalInfo: string
  table: string
}

export interface Bewertung {
  ID: number
  Rezept_ID: number
  Bewertung: number
  Name: string
  Text: string
  Image: string
}

export interface Anmerkung {
  ID: number
  Rezept_ID: number
  Anmerkung: string
}

export interface Bild {
  ID: number
  Rezept_ID: number
  Image: string
}

export interface KalenderEintrag {
  ID?: number
  Kalender_ID?: number
  Datum: string
  Rezept_ID: number | null
  Text: string | null
  Name?: string | null
  Image?: string | null
}

export interface OptionalInfo {
  title: string
  content: string
}

export interface KitchenAppliance {
  ID: number
  Name: string
  Image: string
  recipe_count?: number
}

export interface Rezept {
  ID: number
  Name: string
  Kategorie_ID: number
  Kategorie: string
  KategorieColor: string
  Zubereitung: string
  Portionen: number
  Zeit: number
  Zutaten_JSON: Zutat[]
  ZutatenTables: string[]
  OptionalInfos: string | null
  KitchenAppliances: string
  Bilder: Bild[]
  Bewertungen: Bewertung[]
  Anmerkungen: Anmerkung[]
  Kalender: KalenderEintrag[]
}

export interface Kategorie {
  ID: number
  Name: string
  ColorHex: string
  usage_count?: number
}

export interface EinkaufslisteItem {
  Einkaufsliste_ID: number
  Zutat_ID: number
  Menge: number
  Einheit: string
  Name: string
  Image: string
}

export type ThemeName =
  | 'light'
  | 'dark'
  | 'helloween'
  | 'christmas'
  | 'spring'
  | 'dracula'
  | 'midnight'
