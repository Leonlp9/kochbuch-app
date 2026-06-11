<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { search as apiSearch, getKategorien, searchZutaten, type ZutatSuche } from '@/services/api'
import RecipeGrid from '@/components/RecipeGrid.vue'
import type { SearchResult, Kategorie } from '@/types/models'

const route = useRoute()

const term = ref('')
const order = ref('Name')
const zeit = ref('4')
const kategorie = ref('')
const showFilters = ref(false)

// --- Zutaten-Filter ---
interface IngredientTag { ID: number; Name: string; Image: string }
const includeIngredients = ref<IngredientTag[]>([])
const excludeIngredients = ref<IngredientTag[]>([])
const ingQuery = ref('')
const ingResults = ref<ZutatSuche[]>([])
let ingDebounce: number | undefined
function searchIngredients() {
  clearTimeout(ingDebounce)
  if (!ingQuery.value.trim()) { ingResults.value = []; return }
  ingDebounce = window.setTimeout(async () => {
    try {
      const raw = await searchZutaten(ingQuery.value)
      // Duplikate nach Name entfernen (gleiche Zutat mit verschiedenen Einheiten)
      const seen = new Set<string>()
      ingResults.value = raw.filter(z => {
        const key = z.Name.toLowerCase()
        if (seen.has(key)) return false
        seen.add(key)
        return true
      })
    } catch { ingResults.value = [] }
  }, 220)
}
function addInclude(z: ZutatSuche) {
  if (!includeIngredients.value.find(i => i.ID === z.ID))
    includeIngredients.value.push({ ID: z.ID, Name: z.Name, Image: z.Image })
  excludeIngredients.value = excludeIngredients.value.filter(i => i.ID !== z.ID)
  ingQuery.value = ''; ingResults.value = []
}
function addExclude(z: ZutatSuche) {
  if (!excludeIngredients.value.find(i => i.ID === z.ID))
    excludeIngredients.value.push({ ID: z.ID, Name: z.Name, Image: z.Image })
  includeIngredients.value = includeIngredients.value.filter(i => i.ID !== z.ID)
  ingQuery.value = ''; ingResults.value = []
}
function removeInclude(id: number) { includeIngredients.value = includeIngredients.value.filter(i => i.ID !== id) }
function removeExclude(id: number) { excludeIngredients.value = excludeIngredients.value.filter(i => i.ID !== id) }

const allResults = ref<SearchResult[]>([])
const loading = ref(true)
const kategorien = ref<Kategorie[]>([])

const PAGE_SIZE = 20
const visibleCount = ref(PAGE_SIZE)
const loadOffset = ref(0)
const loadingMore = ref(false)

const visibleResults = computed(() => allResults.value.slice(0, visibleCount.value))
const hasMore = computed(() => visibleCount.value < allResults.value.length)

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
  visibleCount.value = PAGE_SIZE
  loadOffset.value = 0
  apiSearch({
    search: term.value,
    order: order.value,
    zeit: zeit.value,
    kategorie: kategorie.value || undefined,
    whitelistIngredients: includeIngredients.value.length
      ? JSON.stringify(includeIngredients.value.map(i => i.ID))
      : undefined,
    blacklistIngredients: excludeIngredients.value.length
      ? JSON.stringify(excludeIngredients.value.map(i => i.ID))
      : undefined,
  })
    .then(({ data }) => (allResults.value = data))
    .catch(() => (allResults.value = []))
    .finally(() => (loading.value = false))
}
function onInput() {
  clearTimeout(debounce)
  debounce = window.setTimeout(runSearch, 250)
}

watch([order, zeit, kategorie, includeIngredients, excludeIngredients], runSearch, { deep: true })

// --- Infinite Scroll via IntersectionObserver ---
const sentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

function loadMore() {
  if (!hasMore.value || loadingMore.value) return
  loadingMore.value = true
  // Kleiner Delay damit der Spinner kurz sichtbar ist wenn man sehr schnell scrollt
  setTimeout(() => {
    loadOffset.value = visibleCount.value
    visibleCount.value = Math.min(visibleCount.value + PAGE_SIZE, allResults.value.length)
    loadingMore.value = false
  }, 120)
}

function setupObserver() {
  observer?.disconnect()
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) loadMore()
    },
    { rootMargin: '200px' }, // 200px vor dem Ende vorladen
  )
  if (sentinel.value) observer.observe(sentinel.value)
}

onMounted(() => {
  getKategorien(true).then(({ data }) => (kategorien.value = data)).catch(() => {})
  if (route.query.kategorie) {
    kategorie.value = String(route.query.kategorie)
    showFilters.value = true
  }
  runSearch()
  setupObserver()
})

onUnmounted(() => observer?.disconnect())

