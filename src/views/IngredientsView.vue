<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { searchZutaten, type ZutatSuche } from '@/services/api'
import { editZutat, deleteZutat, generateIngredientIcon, saveIngredientIcon } from '@/services/writeApi'
import { isOnline } from '@/services/network'
import Modal from '@/components/Modal.vue'

const UNITS = ['g', 'ml', 'L', 'Stück', 'Prise', 'TL', 'EL', 'Tasse', 'Packung', 'Bund', 'Bd', 'Dose', 'Paket', 'Becher', 'Scheibe', 'Zehe', 'Zweige', 'Würfel', 'Messerspitze', 'Blätter']

const query = ref('')
const list = ref<ZutatSuche[]>([])
const loading = ref(true)
const errorMsg = ref('')

const showEdit = ref(false)
const editing = ref<ZutatSuche | null>(null)
const formName = ref('')
const formUnit = ref('g')
const iconFile = ref<File | null>(null)
const busy = ref(false)

// ── KI-Icon-Generierung ─────────────────────────────────────────────────────
const aiGenerating = ref(false)
const aiPreviewData = ref<string | null>(null)  // base64 PNG
const aiError = ref('')
const aiAccepting = ref(false)

async function generateAiIcon() {
  if (!editing.value) return
  aiGenerating.value = true
  aiPreviewData.value = null
  aiError.value = ''
  try {
    const res = await generateIngredientIcon(formName.value || editing.value.Name)
    if (!res.success || !res.image_data) throw new Error(res.error || 'Kein Bild erhalten')
    aiPreviewData.value = res.image_data
  } catch (e) {
    aiError.value = e instanceof Error ? e.message : 'Fehler bei der KI-Generierung'
  } finally {
    aiGenerating.value = false
  }
}

async function acceptAiIcon() {
  if (!editing.value || !aiPreviewData.value) return
  aiAccepting.value = true
  aiError.value = ''
  try {
    await saveIngredientIcon(editing.value.ID, aiPreviewData.value)
    aiPreviewData.value = null
    showEdit.value = false
    await load()
  } catch (e) {
    aiError.value = e instanceof Error ? e.message : 'Fehler beim Speichern'
  } finally {
    aiAccepting.value = false
  }
}

function rejectAiIcon() {
  aiPreviewData.value = null
  aiError.value = ''
}

// ────────────────────────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  try {
    list.value = await searchZutaten(query.value ? query.value : '*')
  } catch {
    errorMsg.value = 'Zutaten konnten nicht geladen werden.'
  }
  loading.value = false
}
onMounted(load)

function openEdit(z: ZutatSuche) {
  editing.value = z
  formName.value = z.Name
  formUnit.value = z.unit || 'g'
  iconFile.value = null
  aiPreviewData.value = null
  aiError.value = ''
  showEdit.value = true
}
function onIcon(e: Event) {
  const input = e.target as HTMLInputElement
  iconFile.value = input.files?.[0] ?? null
}

