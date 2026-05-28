<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { search, getKategorien, getKalender } from '@/services/api'
import RecipeGrid from '@/components/RecipeGrid.vue'
import { cachedSrc } from '@/services/imageCache'
import type { SearchResult, Kategorie, KalenderEintrag } from '@/types/models'

const router = useRouter()

const randomPool = ref<SearchResult[]>([])
const hero = ref<SearchResult | null>(null)
const kategorien = ref<Kategorie[]>([])
const neueste = ref<SearchResult[]>([])
const zuletzt = ref<SearchResult[]>([])
const random = ref<SearchResult[]>([])
const heute = ref<KalenderEintrag[]>([])

const loadingNew = ref(true)
const loadingRecent = ref(true)
const loadingRandom = ref(true)

let heroTimer: number | undefined

function rotateHero() {
  if (randomPool.value.length === 0) return
  hero.value = randomPool.value[Math.floor(Math.random() * randomPool.value.length)]
}

async function loadRandom() {
  loadingRandom.value = true
  const { data } = await search({ random: true })
  random.value = data
  if (randomPool.value.length === 0) {
    randomPool.value = data
    rotateHero()
  }
  loadingRandom.value = false
}

onMounted(async () => {
  void loadRandom()
  heroTimer = window.setInterval(rotateHero, 5000)

  getKategorien().then(({ data }) => (kategorien.value = data)).catch(() => {})

  search({ neueste: true })
    .then(({ data }) => (neueste.value = data))
    .catch(() => {})
    .finally(() => (loadingNew.value = false))

  search({ last_visit: true })
    .then(({ data }) => (zuletzt.value = data))
    .catch(() => {})
    .finally(() => (loadingRecent.value = false))

  const today = new Date().toISOString().split('T')[0]
  getKalender()
    .then(({ data }) => (heute.value = data.filter((e) => e.Datum === today)))
    .catch(() => {})
})

onUnmounted(() => {
  if (heroTimer) clearInterval(heroTimer)
})

function openRecipe(id: number | null) {
  if (id != null) router.push(`/recipe/${id}`)
}
function openCategory(id: number) {
  router.push({ path: '/search', query: { kategorie: String(id) } })
}
</script>

<template>
  <div class="container">
    <header class="hero-head">
      <p class="eyebrow">Willkommen zurück</p>
      <h1>Was kochen wir heute?</h1>
    </header>

    <!-- Hero -->
    <button
      v-if="hero"
      class="hero"
      :style="{ backgroundImage: `url('${cachedSrc(hero.Image)}')` }"
      @click="openRecipe(hero.rezepte_ID)"
    >
      <span class="hero-grad"></span>
      <span class="hero-cap">
        <span class="hero-kicker">Inspiration</span>
        <span class="hero-name">{{ hero.Name }}</span>
      </span>
    </button>
    <div v-else class="skeleton hero-sk"></div>

    <!-- Heute -->
    <template v-if="heute.length">
      <div class="section-title">
        <h2>Heute geplant</h2>
        <RouterLink to="/calendar" class="more">Kalender →</RouterLink>
      </div>
      <div class="today-row">
        <button
          v-for="e in heute"
          :key="e.Kalender_ID"
          class="today-card"
          @click="openRecipe(e.Rezept_ID)"
        >
          <div
            v-if="e.Image"
            class="today-img"
            :style="{ backgroundImage: `url('${cachedSrc(e.Image)}')` }"
          ></div>
          <span class="today-name">{{ e.Name ?? e.Text }}</span>
        </button>
      </div>
    </template>

    <!-- Kategorien -->
    <div class="section-title">
      <h2>Kategorien</h2>
    </div>
    <div class="cat-row">
      <button
        v-for="k in kategorien"
        :key="k.ID"
        class="cat"
        :style="{ '--cat': k.ColorHex }"
        @click="openCategory(k.ID)"
      >
        {{ k.Name }}
      </button>
    </div>

    <!-- Zufall -->
    <div class="section-title">
      <span class="eyebrow">Zufallsauswahl</span>
      <h2 style="flex: 1">Lust auf etwas Neues?</h2>
      <button class="btn btn--ghost shake" @click="loadRandom">
        <i class="fa-solid fa-shuffle"></i> Shake
      </button>
    </div>
    <RecipeGrid :recipes="random" :loading="loadingRandom" :skeleton-count="4" />

    <!-- Neueste -->
    <div class="section-title">
      <h2>Zuletzt hinzugefügt</h2>
    </div>
    <RecipeGrid :recipes="neueste" :loading="loadingNew" :skeleton-count="4" />

    <!-- Zuletzt aufgerufen -->
    <template v-if="loadingRecent || zuletzt.length">
      <div class="section-title">
        <h2>Zuletzt aufgerufen</h2>
      </div>
      <RecipeGrid :recipes="zuletzt" :loading="loadingRecent" :skeleton-count="4" />
    </template>
  </div>
