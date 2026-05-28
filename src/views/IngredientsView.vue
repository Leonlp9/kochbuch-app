<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { searchZutaten, type ZutatSuche } from '@/services/api'
import { editZutat, deleteZutat } from '@/services/writeApi'
import { isOnline } from '@/services/network'
import Modal from '@/components/Modal.vue'

const UNITS = ['g', 'ml', 'Stück', 'Prise', 'TL', 'EL', 'Tasse', 'Packung', 'Bund', 'Dose', 'Paket', 'Becher', 'Scheibe', 'Zehe', 'Zweige', 'Würfel', 'Messerspitze']

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

async function load() {
  loading.value = true
  try {
    // '*' am Ende = alle anzeigen (kein 20er-Limit)
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
      <div class="field">
        <label>Icon (SVG, optional)</label>
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
.field input,
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
.search-in {
  margin-bottom: var(--sp-4);
}
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
.zcard:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.zcard img {
  width: 40px;
  height: 40px;
  object-fit: contain;
}
.zn {
  font-weight: 600;
  font-size: var(--fs-sm);
  text-align: center;
}
.zcard small {
  color: var(--ink-faint);
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
.danger {
  color: var(--danger);
}
.error-line {
  color: var(--danger);
  font-weight: 600;
  font-size: var(--fs-sm);
}
</style>
