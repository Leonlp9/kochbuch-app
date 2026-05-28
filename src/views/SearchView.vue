<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { search as apiSearch, getKategorien } from '@/services/api'
import RecipeGrid from '@/components/RecipeGrid.vue'
import type { SearchResult, Kategorie } from '@/types/models'

const route = useRoute()

const term = ref('')
const order = ref('Name')
const zeit = ref('4')
const kategorie = ref('')
const showFilters = ref(false)

const results = ref<SearchResult[]>([])
const loading = ref(true)
const kategorien = ref<Kategorie[]>([])

const ZEIT_LABEL: Record<string, string> = {
  '0': '0–15 Min',
  '1': '15–30 Min',
  '2': '30–60 Min',
  '3': '60+ Min',
  '4': 'Beliebig',
}
const ORDERS = [
  { v: 'Name', l: 'A–Z' },
  { v: 'Rating', l: 'Bewertung' },
  { v: 'Zeit', l: 'Zeit' },
]

let debounce: number | undefined
function runSearch() {
  loading.value = true
  apiSearch({
    search: term.value,
    order: order.value,
    zeit: zeit.value,
    kategorie: kategorie.value || undefined,
  })
    .then(({ data }) => (results.value = data))
    .catch(() => (results.value = []))
    .finally(() => (loading.value = false))
}
function onInput() {
  clearTimeout(debounce)
  debounce = window.setTimeout(runSearch, 250)
}

watch([order, zeit, kategorie], runSearch)

onMounted(() => {
  getKategorien(true).then(({ data }) => (kategorien.value = data)).catch(() => {})
  if (route.query.kategorie) {
    kategorie.value = String(route.query.kategorie)
    showFilters.value = true
  }
  runSearch()
})
</script>

<template>
  <div class="container">
    <h1>Suche</h1>

    <div class="searchbar">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input
        v-model="term"
        type="text"
        placeholder="Rezept suchen…"
        @input="onInput"
      />
      <button
        class="filter-toggle"
        :class="{ on: showFilters }"
        @click="showFilters = !showFilters"
        aria-label="Filter"
      >
        <i class="fa-solid fa-sliders"></i>
      </button>
    </div>

    <Transition name="expand">
      <div v-show="showFilters" class="filters">
        <div class="filter-group">
          <span class="flabel">Sortierung</span>
          <div class="seg">
            <button
              v-for="o in ORDERS"
              :key="o.v"
              :class="{ active: order === o.v }"
              @click="order = o.v"
            >
              {{ o.l }}
            </button>
          </div>
        </div>

        <div class="filter-group">
          <span class="flabel">Zeit · {{ ZEIT_LABEL[zeit] }}</span>
          <input v-model="zeit" type="range" min="0" max="4" step="1" />
        </div>

        <div class="filter-group">
          <span class="flabel">Kategorie</span>
          <select v-model="kategorie" class="select">
            <option value="">Alle Kategorien</option>
            <option v-for="k in kategorien" :key="k.ID" :value="String(k.ID)">
              {{ k.Name }} ({{ k.usage_count ?? 0 }})
            </option>
          </select>
        </div>
      </div>
    </Transition>

    <div class="result-meta">
      <span>{{ results.length }} Rezepte</span>
    </div>

    <RecipeGrid :recipes="results" :loading="loading" :skeleton-count="8" />

    <div v-if="!loading && results.length === 0" class="empty">
      <i class="fa-solid fa-utensils"></i>
      <p>Keine Rezepte gefunden. Andere Suche oder Filter probieren.</p>
    </div>
  </div>
</template>

<style scoped>
.searchbar {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  background: var(--surface);
  border: 1.5px solid var(--line);
  border-radius: var(--r-full);
  padding: 0 var(--sp-3) 0 var(--sp-5);
  margin: var(--sp-4) 0;
  transition: border-color 0.18s var(--ease), box-shadow 0.18s var(--ease);
}
.searchbar:focus-within {
  border-color: var(--accent);
  box-shadow: 0 0 0 4px var(--accent-soft);
}
.searchbar > i {
  color: var(--ink-faint);
}
.searchbar input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  color: var(--ink);
  height: 54px;
  font-size: 1.02rem;
}
.filter-toggle {
  width: 42px;
  height: 42px;
  border: none;
  border-radius: var(--r-full);
  background: var(--surface-2);
  color: var(--ink-soft);
  transition: all 0.18s var(--ease);
}
.filter-toggle.on {
  background: var(--accent);
  color: var(--on-accent);
}

.filters {
  display: grid;
  gap: var(--sp-5);
  padding: var(--sp-5);
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--r-lg);
  margin-bottom: var(--sp-4);
}
.filter-group {
  display: grid;
  gap: var(--sp-3);
}
.flabel {
  font-size: var(--fs-sm);
  font-weight: 600;
  color: var(--ink-soft);
}
.seg {
  display: inline-flex;
  background: var(--surface-2);
  border-radius: var(--r-md);
  padding: 4px;
  gap: 4px;
  width: fit-content;
}
.seg button {
  border: none;
  background: transparent;
  color: var(--ink-soft);
  padding: 8px var(--sp-4);
  border-radius: var(--r-sm);
  font-weight: 600;
  font-size: var(--fs-sm);
  transition: all 0.16s var(--ease);
}
.seg button.active {
  background: var(--surface);
  color: var(--accent-strong);
  box-shadow: var(--shadow-sm);
}
.select {
  width: 100%;
  height: 48px;
  border: 1.5px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface-2);
  color: var(--ink);
  padding: 0 var(--sp-3);
  outline: none;
}

input[type='range'] {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 6px;
  border-radius: var(--r-full);
  background: var(--surface-3);
  outline: none;
}
input[type='range']::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--accent);
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.result-meta {
  color: var(--ink-soft);
  font-size: var(--fs-sm);
  margin: var(--sp-4) 0;
}

.expand-enter-active,
.expand-leave-active {
  transition: all 0.25s var(--ease);
  overflow: hidden;
}
.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
  margin-bottom: 0;
}
.expand-enter-to,
.expand-leave-from {
  max-height: 400px;
}
</style>
