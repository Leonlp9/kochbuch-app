<script setup lang="ts">
import NavBar from '@/components/NavBar.vue'
import OfflineBanner from '@/components/OfflineBanner.vue'
</script>

<template>
  <div class="app-shell">
    <NavBar />
    <main class="app-main">
      <OfflineBanner />
      <RouterView v-slot="{ Component }">
        <Transition name="fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>
  </div>
</template>

<style scoped>
.app-shell {
  display: grid;
  grid-template-columns: var(--nav-w) 1fr;
  min-height: 100vh;
  min-height: 100dvh;
}
.app-main {
  min-width: 0;
}

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
