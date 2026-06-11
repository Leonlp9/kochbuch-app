<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getRezept } from '@/services/api'
import { apiUrl, mediaUrl } from '@/config'
import { isOnline } from '@/services/network'
import {
  deleteRezept,
  addEvaluation,
  editEvaluation,
  deleteEvaluation,
  saveAnmerkung,
  addKalender,
} from '@/services/writeApi'
import StarRating from '@/components/StarRating.vue'
import Modal from '@/components/Modal.vue'
import RichText from '@/components/RichText.vue'
import { cachedSrc } from '@/services/imageCache'
import type { Rezept, OptionalInfo, KitchenAppliance, Bewertung } from '@/types/models'
import { Capacitor } from '@capacitor/core'
import { Filesystem, Directory } from '@capacitor/filesystem'
import { Share } from '@capacitor/share'

const props = defineProps<{ id: string }>()
const router = useRouter()

const rezept = ref<Rezept | null>(null)
const activeImageIndex = ref(0)
const loading = ref(true)
const error = ref(false)
const portionen = ref(4)
const checked = ref<Set<string>>(new Set())
const toast = ref('')

const basePortionen = computed(() => rezept.value?.Portionen ?? 1)

const zeitLabel = computed(() => {
  const t = rezept.value?.Zeit ?? 0
  const h = Math.floor(t / 60)
  const m = t % 60
  return [h > 0 ? `${h} h` : '', m > 0 ? `${m} min` : ''].filter(Boolean).join(' ')
})

const optionalInfos = computed<OptionalInfo[]>(() => {
  try {
    return JSON.parse(rezept.value?.OptionalInfos || '[]')
  } catch {
    return []
  }
})

const appliances = computed<KitchenAppliance[]>(() => {
  try {
    const arr = JSON.parse(rezept.value?.KitchenAppliances || '[]')
    return Array.isArray(arr)
      ? arr.map((a: KitchenAppliance) => ({ ...a, Image: mediaUrl(a.Image) }))
      : []
  } catch {
    return []
  }
})

const avgRating = computed(() => {
  const b = rezept.value?.Bewertungen ?? []
  if (!b.length) return 0
  return b.reduce((s, x) => s + x.Bewertung, 0) / b.length
})

function scaled(menge: number): string {
  const v = (menge * portionen.value) / basePortionen.value
  return v.toLocaleString('de-DE', { maximumFractionDigits: 2 })
}

function ingredientsOf(table: string) {
  return (rezept.value?.Zutaten_JSON ?? []).filter((z) => z.table === table)
}