// Observer neu verbinden wenn Sentinel sich ändert (nach loading)
watch(loading, (v) => {
  if (!v) setTimeout(setupObserver, 50)
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

        <!-- Zutaten-Filter -->
        <div class="filter-group">
          <span class="flabel">Zutaten filtern</span>
          <div class="ing-search-wrap">
            <input
              v-model="ingQuery"
              class="ing-search-input"
              placeholder="Zutat suchen…"
              @input="searchIngredients"
            />
            <div v-if="ingResults.length" class="ing-dropdown">
              <div
                v-for="z in ingResults.slice(0, 8)"
                :key="z.ID"
                class="ing-row"
              >
                <img v-if="z.Image" :src="z.Image" :alt="z.Name" class="ing-icon" />
                <i v-else class="fa-solid fa-bowl-food ing-icon-fa"></i>
                <span class="ing-name">{{ z.Name }}</span>
                <button type="button" class="ing-btn ing-btn--include" @click="addInclude(z)" title="Muss enthalten sein">
                  <i class="fa-solid fa-plus"></i>
                </button>
                <button type="button" class="ing-btn ing-btn--exclude" @click="addExclude(z)" title="Darf nicht enthalten sein">
                  <i class="fa-solid fa-minus"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Ausgewählte Zutaten anzeigen -->
          <div v-if="includeIngredients.length || excludeIngredients.length" class="ing-tags">
            <span v-if="includeIngredients.length" class="ing-tags-label">
              <i class="fa-solid fa-check"></i> Muss enthalten:
            </span>
            <span
              v-for="tag in includeIngredients"
              :key="'i' + tag.ID"
              class="ing-tag ing-tag--include"
            >
              {{ tag.Name }}
              <button type="button" @click="removeInclude(tag.ID)"><i class="fa-solid fa-xmark"></i></button>
            </span>

            <span v-if="excludeIngredients.length" class="ing-tags-label">
              <i class="fa-solid fa-ban"></i> Ohne:
            </span>
            <span
              v-for="tag in excludeIngredients"
              :key="'e' + tag.ID"
              class="ing-tag ing-tag--exclude"
            >
              {{ tag.Name }}
              <button type="button" @click="removeExclude(tag.ID)"><i class="fa-solid fa-xmark"></i></button>
            </span>
          </div>
        </div>
      </div>
    </Transition>

    <div class="result-meta">
      <span>{{ allResults.length }} Rezepte</span>
    </div>

    <RecipeGrid
      :recipes="visibleResults"
      :loading="loading"
      :skeleton-count="8"
      :load-offset="loadOffset"
    />

    <!-- Sentinel – unsichtbar, löst Nachladen aus -->
    <div ref="sentinel" class="sentinel"></div>

    <!-- Spinner: nur wenn man schneller scrollt als nachgeladen wird -->
    <div v-if="loadingMore" class="more-spinner">
      <i class="fa-solid fa-spinner fa-spin"></i>
    </div>

    <div v-if="!loading && allResults.length === 0" class="empty">
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

.sentinel {
  height: 1px;
  margin-top: var(--sp-4);
}

.more-spinner {
  display: flex;
  justify-content: center;
  padding: var(--sp-5);
  color: var(--ink-faint);
  font-size: 1.4rem;
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
  max-height: 900px;
}

/* ── Zutaten-Filter ── */
.ing-search-wrap {
  position: relative;
}
.ing-search-input {
  width: 100%;
  height: 44px;
  border: 1.5px solid var(--line);
  border-radius: var(--r-md);
  background: var(--surface-2);
  color: var(--ink);
  padding: 0 var(--sp-3);
  outline: none;
  font-size: var(--fs-sm);
  box-sizing: border-box;
}
.ing-search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}
.ing-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--r-md);
  box-shadow: var(--shadow-md);
  z-index: 50;
  overflow: hidden;
}
.ing-row {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-3);
  transition: background 0.12s;
}
.ing-row:hover { background: var(--surface-2); }
.ing-icon {
  width: 28px;
  height: 28px;
  object-fit: contain;
  border-radius: var(--r-sm);
  flex-shrink: 0;
}
.ing-icon-fa {
  width: 28px;
  text-align: center;
  color: var(--ink-faint);
  flex-shrink: 0;
}
.ing-name {
  flex: 1;
  font-size: var(--fs-sm);
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ing-btn {
  width: 30px;
  height: 30px;
  border: none;
  border-radius: var(--r-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.14s;
}
.ing-btn--include {
  background: color-mix(in srgb, var(--accent) 15%, transparent);
  color: var(--accent-strong);
}
.ing-btn--include:hover { background: color-mix(in srgb, var(--accent) 28%, transparent); }
.ing-btn--exclude {
  background: color-mix(in srgb, #ef4444 15%, transparent);
  color: #dc2626;
}
.ing-btn--exclude:hover { background: color-mix(in srgb, #ef4444 28%, transparent); }

.ing-tags {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--sp-2);
  margin-top: var(--sp-2);
}
.ing-tags-label {
  font-size: var(--fs-xs, 0.72rem);
  font-weight: 700;
  color: var(--ink-soft);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  display: flex;
  align-items: center;
  gap: 4px;
  width: 100%;
  margin-top: var(--sp-1);
}
.ing-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px 4px 10px;
  border-radius: var(--r-full);
  font-size: var(--fs-sm);
  font-weight: 600;
}
.ing-tag button {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
  opacity: 0.7;
  font-size: 0.75rem;
}
.ing-tag button:hover { opacity: 1; }
.ing-tag--include {
  background: color-mix(in srgb, var(--accent) 18%, transparent);
  color: var(--accent-strong);
}
.ing-tag--exclude {
  background: color-mix(in srgb, #ef4444 15%, transparent);
  color: #dc2626;
}
</style>