</template>

<style scoped>
.hero-head {
  margin-bottom: var(--sp-5);
}
.hero-head .eyebrow {
  color: var(--accent);
  font-size: var(--fs-sm);
  font-weight: 600;
  letter-spacing: 0.04em;
}
.hero-head h1 {
  font-size: var(--fs-display);
  margin-top: var(--sp-1);
}

.hero,
.hero-sk {
  width: 100%;
  aspect-ratio: 16 / 9;
  max-height: 440px;
  border-radius: var(--r-xl);
  border: none;
  overflow: hidden;
  position: relative;
  background-size: cover;
  background-position: center;
  background-color: var(--surface-2);
  cursor: pointer;
  display: flex;
  align-items: flex-end;
  box-shadow: var(--shadow-md);
}
.hero-grad {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(20, 16, 14, 0.78), transparent 62%);
}
.hero-cap {
  position: relative;
  padding: var(--sp-5);
  text-align: left;
  color: #fff;
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
}
.hero-kicker {
  font-size: var(--fs-xs);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  opacity: 0.85;
  font-weight: 600;
}
.hero-name {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: clamp(1.5rem, 4vw, 2.4rem);
  line-height: 1.1;
}

.more {
  color: var(--accent);
  font-size: var(--fs-sm);
  font-weight: 600;
}

/* Heute */
.today-row {
  display: flex;
  gap: var(--sp-3);
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: var(--sp-1);
}
.today-row::-webkit-scrollbar {
  display: none;
}
.today-card {
  flex: 0 0 auto;
  width: 160px;
  border: 1px solid var(--line);
  background: var(--surface);
  border-radius: var(--r-lg);
  overflow: hidden;
  text-align: left;
  padding: 0;
}
.today-img {
  width: 100%;
  aspect-ratio: 4 / 3;
  background-size: cover;
  background-position: center;
}
.today-name {
  display: block;
  padding: var(--sp-3);
  font-weight: 600;
  font-size: var(--fs-sm);
}

/* Kategorien */
.cat-row {
  display: flex;
  gap: var(--sp-2);
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: var(--sp-1);
}
.cat-row::-webkit-scrollbar {
  display: none;
}
.cat {
  flex: 0 0 auto;
  padding: 0 var(--sp-4);
  height: 46px;
  border: none;
  border-radius: var(--r-full);
  background: color-mix(in srgb, var(--cat) 22%, var(--surface));
  color: var(--ink);
  font-weight: 600;
  position: relative;
  transition: transform 0.12s var(--ease);
}
.cat::before {
  content: '';
  position: absolute;
  left: var(--sp-3);
  top: 50%;
  transform: translateY(-50%);
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--cat);
}
.cat {
  padding-left: var(--sp-6);
}
.cat:active {
  transform: scale(0.96);
}

.shake i {
  transition: transform 0.4s var(--ease-spring);
}
.shake:hover i {
  transform: rotate(180deg);
}
</style>
