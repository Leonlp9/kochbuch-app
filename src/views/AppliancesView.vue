<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getKitchenAppliances } from '@/services/api'
import { addAppliance, updateAppliance, deleteAppliance } from '@/services/writeApi'
import { isOnline } from '@/services/network'
import Modal from '@/components/Modal.vue'
import type { KitchenAppliance } from '@/types/models'

const list = ref<KitchenAppliance[]>([])
const loading = ref(true)
const errorMsg = ref('')

const showEdit = ref(false)
const editing = ref<KitchenAppliance | null>(null)
const formName = ref('')
const imageFile = ref<File | null>(null)
const busy = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await getKitchenAppliances()
    list.value = res.data
  } catch {
    errorMsg.value = 'Geräte konnten nicht geladen werden.'
  }
  loading.value = false
}
onMounted(load)

function openNew() {
  editing.value = null
  formName.value = ''
  imageFile.value = null
  showEdit.value = true
}
function openEdit(a: KitchenAppliance) {
  editing.value = a
  formName.value = a.Name
  imageFile.value = null
  showEdit.value = true
}
function onImage(e: Event) {
  const input = e.target as HTMLInputElement
  imageFile.value = input.files?.[0] ?? null
}

async function save() {
  if (!formName.value.trim()) return
  if (!editing.value && !imageFile.value) {
    errorMsg.value = 'Bitte ein Bild auswählen.'
    return
  }
  busy.value = true
  errorMsg.value = ''
  try {
    if (editing.value) {
      await updateAppliance(editing.value.ID, formName.value.trim(), imageFile.value)
    } else {
      await addAppliance(formName.value.trim(), imageFile.value as File)
    }
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
    await deleteAppliance(editing.value.ID)
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
    <h1>Küchengeräte</h1>

    <div v-if="!isOnline" class="warn">
      <i class="fa-solid fa-plug-circle-xmark"></i> Verwaltung nur mit Serververbindung möglich.
    </div>

    <div v-if="loading" class="empty"><i class="fa-solid fa-spinner fa-spin"></i></div>

    <div v-else class="agrid">
      <button v-for="a in list" :key="a.ID" class="acard" :disabled="!isOnline" @click="openEdit(a)">
        <img :src="a.Image" :alt="a.Name" onerror="this.style.visibility='hidden'" />
        <span class="an">{{ a.Name }}</span>
        <small>{{ a.recipe_count ?? 0 }} Rezepte</small>
      </button>
    </div>

    <button class="btn btn--accent" :disabled="!isOnline" @click="openNew">
      <i class="fa-solid fa-plus"></i> Gerät hinzufügen
    </button>

    <p v-if="errorMsg" class="error-line">{{ errorMsg }}</p>

    <Modal v-if="showEdit" :title="editing ? 'Gerät bearbeiten' : 'Neues Gerät'" @close="showEdit = false">
      <div class="field">
        <label>Name</label>
        <input v-model="formName" placeholder="z. B. Backofen" />
      </div>
      <div class="field">
        <label>Bild {{ editing ? '(optional ersetzen)' : '' }}</label>
        <input type="file" accept="image/*" @change="onImage" />
      </div>
      <template #footer>
        <button v-if="editing" class="btn btn--ghost danger" :disabled="busy" @click="remove">
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
.agrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: var(--sp-3);
  margin: var(--sp-4) 0;
}
.acard {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-4);
  border: 1px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface);
  cursor: pointer;
}
.acard:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.acard img {
  width: 60px;
  height: 60px;
  object-fit: contain;
}
.an {
  font-weight: 600;
  text-align: center;
}
.acard small {
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
.field input {
  height: 48px;
  border: 1.5px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface);
  color: var(--ink);
  padding: 0 var(--sp-3);
  outline: none;
}
.danger {
  color: var(--danger);
}
.error-line {
  color: var(--danger);
  font-weight: 600;
  font-size: var(--fs-sm);
  margin-top: var(--sp-3);
}
</style>
