<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{ value: number; size?: string; count?: number | null }>(),
  { size: '1rem', count: null },
)

const stars = computed(() => {
  const out: ('full' | 'half' | 'empty')[] = []
  for (let i = 1; i <= 5; i++) {
    if (props.value >= i) out.push('full')
    else if (props.value >= i - 0.5) out.push('half')
    else out.push('empty')
  }
  return out
})
</script>

<template>
  <span class="rating" :style="{ fontSize: size }">
    <i
      v-for="(s, i) in stars"
      :key="i"
      :class="
        s === 'full'
          ? 'fa-solid fa-star'
          : s === 'half'
            ? 'fa-solid fa-star-half-stroke'
            : 'fa-regular fa-star'
      "
    ></i>
    <span v-if="count != null" class="count">({{ count }})</span>
  </span>
</template>

<style scoped>
.rating {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  color: var(--gold);
}
.count {
  margin-left: var(--sp-1);
  color: var(--ink-faint);
  font-size: 0.8em;
}
</style>
