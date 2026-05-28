<script setup lang="ts">
import { computed } from 'vue'
import { isOnline, hasServerConnection } from '@/services/network'

const showOfflineBanner = computed(() => !isOnline.value || !hasServerConnection.value)
const message = computed(() => {
  if (!isOnline.value) return 'Offline - du siehst gespeicherte Inhalte'
  if (!hasServerConnection.value) return 'Server nicht erreichbar - du siehst gespeicherte Inhalte'
  return ''
})
</script>

<template>
  <Transition name="slide">
    <div v-if="showOfflineBanner" class="offline no-print">
      <i class="fa-solid fa-cloud-arrow-down"></i>
      <span>{{ message }}</span>
    </div>
  </Transition>
</template>

<style scoped>
.offline {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-5);
  background: var(--ink);
  color: var(--bg);
  font-size: var(--fs-sm);
  font-weight: 500;
}
.offline i {
  color: var(--gold);
}
.slide-enter-active,
.slide-leave-active {
  transition: all 0.3s var(--ease);
}
.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  transform: translateY(-100%);
}
</style>
