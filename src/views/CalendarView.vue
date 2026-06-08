<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getKalender } from '@/services/api'
import { addKalender, updateKalender, deleteKalender } from '@/services/writeApi'
import { isOnline } from '@/services/network'
import Modal from '@/components/Modal.vue'
import { cachedSrc } from '@/services/imageCache'
import type { KalenderEintrag } from '@/types/models'
import { VueDraggable } from 'vue-draggable-plus'

const router = useRouter()
const entries = ref<KalenderEintrag[]>([])
const loading = ref(true)
const showPast = ref(false)
const busy = ref(false)

// ── Drag-and-Drop Datenstruktur ──────────────────────────────────────────────
interface DayGroup { date: string; list: KalenderEintrag[] }
const displayDays = ref<DayGroup[]>([])

function buildDisplayDays() {
  const map = new Map<string, KalenderEintrag[]>()

  // Bestehende Ghost-Days (leere Tage) erhalten – ermöglicht 2-Zug-Tausch
  for (const day of displayDays.value) {
    if (day.list.length === 0) map.set(day.date, [])
  }

  for (const e of entries.value) {
    if (!map.has(e.Datum)) map.set(e.Datum, [])
    map.get(e.Datum)!.push(e)
  }

  displayDays.value = [...map.entries()]
    .sort((a, b) =>
      showPast.value ? b[0].localeCompare(a[0]) : a[0].localeCompare(b[0])
    )
    .map(([date, list]) => ({ date, list }))
}

// Neu bauen wenn Einträge vom Server kommen oder showPast toggled
watch(entries, buildDisplayDays)
watch(showPast, buildDisplayDays)

// ── Drag-End: Datum des verschobenen Eintrags aktualisieren ──────────────────
function onDragEnd(evt: { item: Element }) {
  const kid = parseInt((evt.item as HTMLElement).dataset.kid ?? '0')
  if (!kid) return

  for (const day of displayDays.value) {
    const entry = day.list.find(e => e.Kalender_ID === kid)
    if (entry) {
      if (day.date !== entry.Datum) {
        const newDate = day.date
        entry.Datum = newDate
        // Serverseitig speichern (kein Reload – Ghost-Days bleiben erhalten)
        updateKalender(kid, { text: entry.Text ?? '', date: newDate }).catch(() => {})
      }
      break
    }
  }
}

function fmt(d: string) {
  return new Date(d).toLocaleDateString('de-DE', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
  })
}

async function load() {
  loading.value = true
  try {
    const { data } = await getKalender(showPast.value)
    entries.value = data
  } catch {
    entries.value = []
  } finally {
    loading.value = false
  }
}

function open(e: KalenderEintrag) {
  if (e.Rezept_ID != null) router.push(`/recipe/${e.Rezept_ID}`)
  else openEdit(e)
}

// --- Hinzufügen / Bearbeiten ---
const showForm = ref(false)
const editId = ref<number | null>(null)
const fDate = ref(new Date().toISOString().split('T')[0])
const fText = ref('')

function openNew() {
  editId.value = null
  fDate.value = new Date().toISOString().split('T')[0]
  fText.value = ''
  showForm.value = true
}
function openEdit(e: KalenderEintrag) {
  editId.value = e.Kalender_ID ?? null
  fDate.value = e.Datum
  fText.value = e.Text ?? ''
  showForm.value = true
}
async function save() {
  busy.value = true
  try {
    if (editId.value != null) {
      await updateKalender(editId.value, { text: fText.value, date: fDate.value })
    } else {
      if (!fText.value.trim()) { busy.value = false; return }
      await addKalender(fDate.value, fText.value)
    }
    showForm.value = false
    await load()
  } catch {
    /* ignore */
  } finally {
    busy.value = false
  }
}
async function remove() {
  if (editId.value == null) return
  busy.value = true
  try {
    await deleteKalender(editId.value)
    showForm.value = false
    await load()
  } catch {
    /* ignore */
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="container">
    <div class="head">
      <h1>Meine Woche</h1>
      <label class="toggle">
        <input v-model="showPast" type="checkbox" @change="load" />
        <span>Vergangenes zeigen</span>
      </label>
    </div>

    <div v-if="loading" class="list">
      <div v-for="n in 4" :key="n" class="skeleton" style="height: 88px; border-radius: var(--r-lg)"></div>
    </div>

    <div v-else-if="displayDays.length" class="days">
      <section v-for="day in displayDays" :key="day.date" class="day">
        <h2 class="day-title">{{ fmt(day.date) }}</h2>

        <!-- Sortierbare Eintrags-Liste für diesen Tag -->
        <VueDraggable
          v-model="day.list"
          group="calendar-entries"
          handle=".drag-handle"
          :animation="200"
          ghost-class="entry--ghost"
          class="sortable-list"
          :class="{ 'sortable-list--empty': day.list.length === 0 }"
          @end="onDragEnd"
        >
          <div
            v-for="e in day.list"
            :key="e.Kalender_ID"
            class="entry"
            :class="{ clickable: e.Rezept_ID != null }"
            :data-kid="e.Kalender_ID"
          >
            <!-- Drag-Handle -->
            <div class="drag-handle" title="Verschieben">
              <i class="fa-solid fa-grip-lines"></i>
            </div>

            <div
              v-if="e.Image"
              class="entry-img"
              :style="{ backgroundImage: `url('${cachedSrc(e.Image)}')` }"
              @click="open(e)"
            ></div>
            <div class="entry-body" @click="open(e)">
              <strong>{{ e.Name ?? e.Text }}</strong>
              <span v-if="e.Text && e.Name">{{ e.Text }}</span>
            </div>
            <button class="entry-edit" :disabled="!isOnline" @click.stop="openEdit(e)" aria-label="Bearbeiten">
              <i class="fa-solid fa-pen"></i>
            </button>
          </div>
        </VueDraggable>
      </section>
    </div>

    <div v-else class="empty">
      <i class="fa-regular fa-calendar"></i>
      <p>Nichts geplant.</p>
    </div>

    <button class="fab no-print" :disabled="!isOnline" @click="openNew" aria-label="Eintrag hinzufügen">
      <i class="fa-solid fa-plus"></i>
    </button>

    <Modal v-if="showForm" :title="editId != null ? 'Eintrag bearbeiten' : 'Neuer Eintrag'" @close="showForm = false">
      <div class="field">
        <label>Datum</label>
        <input v-model="fDate" type="date" />
      </div>
      <div class="field">
        <label>Text</label>
        <input v-model="fText" placeholder="z. B. Pizza-Abend" />
      </div>
      <template #footer>
        <button v-if="editId != null" class="btn btn--ghost danger" :disabled="busy" @click="remove">
          <i class="fa-solid fa-trash"></i> Löschen
        </button>
        <button class="btn btn--accent" :disabled="busy" @click="save">Speichern</button>
      </template>
    </Modal>
  </div>
</template>

<style scoped>
.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  flex-wrap: wrap;
  margin-bottom: var(--sp-4);
}
.toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: var(--fs-sm);
  color: var(--ink-soft);
  cursor: pointer;
}
.toggle input {
  width: 18px;
  height: 18px;
  accent-color: var(--accent);
}

