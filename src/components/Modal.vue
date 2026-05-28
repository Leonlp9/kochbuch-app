<script setup lang="ts">
defineProps<{ title?: string }>()
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <div class="backdrop" @click.self="emit('close')">
      <div class="dialog rise">
        <header v-if="title || $slots.header" class="dialog-head">
          <h2>{{ title }}</h2>
          <button class="x" @click="emit('close')" aria-label="Schließen">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </header>
        <div class="dialog-body">
          <slot />
        </div>
        <footer v-if="$slots.footer" class="dialog-foot">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.backdrop {
  position: fixed;
  inset: 0;
  background: rgba(20, 16, 14, 0.55);
  backdrop-filter: blur(3px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--sp-4);
  z-index: 300;
}
.dialog {
  background: var(--surface);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-lg);
  width: 100%;
  max-width: 560px;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.dialog-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--line);
}
.dialog-head h2 {
  font-size: var(--fs-h3);
}
.x {
  width: 38px;
  height: 38px;
  border: none;
  border-radius: var(--r-full);
  background: var(--surface-2);
  color: var(--ink-soft);
  font-size: 1.1rem;
}
.x:hover {
  background: var(--surface-3);
}
.dialog-body {
  padding: var(--sp-5);
  overflow-y: auto;
}
.dialog-foot {
  padding: var(--sp-4) var(--sp-5);
  border-top: 1px solid var(--line);
  display: flex;
  gap: var(--sp-3);
  justify-content: flex-end;
}
</style>
