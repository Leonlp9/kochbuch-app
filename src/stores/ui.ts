import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { ThemeName } from '@/types/models'

const THEMES: ThemeName[] = [
  'light',
  'dark',
  'helloween',
  'christmas',
  'spring',
  'dracula',
  'midnight',
]

export const THEME_LABELS: Record<ThemeName, string> = {
  light: 'Hell',
  dark: 'Dunkel',
  helloween: 'Halloween',
  christmas: 'Weihnachten',
  spring: 'Frühling',
  dracula: 'Dracula',
  midnight: 'Mitternacht',
}

export const useUiStore = defineStore('ui', () => {
  const theme = ref<ThemeName>('light')

  function applyTheme(t: ThemeName) {
    theme.value = t
    document.documentElement.setAttribute('data-theme', t)
    try {
      localStorage.setItem('theme', t)
    } catch {
      /* ignore */
    }
  }

  function initTheme() {
    let stored: string | null = null
    try {
      stored = localStorage.getItem('theme')
    } catch {
      /* ignore */
    }
    applyTheme((stored as ThemeName) ?? 'light')
  }

  return { theme, themes: THEMES, applyTheme, initTheme }
})
