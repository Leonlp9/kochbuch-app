<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getKategorien } from '@/services/api'
import { addKategorie, editKategorie, deleteKategorie } from '@/services/writeApi'
import { isOnline } from '@/services/network'
import Modal from '@/components/Modal.vue'
import type { Kategorie } from '@/types/models'

const list = ref<Kategorie[]>([])
const loading = ref(true)
const errorMsg = ref('')

const showEdit = ref(false)
const editing = ref<Kategorie | null>(null)
const formName = ref('')
const formColor = ref('#c9603f')
const busy = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await getKategorien(true)
    list.value = res.data
  } catch {
    errorMsg.value = 'Kategorien konnten nicht geladen werden.'
  }
  loading.value = false
}
onMounted(load)

function openNew() {
  editing.value = null
  formName.value = ''
  formColor.value = '#c9603f'
  showEdit.value = true
}
function openEdit(k: Kategorie) {
  editing.value = k
  formName.value = k.Name
  formColor.value = k.ColorHex || '#c9603f'
  showEdit.value = true
}

async function save() {
  if (!formName.value.trim()) return
  busy.value = true
  errorMsg.value = ''
  try {
    if (editing.value) {
      await editKategorie(editing.value.ID, formName.value.trim(), formColor.value)
    } else {
      await addKategorie(formName.value.trim(), formColor.value)
    }
    showEdit.value = false
    await load()
  } catch (e) {
    errorMsg.value = e instanceof Error ? e.message : 'Speichern fehlgeschlagen'
  } finally {
    busy.value = false
  }
}

async function remove(k: Kategorie) {
  busy.value = true
  errorMsg.value = ''
  try {
    await deleteKategorie(k.ID)
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
    <h1>Kategorien</h1>

    <div v-if="!isOnline" class="warn">
      <i class="fa-solid fa-plug-circle-xmark"></i> Verwaltung nur mit Serververbindung möglich.
    </div>

    <div v-if="loading" class="empty"><i class="fa-solid fa-spinner fa-spin"></i></div>

    <div v-else class="cat-list">
      <button
        v-for="k in list"
        :key="k.ID"
        class="cat"
        :style="{ background: k.ColorHex }"
        :disabled="!isOnline"
        @click="openEdit(k)"
      >
        <span class="cat-name">{{ k.Name }}</span>
        <span class="cat-count">{{ k.usage_count ?? 0 }}</span>
      </button>
    </div>

    <button class="btn btn--accent" :disabled="!isOnline" @click="openNew">
      <i class="fa-solid fa-plus"></i> Kategorie hinzufügen
    </button>

    <p v-if="errorMsg" class="error-line">{{ errorMsg }}</p>

    <Modal v-if="showEdit" :title="editing ? 'Kategorie bearbeiten' : 'Neue Kategorie'" @close="showEdit = false">
      <div class="field">
        <label>Name</label>
        <input v-model="formName" placeholder="Name der Kategorie" />
      </div>
      <div class="field">
        <label>Farbe</label>
        <input v-model="formColor" type="color" class="color" />
      </div>
      <p v-if="editing && (editing.usage_count ?? 0) > 0" class="hint">
        Kann nicht gelöscht werden – wird in {{ editing.usage_count }} Rezepten verwendet.
      </p>
      <template #footer>
        <button
          v-if="editing && (editing.usage_count ?? 0) === 0"
          class="btn btn--ghost danger"
          :disabled="busy"
          @click="remove(editing)"
        >
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
.cat-list {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-3);
  margin: var(--sp-4) 0;
}
.cat {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-3) var(--sp-4);
  border: none;
  border-radius: var(--r-md);
  color: #fff;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
  font-weight: 700;
  cursor: pointer;
}
.cat:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.cat-count {
  background: rgba(255, 255, 255, 0.3);
  border-radius: var(--r-full);
  padding: 1px 8px;
  font-size: var(--fs-xs);
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
.color {
  padding: 4px;
  cursor: pointer;
}
.hint {
  color: var(--ink-faint);
  font-size: var(--fs-sm);
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
