<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  getKategorien,
  getRezept,
  getKitchenAppliances,
  searchZutaten,
  type ZutatSuche,
} from '@/services/api'
import {
  saveRezept,
  addZutat,
  getImages,
  deleteImage,
  analyzeRecipeWithAI,
  type ServerImage,
} from '@/services/writeApi'
import { isOnline } from '@/services/network'
import { cachedSrc } from '@/services/imageCache'
import Modal from '@/components/Modal.vue'
import RichText from '@/components/RichText.vue'
import type { Kategorie, KitchenAppliance } from '@/types/models'

const props = defineProps<{ id?: string }>()
const router = useRouter()
const isEdit = computed(() => props.id != null)

const UNITS = ['g', 'ml', 'Stück', 'Prise', 'TL', 'EL', 'Tasse', 'Packung', 'Bund', 'Dose', 'Paket', 'Becher', 'Scheibe', 'Zehe', 'Zweige', 'Würfel', 'Messerspitze']

interface EditIngredient {
  ID: number
  Menge: number
  unit: string
  Name: string
  Image: string
  additionalInfo: string
  table: string
}
interface OptInfo {
  title: string
  content: string
}

const name = ref('')
const kategorieId = ref('')
const dauer = ref(30)
const portionen = ref(4)
const anleitung = ref('')
const tables = ref<string[]>([''])
const ingredients = ref<EditIngredient[]>([])
const optInfos = ref<OptInfo[]>([])
const selectedAppliances = ref<KitchenAppliance[]>([])

const kategorien = ref<Kategorie[]>([])
const allAppliances = ref<KitchenAppliance[]>([])
const existingImages = ref<ServerImage[]>([])
const newFiles = ref<File[]>([])
const newPreviews = ref<string[]>([])

const saving = ref(false)
const loading = ref(true)
const errorMsg = ref('')

// --- KI-Modus ---
type EditMode = 'choose' | 'manual' | 'ai-upload' | 'ai-analyzing' | 'form'
const editMode = ref<EditMode>('choose')
const aiFile = ref<File | null>(null)
const aiFilePreview = ref('')
const aiError = ref('')
const aiProgress = ref('')
const aiFilledForm = ref(false)

// Für Bild-Lookups bei KI-Zuordnung
const allKnownIngredients = ref<ZutatSuche[]>([])

// --- Modals ---
const showIngredientSearch = ref(false)
const searchTable = ref('')
const ingredientQuery = ref('')
const ingredientResults = ref<ZutatSuche[]>([])
const showNewIngredient = ref(false)
const newIngName = ref('')
const newIngUnit = ref('g')
const showAppliancePicker = ref(false)

function ingredientsOf(table: string) {
  return ingredients.value.filter((z) => z.table === table)
}

onMounted(async () => {
  try {
    const [k, a] = await Promise.all([getKategorien(), getKitchenAppliances()])
    kategorien.value = k.data
    allAppliances.value = a.data
  } catch {
    /* offline */
  }

  // Zutatenliste für KI-Mapping vorladen
  try {
    allKnownIngredients.value = await searchZutaten('')
  } catch { /* ignore */ }

  if (isEdit.value && props.id) {
    try {
      const { data } = await getRezept(props.id)
      name.value = data.Name
      kategorieId.value = String(data.Kategorie_ID)
      dauer.value = data.Zeit
      portionen.value = data.Portionen
      anleitung.value = data.Zubereitung || ''
      tables.value = data.ZutatenTables?.length ? data.ZutatenTables : ['']
      ingredients.value = (data.Zutaten_JSON || []).map((z) => ({
        ID: z.ID,
        Menge: z.Menge,
        unit: z.unit,
        Name: z.Name,
        Image: z.Image,
        additionalInfo: z.additionalInfo || '',
        table: z.table || '',
      }))
      try {
        optInfos.value = JSON.parse(data.OptionalInfos || '[]')
      } catch {
        optInfos.value = []
      }
      try {
        const sel = JSON.parse(data.KitchenAppliances || '[]') as KitchenAppliance[]
        selectedAppliances.value = sel
      } catch {
        selectedAppliances.value = []
      }
      const imgs = await getImages(props.id)
      existingImages.value = imgs
    } catch {
      errorMsg.value = 'Rezept konnte nicht geladen werden (online sein?).'
    }
    editMode.value = 'form'
  } else {
    editMode.value = 'choose'
  }
  loading.value = false
})

