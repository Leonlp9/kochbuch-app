<script setup lang="ts">
import { RouterLink } from 'vue-router'
import StarRating from './StarRating.vue'
import { cachedSrc } from '@/services/imageCache'
import type { SearchResult } from '@/types/models'

defineProps<{ recipe: SearchResult }>()
</script>

<template>
  <RouterLink :to="`/recipe/${recipe.rezepte_ID}`" class="rcard">
    <div class="rcard-img" :style="{ backgroundImage: `url('${cachedSrc(recipe.Image)}')` }">
      <span v-if="recipe.Zeit" class="badge"
        ><i class="fa-regular fa-clock"></i>{{ recipe.Zeit }}</span
      >
    </div>
    <div class="rcard-body">
      <h3 class="rcard-title">{{ recipe.Name }}</h3>
      <StarRating
        v-if="recipe.Rating > 0"
        :value="recipe.Rating"
        :count="recipe.RatingCount"
        size="0.8rem"
      />
    </div>
  </RouterLink>
</template>

<style scoped>
.rcard {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--r-lg);
  overflow: hidden;
  transition: transform 0.18s var(--ease), box-shadow 0.18s var(--ease);
}
.rcard:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
}
.rcard-img {
  aspect-ratio: 4 / 3;
  background-size: cover;
  background-position: center;
  background-color: var(--surface-2);
  position: relative;
}
.badge {
  position: absolute;
  bottom: var(--sp-2);
  right: var(--sp-2);
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px var(--sp-2);
  border-radius: var(--r-full);
  background: rgba(20, 16, 14, 0.62);
  color: #fff;
  font-size: var(--fs-xs);
  font-weight: 600;
  backdrop-filter: blur(4px);
}
.rcard-body {
  padding: var(--sp-3) var(--sp-3) var(--sp-4);
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}
.rcard-title {
  font-size: 1.02rem;
  font-weight: 600;
  line-height: 1.25;
}
</style>
