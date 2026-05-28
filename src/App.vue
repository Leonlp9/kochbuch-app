<script setup lang="ts">
import { onMounted } from 'vue'
import NavBar from '@/components/NavBar.vue'
import OfflineBanner from '@/components/OfflineBanner.vue'
import { checkForUpdate, updateReady, applyUpdate } from '@/services/updater'

onMounted(() => {
  // Update-Check im Hintergrund – blockiert die App nicht
  void checkForUpdate()
})
</script>

<template>
  <div class="app-shell">
    <NavBar />
    <main class="app-main">
      <OfflineBanner />

      <!-- Update-Banner: erscheint, wenn ein neues Bundle geladen wurde -->
      <Transition name="slide-down">
        <div v-if="updateReady" class="update-banner">
          <span>✨ Update verfügbar!</span>
          <div class="update-actions">
            <button class="update-apply" @click="applyUpdate">Jetzt neu starten</button>
            <button class="update-dismiss" @click="updateReady = false">✕</button>
          </div>
        </div>
      </Transition>

      <RouterView v-slot="{ Component }">
        <Transition name="fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>
  </div>
</template>

<style scoped>
/* ---- Layout ---- */
.app-shell {
  display: grid;
  grid-template-columns: var(--nav-w) 1fr;
  min-height: 100vh;
  min-height: 100dvh;
}
.app-main {
  min-width: 0;
}

/* ---- Update-Banner ---- */
.update-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.6rem 1rem;
  background: var(--green, #5c8054);
  color: var(--on-accent, #fff);
  font-size: 0.9rem;
  font-weight: 500;
}
.update-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}
.update-apply {
  background: rgba(255,255,255,0.22);
  border: 1px solid rgba(255,255,255,0.5);
  color: inherit;
  cursor: pointer;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.25rem 0.7rem;
  border-radius: 999px;
  transition: background 0.15s;
}
.update-apply:hover { background: rgba(255,255,255,0.35); }
.update-dismiss {
  background: none;
  border: none;
  color: inherit;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  padding: 0;
  opacity: 0.8;
}
.update-dismiss:hover { opacity: 1; }

.slide-down-enter-active,
.slide-down-leave-active {
  transition: max-height 0.3s var(--ease, ease), opacity 0.3s var(--ease, ease);
  overflow: hidden;
}
.slide-down-enter-from,
.slide-down-leave-to {
  max-height: 0;
  opacity: 0;
}
.slide-down-enter-to,
.slide-down-leave-from {
  max-height: 4rem;
  opacity: 1;
}

/* ---- Seiten-Transitions ---- */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s var(--ease), transform 0.2s var(--ease);
}
.fade-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

@media (max-width: 768px) {
  .app-shell {
    grid-template-columns: 1fr;
  }
  .app-main {
    padding-bottom: calc(var(--nav-h-mobile) + env(safe-area-inset-bottom, 0px));
  }
}
</style>