// --- KI-Datei ---
function onAiFilePicked(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files?.length) return
  const f = input.files[0]
  aiFile.value = f
  aiError.value = ''
  aiFilePreview.value = f.type.startsWith('image/') ? URL.createObjectURL(f) : ''
  input.value = ''
}

async function startAiAnalysis() {
  if (!aiFile.value) return
  aiError.value = ''
  aiProgress.value = 'Datei wird analysiertâ€¦'
  editMode.value = 'ai-analyzing'

  try {
    const res = await analyzeRecipeWithAI(aiFile.value)
    if (!res.success || !res.recipe) throw new Error(res.error || 'Unbekannter Fehler')

    const r = res.recipe
    name.value = r.recipe_name || ''
    dauer.value = r.prep_time_minutes || 30
    portionen.value = r.portions || 4
    anleitung.value = r.instructions || ''

    if (r.category_id && kategorien.value.some((k) => k.ID === r.category_id)) {
      kategorieId.value = String(r.category_id)
    }

    const newTables: string[] = []
    const newIngredients: EditIngredient[] = []

    for (const tbl of r.ingredient_tables || []) {
      const tableName = tbl.table_name ?? ''
      if (!newTables.includes(tableName)) newTables.push(tableName)
      for (const ing of tbl.ingredients || []) {
        const known = allKnownIngredients.value.find((z) => z.ID === ing.ingredient_id)
        newIngredients.push({
          ID: ing.ingredient_id || 0,
          Menge: ing.quantity || 0,
          unit: ing.unit || '',
          Name: ing.ingredient_name || '',
          Image: known?.Image || '',
          additionalInfo: ing.additional_info || '',
          table: tableName,
        })
      }
    }

    tables.value = newTables.length ? newTables : ['']
    ingredients.value = newIngredients

    // Kuechengeraete zuordnen
    if (r.kitchen_appliance_ids?.length) {
      selectedAppliances.value = allAppliances.value.filter((a) =>
        r.kitchen_appliance_ids.includes(a.ID),
      )
    }

    // Zusatzinfos uebernehmen
    if (r.optional_infos?.length) {
      optInfos.value = r.optional_infos.map((i) => ({ title: i.title, content: i.content }))
    }

    aiFilledForm.value = true
    aiProgress.value = ''
    editMode.value = 'form'
  } catch (e) {
    aiError.value = e instanceof Error ? e.message : 'Fehler bei der KI-Analyse'
    aiProgress.value = ''
    editMode.value = 'ai-upload'
  }
}

// --- Tabellen ---
function addTable() {
  tables.value.push(`Tabelle ${tables.value.length + 1}`)
}
function renameTable(index: number, value: string) {
  const old = tables.value[index]
  tables.value[index] = value
  ingredients.value.forEach((z) => { if (z.table === old) z.table = value })
}
function removeTable(index: number) {
  const t = tables.value[index]
  ingredients.value = ingredients.value.filter((z) => z.table !== t)
  tables.value.splice(index, 1)
  if (tables.value.length === 0) tables.value = ['']
}

// --- Zutaten ---
async function runIngredientSearch() {
  try {
    ingredientResults.value = await searchZutaten(ingredientQuery.value)
  } catch {
    ingredientResults.value = []
  }
}
function openIngredientSearch(table: string) {
  searchTable.value = table
  ingredientQuery.value = ''
  showIngredientSearch.value = true
  runIngredientSearch()
}
function pickIngredient(z: ZutatSuche) {
  ingredients.value.push({
    ID: z.ID, Menge: 0, unit: z.unit, Name: z.Name,
    Image: z.Image, additionalInfo: '', table: searchTable.value,
  })
  showIngredientSearch.value = false
}
function removeIngredient(ing: EditIngredient) {
  ingredients.value = ingredients.value.filter((z) => z !== ing)
}
async function createIngredient() {
  if (!newIngName.value.trim()) return
  try {
    const res = await addZutat(newIngName.value.trim(), newIngUnit.value)
    if (res.ID) {
      ingredients.value.push({
        ID: res.ID, Menge: 0, unit: newIngUnit.value,
        Name: newIngName.value.trim(), Image: '', additionalInfo: '', table: searchTable.value,
      })
    }
    showNewIngredient.value = false
    showIngredientSearch.value = false
    newIngName.value = ''
  } catch (e) {
    errorMsg.value = e instanceof Error ? e.message : 'Fehler beim Anlegen der Zutat'
  }
}

