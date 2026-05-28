<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getEinkaufsliste } from '@/services/api'
import { deleteEinkaufsliste } from '@/services/writeApi'
import { isOnline } from '@/services/network'
import type { EinkaufslisteItem } from '@/types/models'

const items = ref<EinkaufslisteItem[]>([])
const loading = ref(true)
const checked = ref<Set<number>>(new Set())

function toggle(id: number) {
  const s = new Set(checked.value)
  s.has(id) ? s.delete(id) : s.add(id)
  checked.value = s
}

async function removeItem(id: number) {
  if (!isOnline.value) return
  try {
    await deleteEinkaufsliste(id)
    items.value = items.value.filter((x) => x.Einkaufsliste_ID !== id)
  } catch {
    /* ignore */
  }
}

onMounted(async () => {
  try {
    const { data } = await getEinkaufsliste()
    items.value = data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="container">
    <h1>Einkaufsliste</h1>

    <div v-if="loading" class="list">
      <div v-for="n in 5" :key="n" class="skeleton row-sk"></div>
    </div>

    <ul v-else-if="items.length" class="list">
      <li
        v-for="(item, i) in items"
        :key="item.Einkaufsliste_ID"
        class="row rise"
        :class="{ done: checked.has(item.Einkaufsliste_ID) }"
        :style="{ animationDelay: `${i * 30}ms` }"
      >
        <img :src="item.Image" :alt="item.Name" @click="toggle(item.Einkaufsliste_ID)" />
        <span class="row-name" @click="toggle(item.Einkaufsliste_ID)">{{ item.Name }}</span>
        <span class="row-qty" @click="toggle(item.Einkaufsliste_ID)">{{ item.Menge }} {{ item.Einheit }}</span>
        <button class="del" :disabled="!isOnline" @click.stop="removeItem(item.Einkaufsliste_ID)" aria-label="Löschen">
          <i class="fa-solid fa-trash"></i>
        </button>
      </li>
    </ul>

    <div v-else class="empty">
      <i class="fa-solid fa-basket-shopping"></i>
      <p>Deine Einkaufsliste ist leer.</p>
    </div>
  </div>
</template>

<style scoped>
.list {
  display: grid;
  gap: var(--sp-2);
  margin-top: var(--sp-4);
}
.row-sk {
  height: 64px;
  border-radius: var(--r-md);
}
.row {
  display: grid;
  grid-template-columns: 44px 1fr auto 24px;
  gap: var(--sp-3);
  align-items: center;
  padding: var(--sp-3);
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--r-md);
  cursor: pointer;
  transition: background 0.15s var(--ease);
}
.row:hover {
  background: var(--surface-2);
}
.row img {
  width: 44px;
  height: 44px;
  object-fit: contain;
  background: var(--surface-2);
  border-radius: var(--r-sm);
  padding: 4px;
}
.row-name {
  font-weight: 600;
}
.row-qty {
  color: var(--ink-soft);
  font-size: var(--fs-sm);
}
.row img,
.row-name,
.row-qty {
  cursor: pointer;
}
.del {
  width: 38px;
  height: 38px;
  border: none;
  border-radius: var(--r-sm);
  background: var(--surface-2);
  color: var(--ink-soft);
}
.del:hover:not(:disabled) {
  background: var(--danger-soft);
  color: var(--danger);
}
.del:disabled {
  opacity: 0.4;
}
.row.done .row-name,
.row.done .row-qty {
  text-decoration: line-through;
  color: var(--ink-faint);
}
</style>