function stripHtml(html: string): string {
  return html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n')
    .replace(/<\/li>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&nbsp;/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

const printing = ref(false)

async function printPage() {
  if (!rezept.value || printing.value) return

  // Desktop / Browser: klassischer Druck-Dialog
  if (!Capacitor.isNativePlatform()) {
    window.print()
    return
  }

  // Native App (Android): PDF via jsPDF erzeugen → Temp-Datei → natives Share-Sheet
  printing.value = true
  showToast('PDF wird erstellt…')
  try {
    const { jsPDF } = await import('jspdf')
    const doc = new jsPDF({ unit: 'mm', format: 'a4' })
    const r = rezept.value
    const pageW = doc.internal.pageSize.getWidth()
    const margin = 15
    const contentW = pageW - margin * 2
    let y = margin

    const addPage = () => { doc.addPage(); y = margin }
    const checkY = (needed = 10) => {
      if (y + needed > doc.internal.pageSize.getHeight() - margin) addPage()
    }

    // Titel
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(20)
    const titleLines = doc.splitTextToSize(r.Name, contentW) as string[]
    doc.text(titleLines, margin, y)
    y += titleLines.length * 8 + 4

    // Infos
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(10)
    doc.setTextColor(120, 120, 120)
    const zeitStr = (() => {
      const t = r.Zeit ?? 0
      const h = Math.floor(t / 60)
      const m = t % 60
      return [h > 0 ? `${h} h` : '', m > 0 ? `${m} min` : ''].filter(Boolean).join(' ')
    })()
    doc.text(`${r.Kategorie}  ·  ${portionen.value} Portionen  ·  ${zeitStr}`, margin, y)
    y += 6

    // Trennlinie
    doc.setDrawColor(200, 200, 200)
    doc.line(margin, y, pageW - margin, y)
    y += 6

    // Bild (erstes Rezeptfoto) unterhalb der Infos einfügen
    if (r.Bilder.length > 0) {
      try {
        const imgUrl = mediaUrl(r.Bilder[0].Image)
        const imgResp = await fetch(imgUrl)
        const imgBlob = await imgResp.blob()
        const imgBase64 = await new Promise<string>((resolve, reject) => {
          const reader = new FileReader()
          reader.onloadend = () => resolve(reader.result as string)
          reader.onerror = reject
          reader.readAsDataURL(imgBlob)
        })
        // Seitenverhältnis ermitteln
        const imgEl = new Image()
        await new Promise<void>((res, rej) => { imgEl.onload = () => res(); imgEl.onerror = rej; imgEl.src = imgBase64 })
        const ratio = imgEl.naturalWidth / imgEl.naturalHeight
        const imgW = contentW
        const imgH = Math.min(imgW / ratio, 80) // max 80 mm Höhe
        const fmt = (imgBase64.match(/data:image\/(\w+);/) ?? [])[1]?.toUpperCase() ?? 'JPEG'
        checkY(imgH + 4)
        doc.addImage(imgBase64, fmt, margin, y, imgW, imgH)
        y += imgH + 6
      } catch { /* Bild nicht verfügbar – ignorieren */ }
    }
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(14)
    doc.setTextColor(40, 40, 40)
    checkY(12)
    doc.text('Zutaten', margin, y)
    y += 7

    for (const table of r.ZutatenTables) {
      if (table) {
        checkY(8)
        doc.setFont('helvetica', 'bolditalic')
        doc.setFontSize(11)
        doc.setTextColor(80, 80, 80)
        doc.text(table, margin, y)
        y += 5
      }
      for (const z of (r.Zutaten_JSON ?? []).filter((z) => z.table === table)) {
        checkY(6)
        doc.setFont('helvetica', 'normal')
        doc.setFontSize(10)
        doc.setTextColor(40, 40, 40)
        const menge = scaled(z.Menge)
        const zeile = `${menge} ${z.unit} ${z.Name}${z.additionalInfo ? ' (' + z.additionalInfo + ')' : ''}`
        const lines = doc.splitTextToSize(`• ${zeile}`, contentW) as string[]
        doc.text(lines, margin + 2, y)
        y += lines.length * 5
      }
      y += 2
    }

    // Zubereitung
    checkY(14)
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(14)
    doc.setTextColor(40, 40, 40)
    doc.text('Zubereitung', margin, y)
    y += 7
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(10)
    doc.setTextColor(40, 40, 40)
    const zuberLines = doc.splitTextToSize(stripHtml(r.Zubereitung), contentW) as string[]
    for (const line of zuberLines) {
      checkY(6)
      doc.text(line, margin, y)
      y += 5
    }

    // Anmerkungen
    const anm = r.Anmerkungen.filter((a) => a.Anmerkung)
    if (anm.length) {
      y += 4
      checkY(12)
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(12)
      doc.setTextColor(40, 40, 40)
      doc.text('Anmerkungen', margin, y)
      y += 6
      doc.setFont('helvetica', 'normal')
      doc.setFontSize(10)
      for (const a of anm) {
        const txt = doc.splitTextToSize(stripHtml(decodeAnmerkung(a.Anmerkung)), contentW) as string[]
        for (const line of txt) {
          checkY(5)
          doc.text(line, margin, y)
          y += 5
        }
      }
    }

    // PDF als Base64 → temporäre Datei im Cache → natives Share-Sheet
    const base64 = doc.output('datauristring').split(',')[1]
    const filename = `${r.Name.replace(/[^a-z0-9äöüß\s]/gi, '').trim() || 'Rezept'}.pdf`

    const fileResult = await Filesystem.writeFile({
      path: filename,
      data: base64,
      directory: Directory.Cache,
    })

    await Share.share({
      title: r.Name,
      url: fileResult.uri,
      dialogTitle: 'Rezept als PDF teilen oder speichern',
    })

    // Temp-Datei nach 10 Sekunden aufräumen
    setTimeout(() => {
      Filesystem.deleteFile({ path: filename, directory: Directory.Cache }).catch(() => {})
    }, 10_000)

  } catch (e) {
    // Share-Sheet vom Nutzer geschlossen → kein Fehler anzeigen
    const msg = e instanceof Error ? e.message : ''
    if (!msg.toLowerCase().includes('cancel') && !msg.toLowerCase().includes('dismiss')) {
      showToast('PDF konnte nicht erstellt werden')
      console.error(e)
    }
  } finally {
    printing.value = false
  }
}

function toggleCheck(key: string) {
  const s = new Set(checked.value)
  s.has(key) ? s.delete(key) : s.add(key)
  checked.value = s
}

/** Anmerkungen sind als JS-String-Literal gespeichert ("...") – sauber dekodieren. */
function decodeAnmerkung(raw: string): string {
  if (!raw) return ''
  const t = raw.trim()
  if (t.startsWith('"') && t.endsWith('"')) {
    try {
      return JSON.parse(t)
    } catch {
      return t.slice(1, -1)
    }
  }
  return raw
}

async function addToCart() {
  if (!isOnline.value || !rezept.value) return
  toast.value = 'Wird hinzugefügt…'
  try {
    const res = await fetch(
      apiUrl('bringApiAddMyRecipeIngredients', { recipe_id: String(rezept.value.ID) }),
    )
    const data = await res.json()
    toast.value = data.success ? data.message : 'Fehler: ' + data.error
  } catch {
    toast.value = 'Fehler beim Hinzufügen'
  }
  setTimeout(() => (toast.value = ''), 4000)
}

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => (toast.value = ''), 4000)
}