// --- Geräte ---
function toggleAppliance(a: KitchenAppliance) {
  const i = selectedAppliances.value.findIndex((x) => x.ID === a.ID)
  if (i >= 0) selectedAppliances.value.splice(i, 1)
  else selectedAppliances.value.push(a)
}
function isApplianceSelected(a: KitchenAppliance) {
  return selectedAppliances.value.some((x) => x.ID === a.ID)
}

// --- Zusatzinfos ---
function addOptInfo() { optInfos.value.push({ title: '', content: '' }) }
function removeOptInfo(i: number) { optInfos.value.splice(i, 1) }

// --- Bilder ---
function onFilesPicked(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files) return
  for (const f of Array.from(input.files)) {
    newFiles.value.push(f)
    newPreviews.value.push(URL.createObjectURL(f))
  }
  input.value = ''
}
function removeNewFile(i: number) {
  newFiles.value.splice(i, 1)
  newPreviews.value.splice(i, 1)
}
async function removeExistingImage(img: ServerImage) {
  if (!props.id) return
  try {
    await deleteImage(props.id, img.ID)
    existingImages.value = existingImages.value.filter((x) => x.ID !== img.ID)
  } catch {
    errorMsg.value = 'Bild konnte nicht gelÃ¶scht werden'
  }
}