.days {
  display: grid;
  gap: var(--sp-6);
}
.day {
  display: grid;
  gap: var(--sp-2);
}
.day-title {
  font-size: var(--fs-h3);
  color: var(--accent-strong);
}

/* ── Sortable-Liste ── */
.sortable-list {
  display: grid;
  gap: var(--sp-2);
  min-height: 4px; /* SortableJS braucht eine minimale Höhe als Drop-Zone */
}
.sortable-list--empty {
  min-height: 64px;
  border: 2px dashed var(--line);
  border-radius: var(--r-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink-faint);
  font-size: var(--fs-sm);
  transition: border-color 0.15s, background 0.15s;
}
.sortable-list--empty::after {
  content: '↓ Hier ablegen';
}

/* ── Eintrag ── */
.entry {
  display: grid;
  grid-template-columns: 32px auto 1fr auto;
  gap: var(--sp-3);
  align-items: center;
  text-align: left;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--r-lg);
  padding: var(--sp-2) var(--sp-3) var(--sp-2) var(--sp-2);
  width: 100%;
  /* Verhindert Overflow auf kleinen Screens */
  min-width: 0;
  overflow: hidden;
  /* Verhindert Text-Selektion beim Drag */
  user-select: none;
  -webkit-user-select: none;
}
.entry.clickable .entry-body,
.entry.clickable .entry-img {
  cursor: pointer;
}

/* Ghost während des Ziehens – SortableJS setzt die Klasse per JS, daher :deep() */
:deep(.entry--ghost) {
  opacity: 0.35;
  background: var(--accent-soft) !important;
  border: 2px dashed var(--accent) !important;
}

/* ── Drag-Handle ── */
.drag-handle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  color: var(--ink-faint);
  cursor: grab;
  touch-action: none; /* Wichtig: verhindert Scroll-Konflikte auf Touch */
  font-size: 0.85rem;
  flex-shrink: 0;
  padding: var(--sp-2) 0;
}
.drag-handle:hover { color: var(--ink-soft); }
.drag-handle:active { cursor: grabbing; }

.entry-img {
  width: 64px;
  height: 64px;
  border-radius: var(--r-md);
  background-size: cover;
  background-position: center;
}
.entry-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--sp-2) 0;
  /* Grid-Item darf unter seine Content-Size schrumpfen */
  min-width: 0;
  overflow: hidden;
}
.entry-body strong,
.entry-body span {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.entry-body span {
  color: var(--ink-soft);
  font-size: var(--fs-sm);
}
.entry-edit {
  width: 38px;
  height: 38px;
  border: 1px solid var(--line);
  border-radius: var(--r-full);
  background: var(--surface);
  color: var(--ink-soft);
}
.entry-edit:hover:not(:disabled) {
  background: var(--accent-soft);
  color: var(--accent-strong);
}
.entry-edit:disabled {
  opacity: 0.4;
}

.fab {
  position: fixed;
  right: 18px;
  bottom: calc(var(--nav-h-mobile) + 18px);
  width: 56px;
  height: 56px;
  border: none;
  border-radius: var(--r-full);
  background: var(--accent);
  color: var(--on-accent);
  font-size: 1.3rem;
  box-shadow: var(--shadow-lg);
  z-index: 50;
}
.fab:disabled {
  opacity: 0.5;
}
@media (min-width: 769px) {
  .fab {
    bottom: 24px;
  }
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
.field input {
  height: 48px;
  border: 1.5px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface);
  color: var(--ink);
  padding: 0 var(--sp-3);
  outline: none;
}
.btn.danger {
  color: var(--danger);
}
</style>
