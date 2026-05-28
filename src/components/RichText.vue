<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'

const props = defineProps<{ modelValue: string; placeholder?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const editor = ref<HTMLDivElement | null>(null)

onMounted(() => {
  if (editor.value) editor.value.innerHTML = props.modelValue || ''
})

// Von aussen gesetzten Wert uebernehmen (z.B. beim Laden im Edit-Modus)
watch(
  () => props.modelValue,
  (v) => {
    if (editor.value && editor.value.innerHTML !== v) editor.value.innerHTML = v || ''
  },
)

function cmd(command: string) {
  document.execCommand(command, false)
  editor.value?.focus()
  sync()
}

function sync() {
  if (editor.value) emit('update:modelValue', editor.value.innerHTML)
}
</script>

<template>
  <div class="rt">
    <div class="rt-toolbar no-print">
      <button type="button" @click="cmd('bold')" title="Fett"><i class="fa-solid fa-bold"></i></button>
      <button type="button" @click="cmd('italic')" title="Kursiv"><i class="fa-solid fa-italic"></i></button>
      <button type="button" @click="cmd('underline')" title="Unterstrichen"><i class="fa-solid fa-underline"></i></button>
      <span class="sep"></span>
      <button type="button" @click="cmd('insertUnorderedList')" title="Liste"><i class="fa-solid fa-list-ul"></i></button>
      <button type="button" @click="cmd('insertOrderedList')" title="Nummerierte Liste"><i class="fa-solid fa-list-ol"></i></button>
    </div>
    <div
      ref="editor"
      class="rt-area"
      contenteditable="true"
      :data-placeholder="placeholder || 'Text eingeben…'"
      @input="sync"
      @blur="sync"
    ></div>
  </div>
</template>

<style scoped>
.rt {
  border: 1.5px solid var(--line);
  border-radius: var(--r-md);
  overflow: hidden;
  background: var(--surface);
}
.rt-toolbar {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 6px;
  background: var(--surface-2);
  border-bottom: 1px solid var(--line);
}
.rt-toolbar button {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: var(--r-sm);
  background: transparent;
  color: var(--ink-soft);
}
.rt-toolbar button:hover {
  background: var(--surface-3);
  color: var(--ink);
}
.sep {
  width: 1px;
  height: 22px;
  background: var(--line);
  margin: 0 4px;
}
.rt-area {
  min-height: 140px;
  padding: var(--sp-4);
  outline: none;
  line-height: 1.6;
}
.rt-area:empty::before {
  content: attr(data-placeholder);
  color: var(--ink-faint);
}
.rt-area :deep(ul),
.rt-area :deep(ol) {
  padding-left: var(--sp-5);
}
</style>