// --- Speichern ---
async function save() {
  errorMsg.value = ''
  if (!isOnline.value) { errorMsg.value = 'Speichern geht nur mit Verbindung zum Server.'; return }
  if (!name.value.trim()) { errorMsg.value = 'Bitte einen Namen eingeben.'; return }
  if (!kategorieId.value) { errorMsg.value = 'Bitte eine Kategorie wählen.'; return }

  saving.value = true
  try {
    const fd = new FormData()
    fd.append('name', name.value.trim())
    fd.append('kategorie', kategorieId.value)
    fd.append('dauer', String(dauer.value))
    fd.append('portionen', String(portionen.value))
    fd.append('anleitung', anleitung.value)
    fd.append('zutaten', JSON.stringify(
      ingredients.value.map((z) => ({ ID: z.ID, Menge: z.Menge, additionalInfo: z.additionalInfo, table: z.table })),
    ))
    fd.append('extraCustomInfos', JSON.stringify(optInfos.value))
    fd.append('kitchenAppliances', JSON.stringify(selectedAppliances.value.map((a) => a.ID)))
    for (const f of newFiles.value) fd.append('bilder[]', f)

    const res = await saveRezept(fd, isEdit.value ? Number(props.id) : undefined)
    if (res.imageWarning) {
      errorMsg.value = 'âš ï¸ ' + res.imageWarning
      setTimeout(() => router.push(`/recipe/${res.ID ?? props.id}`), 3000)
    } else {
      router.push(`/recipe/${res.ID ?? props.id}`)
    }
  } catch (e) {
    errorMsg.value = e instanceof Error ? e.message : 'Speichern fehlgeschlagen'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="container">
    <RouterLink :to="isEdit ? `/recipe/${id}` : '/'" class="back no-print">
      <i class="fa-solid fa-arrow-left"></i> Abbrechen
    </RouterLink>
    <h1>{{ isEdit ? 'Rezept bearbeiten' : 'Neues Rezept' }}</h1>

    <div v-if="!isOnline" class="warn">
      <i class="fa-solid fa-plug-circle-xmark"></i>
      Ohne Serververbindung kannst du nicht speichern. Felder bleiben sichtbar.
    </div>

    <!-- Laden -->
    <div v-if="loading" class="empty"><i class="fa-solid fa-spinner fa-spin"></i></div>

    <!-- â”€â”€ Schritt 1: Modus wählen â”€â”€ -->
    <div v-else-if="editMode === 'choose'" class="mode-choose">
      <p class="mode-hint">Wie mÃ¶chtest du das Rezept anlegen?</p>
      <div class="mode-cards">
        <button class="mode-card" @click="editMode = 'manual'">
          <i class="fa-solid fa-pen-to-square mode-icon"></i>
          <span class="mode-title">Selbst eintragen</span>
          <span class="mode-desc">Alle Felder manuell ausfüllen</span>
        </button>
        <button class="mode-card mode-card--ai" @click="editMode = 'ai-upload'">
          <i class="fa-solid fa-wand-magic-sparkles mode-icon"></i>
          <span class="mode-title">KI ausfüllen lassen</span>
          <span class="mode-desc">PDF oder Foto hochladen – Gemini füllt alles automatisch aus</span>
        </button>
      </div>
    </div>

    <!-- â”€â”€ Schritt 2: KI – Datei hochladen â”€â”€ -->
    <div v-else-if="editMode === 'ai-upload'" class="ai-upload-wrap">
      <div class="ai-info-box">
        <i class="fa-solid fa-circle-info"></i>
        <div>
          <strong>KI-Rezeptanalyse mit Gemini</strong><br />
          Lade ein Bild oder PDF mit dem Rezept hoch. Gemini liest das Dokument und f&uuml;llt alle
          Felder (Name, Kategorie, Zutaten, K&uuml;chenger&auml;te, Zubereitung etc.) automatisch aus.
          Danach kannst du alles pr&uuml;fen, bearbeiten und ein Foto hinzuf&uuml;gen.
        </div>
      </div>

      <label class="ai-drop" :class="{ 'ai-drop--has-file': !!aiFile }">
        <template v-if="!aiFile">
          <i class="fa-solid fa-file-arrow-up ai-drop-icon"></i>
          <span class="ai-drop-label">PDF oder Bild hier ablegen oder klicken</span>
          <span class="ai-drop-hint">PDF, JPG, PNG, WEBP &middot; max. 18 MB</span>
        </template>
        <template v-else>
          <img v-if="aiFilePreview" :src="aiFilePreview" class="ai-preview-img" alt="Vorschau" />
          <div v-else class="ai-file-info">
            <i class="fa-solid fa-file-pdf ai-drop-icon ai-drop-icon--pdf"></i>
            <span class="ai-drop-label">{{ aiFile.name }}</span>
            <span class="ai-drop-hint">{{ (aiFile.size / 1024 / 1024).toFixed(1) }} MB</span>
          </div>
        </template>
        <input
          type="file"
          accept="application/pdf,image/png,image/jpeg,image/webp,image/heic"
          @change="onAiFilePicked"
        />
      </label>

      <p v-if="aiError" class="error-line">
        <i class="fa-solid fa-triangle-exclamation"></i> {{ aiError }}
      </p>

      <div class="ai-actions">
        <button class="btn btn--ghost" @click="editMode = 'choose'">
          <i class="fa-solid fa-arrow-left"></i> Zurück
        </button>
        <button class="btn btn--accent" :disabled="!aiFile" @click="startAiAnalysis">
          <i class="fa-solid fa-wand-magic-sparkles"></i> Rezept analysieren
        </button>
      </div>
    </div>

    <!-- â”€â”€ Schritt 3: KI analysiert â”€â”€ -->
    <div v-else-if="editMode === 'ai-analyzing'" class="ai-analyzing">
      <div class="ai-analyzing-inner">
        <i class="fa-solid fa-spinner fa-spin ai-spinner"></i>
        <p class="ai-analyzing-text">{{ aiProgress || 'Gemini analysiert das Rezept\u2026' }}</p>
        <p class="ai-analyzing-sub">
          Das Dokument wird gelesen und alle Felder werden automatisch ausgef&uuml;llt.<br />
          Das kann einen Moment dauern.
        </p>
      </div>
    </div>

    <!-- â”€â”€ Formular â”€â”€ -->
    <form v-else class="form" @submit.prevent="save">

      <!-- KI-Banner -->
      <div v-if="aiFilledForm" class="ai-filled-banner">
        <i class="fa-solid fa-circle-check"></i>
        <div>
          <strong>Gemini hat das Rezept ausgef&uuml;llt!</strong><br />
          Bitte alle Felder pr&uuml;fen &ndash; besonders die Zutaten-Zuordnungen.
          F&uuml;ge noch ein Foto hinzu und klicke auf &quot;Rezept anlegen&quot;.
        </div>
      </div>

      <!-- Name -->
      <div class="field">
        <label>Name</label>
        <input v-model="name" type="text" placeholder="Rezeptname" />
      </div>

      <div class="grid2">
        <div class="field">
          <label>Kategorie</label>
          <select v-model="kategorieId" class="select">
            <option value="" disabled>Bitte wählen</option>
            <option v-for="k in kategorien" :key="k.ID" :value="String(k.ID)">{{ k.Name }}</option>
          </select>
        </div>
        <div class="grid2 sub">
          <div class="field">
            <label>Dauer (Min.)</label>
            <input v-model.number="dauer" type="number" min="0" step="5" />
          </div>
          <div class="field">
            <label>Portionen</label>
            <input v-model.number="portionen" type="number" min="1" step="1" />
          </div>
        </div>
      </div>

      <!-- Geräte -->
      <div class="field">
        <label>Küchengeräte</label>
        <div class="chips">
          <span v-for="a in selectedAppliances" :key="a.ID" class="chip on" @click="toggleAppliance(a)">
            {{ a.Name }} <i class="fa-solid fa-xmark"></i>
          </span>
          <button type="button" class="chip add" @click="showAppliancePicker = true">
            <i class="fa-solid fa-plus"></i> Gerät
          </button>
        </div>
      </div>

      <!-- Zusatzinfos -->
      <div class="field">
        <label>Zusätzliche Infos (z. B. Kalorien)</label>
        <div v-for="(info, i) in optInfos" :key="i" class="optinfo">
          <input v-model="info.title" placeholder="Titel" />
          <input v-model="info.content" placeholder="Inhalt" />
          <button type="button" class="icon-btn danger" @click="removeOptInfo(i)">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
        <button type="button" class="btn btn--ghost small" @click="addOptInfo">
          <i class="fa-solid fa-plus"></i> Info hinzufügen
        </button>
      </div>

      <!-- Zutaten -->
      <h2 class="sec">Zutaten</h2>
      <div v-for="(table, ti) in tables" :key="ti" class="ztable">
        <div class="ztable-head">
          <input
            :value="table"
            placeholder="Tabellenname (optional)"
            @input="renameTable(ti, ($event.target as HTMLInputElement).value)"
          />
          <button v-if="tables.length > 1" type="button" class="icon-btn danger" @click="removeTable(ti)">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>

        <div v-for="ing in ingredientsOf(table)" :key="ing.ID + '-' + ing.table" class="zrow">
          <img :src="cachedSrc(ing.Image) || ''" :alt="ing.Name" onerror="this.style.visibility='hidden'" />
          <span class="zname">{{ ing.Name }}</span>
          <div class="zinputs">
            <input v-model.number="ing.Menge" type="number" min="0" step="0.1" class="zmenge" />
            <span class="zunit">{{ ing.unit }}</span>
            <input v-model="ing.additionalInfo" placeholder="Info" class="zinfo" />
          </div>
          <button type="button" class="icon-btn danger" @click="removeIngredient(ing)">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <button type="button" class="btn btn--ghost small" @click="openIngredientSearch(table)">
          <i class="fa-solid fa-plus"></i> Zutat hinzufügen
        </button>
      </div>
      <button type="button" class="btn btn--ghost small" @click="addTable">
        <i class="fa-solid fa-table-list"></i> Neue Tabelle
      </button>

      <!-- Zubereitung -->
      <h2 class="sec">Zubereitung</h2>
      <RichText v-model="anleitung" placeholder="Schritt für Schrittâ€¦" />

      <!-- Bilder -->
      <h2 class="sec">Bilder</h2>
      <label class="upload">
        <i class="fa-solid fa-upload"></i> Bilder auswählen
        <input type="file" accept="image/png,image/jpeg,image/webp" multiple @change="onFilesPicked" />
      </label>
      <div v-if="newPreviews.length || existingImages.length" class="thumbs">
        <div v-for="img in existingImages" :key="'e' + img.ID" class="thumb">
          <img :src="img.Image.startsWith('http') ? img.Image : ''" alt="" />
          <button type="button" class="thumb-del" @click="removeExistingImage(img)">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
        <div v-for="(src, i) in newPreviews" :key="'n' + i" class="thumb">
          <img :src="src" alt="" />
          <span class="thumb-new">neu</span>
          <button type="button" class="thumb-del" @click="removeNewFile(i)">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>

      <p v-if="errorMsg" class="error-line">{{ errorMsg }}</p>

      <div class="actions no-print">
        <button type="submit" class="btn btn--accent btn--block" :disabled="saving || !isOnline">
          <i v-if="saving" class="fa-solid fa-spinner fa-spin"></i>
          <i v-else class="fa-solid fa-floppy-disk"></i>
          {{ saving ? 'Speichernâ€¦' : isEdit ? 'Speichern' : 'Rezept anlegen' }}
        </button>
      </div>
    </form>

    <!-- Modal: Zutat suchen -->
    <Modal v-if="showIngredientSearch" title="Zutat hinzufügen" @close="showIngredientSearch = false">
      <input v-model="ingredientQuery" class="search-in" placeholder="Zutat suchenâ€¦" @input="runIngredientSearch" />
      <div class="ing-results">
        <button
          v-for="z in ingredientResults"
          :key="z.ID"
          type="button"
          class="ing-result"
          @click="pickIngredient(z)"
        >
          <img :src="z.Image" :alt="z.Name" onerror="this.style.visibility='hidden'" />
          <span>{{ z.Name }}</span>
          <small>{{ z.unit }}</small>
        </button>
      </div>
      <template #footer>
        <button class="btn btn--ghost" @click="showNewIngredient = true">
          <i class="fa-solid fa-plus"></i> Neue Zutat anlegen
        </button>
      </template>
    </Modal>

    <!-- Modal: neue Zutat -->
    <Modal v-if="showNewIngredient" title="Neue Zutat" @close="showNewIngredient = false">
      <div class="field">
        <label>Name</label>
        <input v-model="newIngName" placeholder="z. B. Mehl" />
      </div>
      <div class="field">
        <label>Einheit</label>
        <select v-model="newIngUnit" class="select">
          <option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
        </select>
      </div>
      <template #footer>
        <button class="btn btn--ghost" @click="showNewIngredient = false">Abbrechen</button>
        <button class="btn btn--accent" @click="createIngredient">Anlegen</button>
      </template>
    </Modal>

    <!-- Modal: Geräte -->
    <Modal v-if="showAppliancePicker" title="Küchengeräte" @close="showAppliancePicker = false">
      <div class="appliance-grid">
        <button
          v-for="a in allAppliances"
          :key="a.ID"
          type="button"
          class="appliance"
          :class="{ on: isApplianceSelected(a) }"
          @click="toggleAppliance(a)"
        >
          <img :src="a.Image" :alt="a.Name" onerror="this.style.visibility='hidden'" />
          <span>{{ a.Name }}</span>
        </button>
      </div>
      <template #footer>
        <button class="btn btn--accent" @click="showAppliancePicker = false">Fertig</button>
      </template>
    </Modal>
  </div>
</template>

<style scoped>
/* â”€â”€ Modus-Auswahl â”€â”€ */
.mode-choose { margin-top: var(--sp-6); }
.mode-hint { color: var(--ink-soft); font-size: var(--fs-sm); margin-bottom: var(--sp-4); }
.mode-cards {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-4);
}
@media (max-width: 600px) { .mode-cards { grid-template-columns: 1fr; } }
.mode-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-6) var(--sp-4);
  border: 2px solid var(--line);
  border-radius: var(--r-lg);
  background: var(--surface);
  cursor: pointer;
  text-align: center;
  transition: border-color 0.15s, background 0.15s;
}
.mode-card:hover { border-color: var(--accent); background: var(--accent-soft); }
.mode-card--ai { border-color: var(--accent); }
.mode-icon { font-size: 2.2rem; color: var(--accent); }
.mode-title { font-weight: 700; font-size: var(--fs-body); color: var(--ink); }
.mode-desc { font-size: var(--fs-sm); color: var(--ink-soft); }