async function reload() {
  try {
    const { data } = await getRezept(props.id)
    rezept.value = data
  } catch {
    /* ignore */
  }
}

// --- Rezept löschen ---
const confirmDelete = ref(false)
const busy = ref(false)
async function doDeleteRezept() {
  if (!rezept.value) return
  busy.value = true
  try {
    await deleteRezept(rezept.value.ID)
    router.push('/')
  } catch (e) {
    showToast(e instanceof Error ? e.message : 'Löschen fehlgeschlagen')
    busy.value = false
    confirmDelete.value = false
  }
}

// --- Auf Kalender ---
const showCal = ref(false)
const calDate = ref(new Date().toISOString().split('T')[0])
const calText = ref('')
async function doAddKalender() {
  if (!rezept.value) return
  busy.value = true
  try {
    await addKalender(calDate.value, calText.value, rezept.value.ID)
    showCal.value = false
    showToast('Zum Kalender hinzugefügt')
  } catch (e) {
    showToast(e instanceof Error ? e.message : 'Fehler')
  } finally {
    busy.value = false
  }
}

// --- Bewertung anlegen/bearbeiten ---
const showReview = ref(false)
const reviewEditId = ref<number | null>(null)
const rvName = ref('')
const rvRating = ref(5)
const rvText = ref('')
function openNewReview() {
  reviewEditId.value = null
  rvName.value = ''
  rvRating.value = 5
  rvText.value = ''
  showReview.value = true
}
function openEditReview(b: Bewertung) {
  reviewEditId.value = b.ID
  rvName.value = b.Name
  rvRating.value = b.Bewertung
  rvText.value = b.Text
  showReview.value = true
}
async function saveReview() {
  if (!rezept.value || !rvName.value.trim()) return
  busy.value = true
  try {
    if (reviewEditId.value != null) {
      await editEvaluation(reviewEditId.value, rvRating.value, rvName.value.trim(), rvText.value)
    } else {
      await addEvaluation(rezept.value.ID, rvRating.value, rvName.value.trim(), rvText.value)
    }
    showReview.value = false
    await reload()
  } catch (e) {
    showToast(e instanceof Error ? e.message : 'Fehler')
  } finally {
    busy.value = false
  }
}
async function removeReview() {
  if (reviewEditId.value == null) return
  busy.value = true
  try {
    await deleteEvaluation(reviewEditId.value)
    showReview.value = false
    await reload()
  } catch (e) {
    showToast(e instanceof Error ? e.message : 'Fehler')
  } finally {
    busy.value = false
  }
}

