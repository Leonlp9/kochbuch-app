<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useUiStore, THEME_LABELS } from '@/stores/ui'
import { PI_SERVER } from '@/config'
import { isOnline } from '@/services/network'
import { searchZutaten } from '@/services/api'
import { preloadImages, cachedImageCount, clearImageCache } from '@/services/imageCache'
import {
  cachedRecipeCount,
  downloadAllRecipes,
  downloadAllRecipeImages,
} from '@/services/offlineDownload'

const ui = useUiStore()

const cachedImgCount = ref(0)
const cachedRecCount = ref(0)

// Ladezustände je Aktion
const loadingIcons = ref(false)
const loadingRecipes = ref(false)
const loadingImages = ref(false)

// Fortschritt (jeweils eins aktiv)
const progress = ref(0)
const progressTotal = ref(0)
const progressLabel = ref('')

async function refreshCounts() {
  cachedImgCount.value = await cachedImageCount()
  cachedRecCount.value = await cachedRecipeCount()
}
onMounted(refreshCounts)

async function preloadIcons() {
  if (!isOnline.value || loadingIcons.value) return
  loadingIcons.value = true
  progress.value = 0
  progressLabel.value = ''
  try {
    const zutaten = await searchZutaten('*')
    const urls = zutaten.map((z) => z.Image)
    progressTotal.value = urls.length
    await preloadImages(urls, (done, total) => {
      progress.value = done
      progressTotal.value = total
    })
    await refreshCounts()
  } catch {
    /* ignore */
  } finally {
    loadingIcons.value = false
  }
}

async function preloadRecipes() {
  if (!isOnline.value || loadingRecipes.value) return
  loadingRecipes.value = true
  progress.value = 0
  progressTotal.value = 0
  progressLabel.value = ''
  try {
    await downloadAllRecipes((done, total, label) => {
      progress.value = done
      progressTotal.value = total
      progressLabel.value = label ?? ''
    })
    await refreshCounts()
  } catch {
    /* ignore */
  } finally {
    loadingRecipes.value = false
  }
}

async function preloadRecipeImages() {
  if (!isOnline.value || loadingImages.value) return
  loadingImages.value = true
  progress.value = 0
  progressTotal.value = 0
  progressLabel.value = ''
  try {
    await downloadAllRecipeImages((done, total, label) => {
      progress.value = done
      progressTotal.value = total
      progressLabel.value = label ?? ''
    })
    await refreshCounts()
  } catch {
    /* ignore */
  } finally {
    loadingImages.value = false
  }
}

const anyLoading = () => loadingIcons.value || loadingRecipes.value || loadingImages.value

async function clearCache() {
  await clearImageCache()
  await refreshCounts()
}
</script>