async function save() {
  if (!editing.value || !formName.value.trim()) return
  busy.value = true
  errorMsg.value = ''
  try {
    await editZutat(editing.value.ID, formName.value.trim(), formUnit.value, iconFile.value)
    showEdit.value = false
    await load()
  } catch (e) {
    errorMsg.value = e instanceof Error ? e.message : 'Speichern fehlgeschlagen'
  } finally {
    busy.value = false
  }
}
async function remove() {
  if (!editing.value) return
  busy.value = true
  errorMsg.value = ''
  try {
    await deleteZutat(editing.value.ID)
    showEdit.value = false
    await load()
  } catch (e) {
    errorMsg.value = e instanceof Error ? e.message : 'Löschen fehlgeschlagen'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="container">
    <RouterLink to="/settings" class="back"><i class="fa-solid fa-arrow-left"></i> Einstellungen</RouterLink>
    <h1>Zutaten</h1>

    <div v-if="!isOnline" class="warn">
      <i class="fa-solid fa-plug-circle-xmark"></i> Verwaltung nur mit Serververbindung möglich.
    </div>
    <p class="note"><i class="fa-solid fa-circle-info"></i> Änderungen wirken sich auf alle Rezepte aus.</p>

    <input v-model="query" class="search-in" placeholder="Zutat suchen…" @input="load" />

    <div v-if="loading" class="empty"><i class="fa-solid fa-spinner fa-spin"></i></div>

    <div v-else class="zgrid">
      <button v-for="z in list" :key="z.ID" class="zcard" :disabled="!isOnline" @click="openEdit(z)">
        <img :src="z.Image" :alt="z.Name" onerror="this.style.visibility='hidden'" />
        <span class="zn">{{ z.Name }}</span>
        <small v-if="z.unit">{{ z.unit }}</small>
      </button>
    </div>

    <p v-if="errorMsg" class="error-line">{{ errorMsg }}</p>

    <Modal v-if="showEdit" title="Zutat bearbeiten" @close="showEdit = false">
      <div class="field">
        <label>Name</label>
        <input v-model="formName" placeholder="Name" />
      </div>
      <div class="field">
        <label>Einheit</label>
        <select v-model="formUnit" class="select">
          <option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
        </select>
      </div>

      <!-- ── Aktuelles Icon ── -->
      <div class="field icon-current-wrap" v-if="editing?.Image">
        <label>Aktuelles Icon</label>
        <div class="icon-current">
          <img :src="editing.Image" :alt="editing.Name" class="icon-preview-img" />
        </div>
      </div>

      <!-- ── KI-Icon-Generator ── -->
      <div class="field ai-icon-section">
        <label>KI-Icon generieren</label>

        <!-- Noch kein Preview: Generator-Button -->
        <div v-if="!aiPreviewData" class="ai-icon-generate">
          <button
            type="button"
            class="btn btn--ghost ai-gen-btn"
            :disabled="aiGenerating || !isOnline"
            @click="generateAiIcon"
          >
            <i class="fa-solid" :class="aiGenerating ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
            {{ aiGenerating ? 'Gemini generiert…' : 'Mit KI generieren' }}
          </button>
          <p v-if="aiGenerating" class="ai-hint">Das dauert ca. 15–30 Sekunden…</p>
          <p v-if="aiError" class="error-line ai-err">
            <i class="fa-solid fa-triangle-exclamation"></i> {{ aiError }}
          </p>
        </div>

        <!-- Preview: Annehmen / Ablehnen -->
        <div v-else class="ai-icon-preview-wrap">
          <div class="ai-icon-preview-box">
            <img
              :src="'data:image/png;base64,' + aiPreviewData"
              alt="KI-generiertes Icon"
              class="ai-icon-preview-img"
            />
          </div>
          <p class="ai-preview-hint">
            <i class="fa-solid fa-circle-info"></i>
            Weißer Hintergrund wird beim Speichern automatisch entfernt.
          </p>
          <div class="ai-icon-actions">
            <button
              type="button"
              class="btn btn--ghost"
              :disabled="aiAccepting"
              @click="rejectAiIcon"
            >
              <i class="fa-solid fa-xmark"></i> Ablehnen
            </button>
            <button
              type="button"
              class="btn btn--ghost ai-gen-btn"
              :disabled="aiAccepting || aiGenerating"
              @click="generateAiIcon"
            >
              <i class="fa-solid fa-rotate-right"></i> Neu generieren
            </button>
            <button
              type="button"
              class="btn btn--accent"
              :disabled="aiAccepting"
              @click="acceptAiIcon"
            >
              <i class="fa-solid" :class="aiAccepting ? 'fa-spinner fa-spin' : 'fa-check'"></i>
              {{ aiAccepting ? 'Speichern…' : 'Übernehmen' }}
            </button>
          </div>
          <p v-if="aiError" class="error-line ai-err">
            <i class="fa-solid fa-triangle-exclamation"></i> {{ aiError }}
          </p>
        </div>
      </div>

      <!-- ── Manueller Icon-Upload ── -->
      <div class="field">
        <label>Icon manuell hochladen (SVG)</label>
        <input type="file" accept="image/svg+xml" @change="onIcon" />
      </div>

      <template #footer>
        <button class="btn btn--ghost danger" :disabled="busy" @click="remove">
          <i class="fa-solid fa-trash"></i> Löschen
        </button>
        <button class="btn btn--accent" :disabled="busy" @click="save">Speichern</button>
      </template>
    </Modal>
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
.note {
  color: var(--ink-faint);
  font-size: var(--fs-sm);
  margin-bottom: var(--sp-3);
}
.search-in,
.field input[type="text"],
.field input:not([type="file"]),
.select {
  width: 100%;
  height: 48px;
  border: 1.5px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface);
  color: var(--ink);
  padding: 0 var(--sp-3);
  outline: none;
}
.search-in { margin-bottom: var(--sp-4); }
.zgrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}
.zcard {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: var(--sp-3);
  border: 1px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface);
  cursor: pointer;
}
.zcard:disabled { opacity: 0.6; cursor: not-allowed; }
.zcard img { width: 40px; height: 40px; object-fit: contain; }
.zn { font-weight: 600; font-size: var(--fs-sm); text-align: center; }
.zcard small { color: var(--ink-faint); }
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

/* ── Aktuelles Icon ── */
.icon-current { display: flex; align-items: center; gap: var(--sp-3); }
.icon-preview-img {
  width: 56px;
  height: 56px;
  object-fit: contain;
  border: 1px solid var(--line);
  border-radius: var(--r-md);
  padding: var(--sp-1);
  background: var(--surface-2);
}

/* ── KI-Sektion ── */
.ai-icon-section { border-top: 1px solid var(--line); padding-top: var(--sp-3); }
.ai-icon-generate { display: flex; flex-direction: column; gap: var(--sp-2); }
.ai-gen-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  color: var(--accent);
  border-color: var(--accent);
  width: fit-content;
}
.ai-hint { font-size: var(--fs-xs); color: var(--ink-soft); margin: 0; }

/* ── KI Preview ── */
.ai-icon-preview-wrap { display: flex; flex-direction: column; gap: var(--sp-3); }
.ai-icon-preview-box {
  display: flex;
  justify-content: center;
  background: repeating-conic-gradient(#e0e0e0 0% 25%, white 0% 50%) 0 0 / 16px 16px;
  border: 1px solid var(--line);
  border-radius: var(--r-lg);
  padding: var(--sp-3);
}
.ai-icon-preview-img {
  width: 160px;
  height: 160px;
  object-fit: contain;
  border-radius: var(--r-md);
}
.ai-preview-hint {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: var(--fs-xs);
  color: var(--ink-soft);
  margin: 0;
}
.ai-icon-actions {
  display: flex;
  gap: var(--sp-2);
  flex-wrap: wrap;
}
.ai-err { margin: 0; }

.danger { color: var(--danger); }
.error-line { color: var(--danger); font-weight: 600; font-size: var(--fs-sm); }
</style>
