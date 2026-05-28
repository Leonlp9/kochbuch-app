<script setup lang="ts">
import RecipeCard from './RecipeCard.vue'
import type { SearchResult } from '@/types/models'

defineProps<{
  recipes: SearchResult[]
  loading?: boolean
  skeletonCount?: number
  /** Index ab dem neue Karten animiert eingeblendet werden sollen */
  loadOffset?: number
}>()
</script>

<template>
  <div class="grid">
    <template v-if="loading">
      <div v-for="n in skeletonCount ?? 6" :key="n" class="sk-card">
        <div class="skeleton sk-img"></div>
        <div class="skeleton sk-line"></div>
      </div>
    </template>
    <RecipeCard
      v-for="(r, i) in recipes"
      v-else
      :key="r.rezepte_ID"
      :recipe="r"
      :class="i >= (loadOffset ?? 0) ? 'rise' : ''"
      :style="i >= (loadOffset ?? 0)
        ? { animationDelay: `${Math.min((i - (loadOffset ?? 0)) * 35, 350)}ms` }
        : {}"
    />
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--sp-4);
}
@media (max-width: 768px) {
  .grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: var(--sp-3);
  }
}
.sk-card {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}
.sk-img {
  aspect-ratio: 4 / 3;
  border-radius: var(--r-lg);
}
.sk-line {
  height: 16px;
  width: 75%;
  border-radius: var(--r-sm);
}
</style>