// --- Anmerkung bearbeiten ---
const showNote = ref(false)
const noteHtml = ref('')
function openNote() {
  const first = rezept.value?.Anmerkungen.find((a) => a.Anmerkung)
  noteHtml.value = first ? decodeAnmerkung(first.Anmerkung) : ''
  showNote.value = true
}
async function saveNote() {
  if (!rezept.value) return
  busy.value = true
  try {
    await saveAnmerkung(rezept.value.ID, noteHtml.value)
    showNote.value = false
    await reload()
  } catch (e) {
    showToast(e instanceof Error ? e.message : 'Fehler')
  } finally {
    busy.value = false
  }
}

async function loadRezept() {
  loading.value = true
  error.value = false
  rezept.value = null
  checked.value = new Set()
  try {
    const { data } = await getRezept(props.id)
    rezept.value = data
    portionen.value = data.Portionen
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

// Neu laden wenn ID wechselt (z.B. Navigation via KI-Chat-Links)
watch(() => props.id, () => { activeImageIndex.value = 0; loadRezept() })

onMounted(loadRezept)
</script>

<template>
  <div class="container">
    <!-- Ladezustand -->
    <template v-if="loading">
      <div class="skeleton" style="height: 32px; width: 60%; margin: 8px 0 24px"></div>
      <div class="skeleton" style="aspect-ratio: 16/9; border-radius: var(--r-xl)"></div>
    </template>

    <div v-else-if="error" class="empty">
      <i class="fa-solid fa-circle-exclamation"></i>
      <p>Rezept konnte nicht geladen werden. Bist du offline und es war noch nie geöffnet?</p>
      <RouterLink to="/" class="btn btn--ghost" style="margin-top: 16px">Zur Startseite</RouterLink>
    </div>

    <template v-else-if="rezept">
      <RouterLink to="/search" class="back no-print">
        <i class="fa-solid fa-arrow-left"></i> Zurück
      </RouterLink>
      <h1 class="title allow-select">{{ rezept.Name }}</h1>

      <!-- Bilder -->
      <div v-if="rezept.Bilder.length" class="gallery">
        <!-- Hauptbild -->
        <img
          class="gallery__main"
          :src="cachedSrc(rezept.Bilder[activeImageIndex].Image)"
          :alt="rezept.Name"
        />
        <!-- Thumbnail-Streifen (nur wenn > 1 Bild) -->
        <div v-if="rezept.Bilder.length > 1" class="gallery__strip">
          <img
            v-for="(b, i) in rezept.Bilder"
            :key="b.ID"
            class="gallery__thumb"
            :class="{ 'gallery__thumb--active': i === activeImageIndex }"
            :src="cachedSrc(b.Image)"
            :alt="`${rezept.Name} ${i + 1}`"
            loading="lazy"
            @click="activeImageIndex = i"
          />
        </div>
      </div>

      <!-- Info-Chips -->
      <div class="infos">
        <span class="chip"><i class="fa-solid fa-users"></i>{{ portionen }} Portionen</span>
        <span class="chip"><i class="fa-regular fa-clock"></i>{{ zeitLabel }}</span>
        <span class="chip" :style="{ '--c': rezept.KategorieColor }">
          <i class="fa-solid fa-tag" :style="{ color: rezept.KategorieColor }"></i>
          {{ rezept.Kategorie }}
        </span>
        <span v-if="appliances.length" class="chip">
          <i class="fa-solid fa-blender"></i>{{ appliances.map((a) => a.Name).join(', ') }}
        </span>
        <span v-if="avgRating > 0" class="chip"><StarRating :value="avgRating" size="0.85rem" /></span>
        <span v-for="info in optionalInfos" :key="info.title" class="chip">
          {{ info.title }}: {{ info.content }}
        </span>
      </div>

      <!-- Aktionen -->
      <div class="actions no-print">
        <button class="btn btn--ghost" :disabled="printing" @click="printPage">
          <i v-if="printing" class="fa-solid fa-spinner fa-spin"></i>
          <i v-else class="fa-solid fa-print"></i>
          {{ Capacitor.isNativePlatform() ? 'PDF / Teilen' : 'Drucken' }}
        </button>
        <button class="btn btn--accent" :disabled="!isOnline" @click="addToCart">
          <i class="fa-solid fa-basket-shopping"></i> Zutaten zu Bring
        </button>
        <RouterLink
          :to="isOnline ? `/edit/${rezept.ID}` : ''"
          class="btn btn--ghost"
          :class="{ disabled: !isOnline }"
        >
          <i class="fa-solid fa-pen"></i> Bearbeiten
        </RouterLink>
        <button class="btn btn--ghost" :disabled="!isOnline" @click="showCal = true">
          <i class="fa-solid fa-calendar-plus"></i> Planen
        </button>
        <button class="btn btn--ghost danger" :disabled="!isOnline" @click="confirmDelete = true">
          <i class="fa-solid fa-trash"></i> Löschen
        </button>
      </div>

      <!-- Zutaten -->
      <div class="section-title"><h2>Zutaten</h2></div>

      <div class="portion-row no-print">
        <span>Portionen anpassen</span>
        <div class="stepper">
          <button @click="portionen = Math.max(1, portionen - 1)" aria-label="weniger">
            <i class="fa-solid fa-minus"></i>
          </button>
          <span class="stepper-val">{{ portionen }}</span>
          <button @click="portionen++" aria-label="mehr">
            <i class="fa-solid fa-plus"></i>
          </button>
        </div>
      </div>

      <div class="ingredient-tables">
        <div v-for="table in rezept.ZutatenTables" :key="table" class="itable">
          <h3 v-if="table">{{ table }}</h3>
          <ul class="zutaten">
            <li
              v-for="(z, i) in ingredientsOf(table)"
              :key="i"
              :class="{ done: checked.has(table + i) }"
              @click="toggleCheck(table + i)"
            >
              <img :src="cachedSrc(z.Image)" :alt="z.Name" />
              <span class="z-text">
                <strong>{{ scaled(z.Menge) }} {{ z.unit }}</strong>
                {{ z.Name }}
                <em v-if="z.additionalInfo">{{ z.additionalInfo }}</em>
              </span>
              <i class="check fa-solid fa-check no-print"></i>
            </li>
          </ul>
        </div>
      </div>

      <!-- Zubereitung -->
      <div class="section-title"><h2>Zubereitung</h2></div>
      <div class="prose allow-select" v-html="rezept.Zubereitung"></div>

      <!-- Anmerkungen -->
      <div class="section-title">
        <h2>Anmerkungen</h2>
        <button class="edit-mini no-print" :disabled="!isOnline" @click="openNote">
          <i class="fa-solid fa-pen"></i>
        </button>
      </div>
      <div
        v-for="a in rezept.Anmerkungen.filter((x) => x.Anmerkung)"
        :key="a.ID"
        class="note prose allow-select"
        v-html="decodeAnmerkung(a.Anmerkung)"
      ></div>
      <p v-if="!rezept.Anmerkungen.some((a) => a.Anmerkung)" class="muted">
        Noch keine Anmerkung.
      </p>

      <!-- Bewertungen -->
      <div class="section-title no-print">
        <h2>Bewertungen</h2>
        <button class="edit-mini" :disabled="!isOnline" @click="openNewReview">
          <i class="fa-solid fa-plus"></i>
        </button>
      </div>
      <ul class="reviews no-print">
        <li v-if="!rezept.Bewertungen.length" class="empty" style="grid-column: 1/-1">
          <i class="fa-regular fa-comment-dots"></i>
          <p>Noch keine Bewertungen.</p>
        </li>
        <li
          v-for="b in rezept.Bewertungen"
          :key="b.ID"
          class="review"
          :class="{ editable: isOnline }"
          @click="isOnline && openEditReview(b)"
        >
          <img :src="b.Image" :alt="b.Name" />
          <div class="allow-select">
            <div class="review-head">
              <strong>{{ b.Name }}</strong>
              <StarRating :value="b.Bewertung" size="0.8rem" />
            </div>
            <p>{{ b.Text }}</p>
          </div>
        </li>
      </ul>

      <!-- Modal: Löschen bestätigen -->
      <Modal v-if="confirmDelete" title="Rezept löschen?" @close="confirmDelete = false">
        <p>Soll „{{ rezept.Name }}" wirklich gelöscht werden? Das kann nicht rückgängig gemacht werden.</p>
        <template #footer>
          <button class="btn btn--ghost" @click="confirmDelete = false">Abbrechen</button>
          <button class="btn btn--accent danger-btn" :disabled="busy" @click="doDeleteRezept">
            Endgültig löschen
          </button>
        </template>
      </Modal>

      <!-- Modal: Kalender -->
      <Modal v-if="showCal" title="Auf den Kalender" @close="showCal = false">
        <div class="field">
          <label>Datum</label>
          <input v-model="calDate" type="date" />
        </div>
        <div class="field">
          <label>Notiz (optional)</label>
          <input v-model="calText" placeholder="z. B. Mittagessen" />
        </div>
        <template #footer>
          <button class="btn btn--accent" :disabled="busy" @click="doAddKalender">Planen</button>
        </template>
      </Modal>

      <!-- Modal: Bewertung -->
      <Modal
        v-if="showReview"
        :title="reviewEditId != null ? 'Bewertung bearbeiten' : 'Bewerten'"
        @close="showReview = false"
      >
        <div class="field">
          <label>Name</label>
          <input v-model="rvName" placeholder="Dein Name" />
        </div>
        <div class="field">
          <label>Sterne</label>
          <div class="star-pick">
            <button
              v-for="n in 5"
              :key="n"
              type="button"
              @click="rvRating = n"
              :aria-label="`${n} Sterne`"
            >
              <i :class="n <= rvRating ? 'fa-solid fa-star' : 'fa-regular fa-star'"></i>
            </button>
          </div>
        </div>
        <div class="field">
          <label>Text</label>
          <textarea v-model="rvText" rows="3" placeholder="Wie war's?"></textarea>
        </div>
        <template #footer>
          <button
            v-if="reviewEditId != null"
            class="btn btn--ghost danger"
            :disabled="busy"
            @click="removeReview"
          >
            <i class="fa-solid fa-trash"></i> Löschen
          </button>
          <button class="btn btn--accent" :disabled="busy" @click="saveReview">Speichern</button>
        </template>
      </Modal>

      <!-- Modal: Anmerkung -->
      <Modal v-if="showNote" title="Anmerkung bearbeiten" @close="showNote = false">
        <RichText v-model="noteHtml" placeholder="Deine Anmerkung…" />
        <template #footer>
          <button class="btn btn--accent" :disabled="busy" @click="saveNote">Speichern</button>
        </template>
      </Modal>

      <Transition name="toast">
        <div v-if="toast" class="toast no-print">{{ toast }}</div>
      </Transition>
    </template>
  </div>
</template>

<style scoped>
.back {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  color: var(--ink-soft);
  font-weight: 600;
  font-size: var(--fs-sm);
  margin-bottom: var(--sp-3);
}
.back:hover {
  color: var(--accent);
}
.title {
  font-size: var(--fs-display);
  margin-bottom: var(--sp-4);
}

.gallery {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}
.gallery__main {
  width: 100%;
  max-height: 420px;
  border-radius: var(--r-xl);
  object-fit: cover;
  box-shadow: var(--shadow-sm);
}
.gallery__strip {
  display: flex;
  gap: var(--sp-2);
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
  padding-bottom: var(--sp-1);
}
.gallery__strip::-webkit-scrollbar {
  height: 4px;
}
.gallery__strip::-webkit-scrollbar-track {
  background: transparent;
}
.gallery__strip::-webkit-scrollbar-thumb {
  background: var(--line);
  border-radius: var(--r-full);
}
.gallery__thumb {
  flex-shrink: 0;
  width: 72px;
  height: 72px;
  border-radius: var(--r-md);
  object-fit: cover;
  cursor: pointer;
  scroll-snap-align: start;
  border: 2px solid transparent;
  box-shadow: var(--shadow-sm);
  transition: border-color 0.15s var(--ease), opacity 0.15s var(--ease);
  opacity: 0.7;
}
.gallery__thumb:hover {
  opacity: 1;
}
.gallery__thumb--active {
  border-color: var(--accent);
  opacity: 1;
}

.infos {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
  margin-bottom: var(--sp-4);
}

.actions {
  display: flex;
  gap: var(--sp-3);
  flex-wrap: wrap;
}
.actions .disabled {
  opacity: 0.5;
  pointer-events: none;
}
.btn.danger {
  color: var(--danger);
}
.danger-btn {
  background: var(--danger);
  color: #fff;
}
.section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  margin-top: var(--sp-5);
}
.edit-mini {
  width: 38px;
  height: 38px;
  border: 1px solid var(--line);
  border-radius: var(--r-full);
  background: var(--surface);
  color: var(--ink-soft);
}
.edit-mini:disabled {
  opacity: 0.5;
}
.edit-mini:hover:not(:disabled) {
  background: var(--accent-soft);
  color: var(--accent-strong);
}
.muted {
  color: var(--ink-faint);
  font-size: var(--fs-sm);
}
.review.editable {
  cursor: pointer;
}
.review.editable:hover {
  background: var(--accent-soft);
}
.field {
  display: grid;
  gap: var(--sp-2);
  margin-bottom: var(--sp-3);
}
.field label {
  font-weight: 600;
  font-size: var(--fs-sm);
  color: var(--ink-soft);
}
.field input,
.field textarea {
  width: 100%;
  border: 1.5px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface);
  color: var(--ink);
  padding: var(--sp-3);
  outline: none;
  font: inherit;
}
.field input {
  height: 48px;
}
.star-pick {
  display: flex;
  gap: var(--sp-2);
  font-size: 1.6rem;
  color: var(--gold);
}
.star-pick button {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.portion-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  background: var(--surface-2);
  border-radius: var(--r-lg);
  padding: var(--sp-3) var(--sp-4);
  margin-bottom: var(--sp-4);
  font-weight: 600;
}
.stepper {
  display: flex;
  align-items: center;
  background: var(--surface);
  border-radius: var(--r-full);
  border: 1px solid var(--line);
  overflow: hidden;
}
.stepper button {
  width: 44px;
  height: 44px;
  border: none;
  background: transparent;
  color: var(--accent);
}
.stepper button:active {
  background: var(--accent-soft);
}
.stepper-val {
  min-width: 40px;
  text-align: center;
  font-weight: 700;
}