<template>
  <div class="container">
    <h1>Einstellungen</h1>

    <div class="section-title"><h2>Erscheinungsbild</h2></div>
    <div class="themes">
      <button
        v-for="t in ui.themes"
        :key="t"
        class="theme-tile"
        :class="{ active: ui.theme === t }"
        :data-preview="t"
        @click="ui.applyTheme(t)"
      >
        <span class="swatches">
          <span class="s s1"></span>
          <span class="s s2"></span>
          <span class="s s3"></span>
        </span>
        <span class="theme-name">{{ THEME_LABELS[t] }}</span>
        <i v-if="ui.theme === t" class="fa-solid fa-circle-check"></i>
      </button>
    </div>

    <div class="section-title"><h2>Server</h2></div>
    <div class="card server">
      <i class="fa-solid fa-server"></i>
      <div>
        <strong>Verbundener Server</strong>
        <code>{{ PI_SERVER }}</code>
        <p class="hint">
          Änderbar in <code>src/config.ts</code> (und <code>vite.config.ts</code> für die
          Browser-Vorschau).
        </p>
      </div>
    </div>

    <div class="section-title"><h2>Offline</h2></div>
    <div class="card offline">
      <div class="offline-stats">
        <span class="stat"><i class="fa-solid fa-image"></i> <strong>{{ cachedImgCount }}</strong> Bilder/Icons gecacht</span>
        <span class="stat"><i class="fa-solid fa-book"></i> <strong>{{ cachedRecCount }}</strong> Rezepte gecacht</span>
      </div>
      <p class="hint">
        Gespeicherte Daten stehen auch ohne Internetverbindung zur Verfügung.
        Lade alles einmal vorab, damit alle Rezepte vollständig offline funktionieren.
      </p>

      <!-- Aktiver Fortschritt -->
      <div v-if="anyLoading()" class="progress-row">
        <div class="progress-bar">
          <div
            class="progress-fill"
            :style="{ width: progressTotal > 0 ? `${(progress / progressTotal) * 100}%` : '0%' }"
          ></div>
        </div>
        <span class="progress-text">
          {{ progress }}/{{ progressTotal }}
          <span v-if="progressLabel" class="progress-label">– {{ progressLabel }}</span>
        </span>
      </div>

      <div class="offline-actions">
        <!-- 1. Zutaten-Icons -->
        <button
          class="btn btn--outline offline-btn"
          :disabled="!isOnline || anyLoading()"
          @click="preloadIcons"
        >
          <i v-if="loadingIcons" class="fa-solid fa-spinner fa-spin"></i>
          <i v-else class="fa-solid fa-icons"></i>
          <span>
            <strong>Zutaten-Icons</strong>
            <small>Alle Zutat-Symbole speichern</small>
          </span>
        </button>

        <!-- 2. Alle Rezepte -->
        <button
          class="btn btn--outline offline-btn"
          :disabled="!isOnline || anyLoading()"
          @click="preloadRecipes"
        >
          <i v-if="loadingRecipes" class="fa-solid fa-spinner fa-spin"></i>
          <i v-else class="fa-solid fa-book-open"></i>
          <span>
            <strong>Alle Rezepte</strong>
            <small>Rezept-Daten offline speichern</small>
          </span>
        </button>

        <!-- 3. Alle Bilder -->
        <button
          class="btn btn--outline offline-btn"
          :disabled="!isOnline || anyLoading()"
          @click="preloadRecipeImages"
        >
          <i v-if="loadingImages" class="fa-solid fa-spinner fa-spin"></i>
          <i v-else class="fa-solid fa-images"></i>
          <span>
            <strong>Alle Bilder</strong>
            <small>Rezeptfotos offline speichern</small>
          </span>
        </button>

        <!-- Cache leeren -->
        <button class="btn btn--ghost" :disabled="cachedImgCount === 0 && cachedRecCount === 0" @click="clearCache">
          <i class="fa-solid fa-trash"></i> Cache leeren
        </button>
      </div>
    </div>

    <div class="section-title"><h2>Verwaltung</h2></div>
    <p class="manage-hint">
      Rezepte, Kategorien, Zutaten und Geräte lassen sich direkt in der App pflegen.
      Das Anlegen und Ändern braucht eine Verbindung zum Server; das Nachkochen
      funktioniert auch offline.
    </p>
    <div class="manage-grid">
      <RouterLink to="/new" class="manage-link">
        <i class="fa-solid fa-plus"></i><span>Neues Rezept</span>
      </RouterLink>
      <RouterLink to="/manage/categories" class="manage-link">
        <i class="fa-solid fa-tags"></i><span>Kategorien</span>
      </RouterLink>
      <RouterLink to="/manage/ingredients" class="manage-link">
        <i class="fa-solid fa-carrot"></i><span>Zutaten</span>
      </RouterLink>
      <RouterLink to="/manage/appliances" class="manage-link">
        <i class="fa-solid fa-blender"></i><span>Küchengeräte</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.themes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: var(--sp-3);
}
.theme-tile {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-4);
  border-radius: var(--r-lg);
  border: 2px solid var(--line);
  background: var(--surface);
  text-align: left;
  transition: border-color 0.18s var(--ease), transform 0.12s var(--ease);
}
.theme-tile:active {
  transform: scale(0.98);
}
.theme-tile.active {
  border-color: var(--accent);
}
.theme-tile > i {
  position: absolute;
  top: var(--sp-3);
  right: var(--sp-3);
  color: var(--accent);
}
.swatches {
  display: flex;
  gap: 6px;
}
.s {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, 0.08);
}
.theme-name {
  font-weight: 600;
}

