<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getKalender } from '@/services/api'
import { addKalender, updateKalender, deleteKalender } from '@/services/writeApi'
import { isOnline } from '@/services/network'
import Modal from '@/components/Modal.vue'
import { cachedSrc } from '@/services/imageCache'
import type { KalenderEintrag } from '@/types/models'

const router = useRouter()
const entries = ref<KalenderEintrag[]>([])
const loading = ref(true)
const showPast = ref(false)
const busy = ref(false)

const grouped = computed(() => {
  const map = new Map<string, KalenderEintrag[]>()
  for (const e of entries.value) {
    if (!map.has(e.Datum)) map.set(e.Datum, [])
    map.get(e.Datum)!.push(e)
  }
  return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]))
})

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
      if (!fText.value.trim()) {
        busy.value = false
        return
      }
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

    <div v-else-if="grouped.length" class="days">
      <section v-for="[date, list] in grouped" :key="date" class="day">
        <h2 class="day-title">{{ fmt(date) }}</h2>
        <div
          v-for="e in list"
          :key="e.Kalender_ID"
          class="entry"
          :class="{ clickable: e.Rezept_ID != null }"
        >
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
.entry {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--sp-3);
  align-items: center;
  text-align: left;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--r-lg);
  padding: var(--sp-2) var(--sp-3) var(--sp-2) var(--sp-2);
  width: 100%;
}
.entry.clickable .entry-body,
.entry.clickable .entry-img {
  cursor: pointer;
}
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