.ingredient-tables {
  display: grid;
  gap: var(--sp-5);
}
.itable h3 {
  margin-bottom: var(--sp-2);
}
.zutaten {
  list-style: none;
}
.zutaten li {
  display: grid;
  grid-template-columns: 26px 1fr 20px;
  gap: var(--sp-3);
  align-items: center;
  padding: var(--sp-3) var(--sp-3);
  border-radius: var(--r-md);
  cursor: pointer;
  transition: background 0.15s var(--ease);
}
.zutaten li:nth-child(odd) {
  background: var(--surface-2);
}
.zutaten li:hover {
  background: var(--accent-soft);
}
.zutaten li img {
  width: 26px;
  height: 26px;
  object-fit: contain;
}
.z-text em {
  color: var(--ink-soft);
  font-style: normal;
  font-size: 0.92em;
}
.zutaten li .check {
  opacity: 0;
  color: var(--green);
  transition: opacity 0.15s var(--ease), transform 0.3s var(--ease-spring);
  transform: scale(0.4);
}
.zutaten li.done .check {
  opacity: 1;
  transform: scale(1);
}
.zutaten li.done .z-text {
  text-decoration: line-through;
  color: var(--ink-faint);
}

.prose {
  background: var(--surface-2);
  border-radius: var(--r-lg);
  padding: var(--sp-5);
  line-height: 1.7;
}
.prose :deep(ul),
.prose :deep(ol) {
  padding-left: var(--sp-5);
}
.prose :deep(p) {
  margin-bottom: var(--sp-3);
}
.note {
  margin-bottom: var(--sp-3);
}

.reviews {
  list-style: none;
  display: grid;
  gap: var(--sp-3);
}
.review {
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: var(--sp-3);
  background: var(--surface-2);
  border-radius: var(--r-lg);
  padding: var(--sp-4);
}
.review img {
  width: 48px;
  height: 48px;
  border-radius: var(--r-md);
  background: var(--surface);
}
.review-head {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  margin-bottom: var(--sp-1);
}
.review p {
  color: var(--ink-soft);
}

.toast {
  position: fixed;
  left: 50%;
  bottom: calc(var(--nav-h-mobile) + 16px);
  transform: translateX(-50%);
  background: var(--ink);
  color: var(--bg);
  padding: var(--sp-3) var(--sp-5);
  border-radius: var(--r-full);
  box-shadow: var(--shadow-lg);
  z-index: 200;
  font-weight: 500;
  max-width: 90vw;
  text-align: center;
}
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s var(--ease-spring);
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translate(-50%, 20px);
}

@media (min-width: 769px) {
  .toast {
    bottom: 24px;
  }
}
</style>