/* â”€â”€ KI Upload â”€â”€ */
.ai-upload-wrap { margin-top: var(--sp-4); display: grid; gap: var(--sp-4); }
.ai-info-box {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  background: var(--accent-soft);
  color: var(--accent-strong);
  padding: var(--sp-3) var(--sp-4);
  border-radius: var(--r-md);
  font-size: var(--fs-sm);
}
.ai-info-box i { flex-shrink: 0; margin-top: 2px; }
.ai-drop {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--sp-3);
  padding: var(--sp-6);
  border: 2px dashed var(--line);
  border-radius: var(--r-lg);
  cursor: pointer;
  text-align: center;
  min-height: 200px;
  background: var(--surface-2);
  transition: border-color 0.15s, background 0.15s;
}
.ai-drop:hover { border-color: var(--accent); background: var(--accent-soft); }
.ai-drop--has-file { border-color: var(--accent); border-style: solid; }
.ai-drop input { display: none; }
.ai-drop-icon { font-size: 2.5rem; color: var(--ink-soft); }
.ai-drop-icon--pdf { color: var(--danger); }
.ai-drop-label { font-weight: 600; font-size: var(--fs-body); color: var(--ink); }
.ai-drop-hint { font-size: var(--fs-sm); color: var(--ink-faint); }
.ai-preview-img { max-width: 100%; max-height: 240px; border-radius: var(--r-md); object-fit: contain; }
.ai-file-info { display: flex; flex-direction: column; align-items: center; gap: var(--sp-2); }
.ai-actions { display: flex; gap: var(--sp-3); justify-content: flex-end; }