/* Vorschau-Farben je Theme */
[data-preview='light'] .s1 { background: #faf6f0 }
[data-preview='light'] .s2 { background: #c8553d }
[data-preview='light'] .s3 { background: #d29a3a }
[data-preview='dark'] .s1 { background: #231d18 }
[data-preview='dark'] .s2 { background: #e0735a }
[data-preview='dark'] .s3 { background: #e0b35a }
[data-preview='midnight'] .s1 { background: #15171c }
[data-preview='midnight'] .s2 { background: #5aa9e6 }
[data-preview='midnight'] .s3 { background: #e0b35a }
[data-preview='dracula'] .s1 { background: #343746 }
[data-preview='dracula'] .s2 { background: #ff79c6 }
[data-preview='dracula'] .s3 { background: #50fa7b }
[data-preview='spring'] .s1 { background: #f3faf2 }
[data-preview='spring'] .s2 { background: #3f9d6a }
[data-preview='spring'] .s3 { background: #e0a73a }
[data-preview='christmas'] .s1 { background: #f7f3ef }
[data-preview='christmas'] .s2 { background: #b23b3b }
[data-preview='christmas'] .s3 { background: #2f7d4f }
[data-preview='helloween'] .s1 { background: #1e1a15 }
[data-preview='helloween'] .s2 { background: #e8721c }
[data-preview='helloween'] .s3 { background: #8aa84a }

.server {
  display: flex;
  gap: var(--sp-4);
  padding: var(--sp-4);
  align-items: flex-start;
}
.server > i {
  font-size: 1.4rem;
  color: var(--accent);
  margin-top: 2px;
}
.server code {
  display: inline-block;
  margin-top: 4px;
  font-size: var(--fs-sm);
  color: var(--ink-soft);
  overflow-wrap: anywhere;
  word-break: break-word;
}
.offline {
  display: grid;
  gap: var(--sp-3);
  padding: var(--sp-4);
}
.offline-stats {
  display: flex;
  gap: var(--sp-4);
  flex-wrap: wrap;
}
.stat {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: var(--fs-sm);
  color: var(--ink-soft);
}
.stat i {
  color: var(--accent);
}
.progress-row {
  display: grid;
  gap: var(--sp-2);
}
.progress-bar {
  height: 6px;
  background: var(--line);
  border-radius: var(--r-full);
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: var(--accent);
  border-radius: var(--r-full);
  transition: width 0.2s ease;
}
.progress-text {
  font-size: var(--fs-sm);
  color: var(--ink-soft);
}
.progress-label {
  color: var(--ink-faint);
}
.offline-actions {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--sp-3);
}
.offline-btn {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-4);
  text-align: left;
}
.offline-btn > i {
  font-size: 1.3rem;
  color: var(--accent);
  width: 24px;
  flex-shrink: 0;
}
.offline-btn span {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.offline-btn strong {
  font-size: var(--fs-base);
  font-weight: 600;
}
.offline-btn small {
  font-size: var(--fs-sm);
  color: var(--ink-faint);
  font-weight: 400;
}
.btn--outline {
  border: 1.5px solid var(--accent);
  background: var(--accent-soft);
  color: var(--accent-strong);
  border-radius: var(--r-lg);
  font: inherit;
  cursor: pointer;
  transition: background 0.18s var(--ease), border-color 0.18s var(--ease);
}
.btn--outline:hover:not(:disabled) {
  background: var(--accent);
  color: #fff;
}
.btn--outline:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.hint {
  margin-top: var(--sp-2);
  font-size: var(--fs-sm);
  color: var(--ink-faint);
}
.manage-hint {
  color: var(--ink-soft);
  line-height: 1.7;
  margin-bottom: var(--sp-4);
}
.manage-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: var(--sp-3);
}
.manage-link {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-4);
  border-radius: var(--r-lg);
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--ink);
  font-weight: 600;
  transition: background 0.18s var(--ease), border-color 0.18s var(--ease);
}
.manage-link:hover {
  background: var(--accent-soft);
  border-color: var(--accent);
  color: var(--accent-strong);
}
.manage-link i {
  font-size: 1.2rem;
  color: var(--accent);
  width: 24px;
  text-align: center;
}
</style>