/* â”€â”€ KI analysiert â”€â”€ */
.ai-analyzing { display: flex; align-items: center; justify-content: center; min-height: 300px; }
.ai-analyzing-inner { display: flex; flex-direction: column; align-items: center; gap: var(--sp-3); text-align: center; }
.ai-spinner { font-size: 3rem; color: var(--accent); }
.ai-analyzing-text { font-weight: 600; font-size: var(--fs-body); }
.ai-analyzing-sub { font-size: var(--fs-sm); color: var(--ink-soft); }

/* â”€â”€ KI-Banner â”€â”€ */
.ai-filled-banner {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  background: var(--accent-soft);
  color: var(--accent-strong);
  padding: var(--sp-3) var(--sp-4);
  border-radius: var(--r-md);
  font-size: var(--fs-sm);
}
.ai-filled-banner i { flex-shrink: 0; margin-top: 2px; font-size: 1.1rem; }

/* â”€â”€ Allgemein â”€â”€ */
.back {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  color: var(--ink-soft);
  font-weight: 600;
  font-size: var(--fs-sm);
  margin-bottom: var(--sp-3);
}
.warn {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  background: var(--danger-soft);
  color: var(--danger);
  padding: var(--sp-3) var(--sp-4);
  border-radius: var(--r-md);
  margin: var(--sp-4) 0;
  font-size: var(--fs-sm);
}
.form { display: grid; gap: var(--sp-4); margin-top: var(--sp-4); }
.field { display: grid; gap: var(--sp-2); }
.field > label { font-weight: 600; font-size: var(--fs-sm); color: var(--ink-soft); }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: var(--sp-4); }
.grid2.sub { gap: var(--sp-3); }
@media (max-width: 600px) { .grid2 { grid-template-columns: 1fr; } }
input, .select {
  width: 100%;
  height: 48px;
  border: 1.5px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface);
  color: var(--ink);
  padding: 0 var(--sp-3);
  outline: none;
}
input:focus, .select:focus { border-color: var(--accent); }
.sec { margin-top: var(--sp-4); padding-bottom: var(--sp-2); border-bottom: 1px solid var(--line); }
.chips { display: flex; flex-wrap: wrap; gap: var(--sp-2); }
.chip {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  height: 38px;
  padding: 0 var(--sp-3);
  border-radius: var(--r-full);
  border: 1.5px solid var(--line);
  background: var(--surface);
  font-size: var(--fs-sm);
  font-weight: 600;
  cursor: pointer;
}
.chip.on { background: var(--accent-soft); color: var(--accent-strong); border-color: transparent; }
.chip.add { color: var(--accent); }
.optinfo { display: grid; grid-template-columns: 1fr 1fr 44px; gap: var(--sp-2); margin-bottom: var(--sp-2); }
.btn.small { min-height: 42px; width: fit-content; }
.ztable { background: var(--surface-2); border-radius: var(--r-lg); padding: var(--sp-3); display: grid; gap: var(--sp-2); }
.ztable-head { display: grid; grid-template-columns: 1fr 44px; gap: var(--sp-2); }
.zrow {
  display: grid;
  grid-template-columns: 28px minmax(80px, 1.4fr) 1fr 40px;
  gap: var(--sp-2);
  align-items: center;
  background: var(--surface);
  border-radius: var(--r-md);
  padding: var(--sp-2);
}
.zrow img { width: 28px; height: 28px; object-fit: contain; }
.zname { font-weight: 600; font-size: var(--fs-sm); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.zinputs { display: flex; align-items: center; gap: var(--sp-2); min-width: 0; }
.zmenge { flex: 0 0 70px; width: 70px; height: 40px; }
.zunit { flex: 0 0 auto; font-size: var(--fs-sm); color: var(--ink-soft); white-space: nowrap; }
.zinfo { flex: 1 1 0; min-width: 60px; height: 40px; }
@media (max-width: 600px) {
  .zrow {
    grid-template-columns: 28px 1fr 40px;
    grid-template-areas: 'img name del' 'inputs inputs inputs';
  }
  .zrow img { grid-area: img; }
  .zname { grid-area: name; }
  .icon-btn { grid-area: del; }
  .zinputs { grid-area: inputs; flex-wrap: nowrap; }
  .zmenge { flex: 0 0 80px; width: 80px; }
  .zinfo { flex: 1 1 0; min-width: 0; }
}
.icon-btn { width: 40px; height: 40px; border: none; border-radius: var(--r-sm); background: var(--surface-2); color: var(--ink-soft); }
.icon-btn.danger:hover { background: var(--danger-soft); color: var(--danger); }
.upload {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-3) var(--sp-4);
  border: 1.5px dashed var(--line);
  border-radius: var(--r-md);
  cursor: pointer;
  font-weight: 600;
  color: var(--ink-soft);
  width: fit-content;
  height: auto;
}
.upload input { display: none; }
.thumbs { display: flex; flex-wrap: wrap; gap: var(--sp-3); }
.thumb { position: relative; width: 96px; height: 96px; border-radius: var(--r-md); overflow: hidden; background: var(--surface-2); }
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.thumb-del {
  position: absolute; top: 4px; right: 4px; width: 28px; height: 28px;
  border: none; border-radius: var(--r-full); background: rgba(20,16,14,.65); color: #fff;
}
.thumb-new {
  position: absolute; bottom: 4px; left: 4px; font-size: var(--fs-xs);
  background: var(--accent); color: var(--on-accent); padding: 2px 6px;
  border-radius: var(--r-full); font-weight: 700;
}
.error-line { color: var(--danger); font-weight: 600; font-size: var(--fs-sm); }
.actions { margin-top: var(--sp-3); }
.search-in { margin-bottom: var(--sp-3); }
.ing-results {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: var(--sp-2);
  max-height: 50vh;
  overflow-y: auto;
}
.ing-result { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: var(--sp-3); border: 1px solid var(--line); border-radius: var(--r-md); background: var(--surface); text-align: center; }
.ing-result img { width: 34px; height: 34px; object-fit: contain; }
.ing-result small { color: var(--ink-faint); }
.appliance-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: var(--sp-2); }
.appliance { display: flex; flex-direction: column; align-items: center; gap: var(--sp-2); padding: var(--sp-3); border: 2px solid var(--line); border-radius: var(--r-md); background: var(--surface); }
.appliance.on { border-color: var(--accent); background: var(--accent-soft); }
.appliance img { width: 48px; height: 48px; object-fit: contain; }
</style>

