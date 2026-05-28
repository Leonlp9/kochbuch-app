<script setup lang="ts">
import logoUrl from '@/assets/logo.svg'

interface NavItem {
  to: string
  icon: string
  label: string
}

const items: NavItem[] = [
  { to: '/', icon: 'fa-house', label: 'Kochbuch' },
  { to: '/search', icon: 'fa-magnifying-glass', label: 'Suche' },
  { to: '/new', icon: 'fa-plus', label: 'Neu' },
  { to: '/calendar', icon: 'fa-calendar-days', label: 'Kalender' },
  { to: '/settings', icon: 'fa-sliders', label: 'Einstellungen' },
]
</script>

<template>
  <nav class="nav no-print" aria-label="Hauptnavigation">
    <div class="brand">
      <img :src="logoUrl" alt="Kochbuch" class="brand-mark" />
      <span class="brand-text">Kochbuch</span>
    </div>
    <RouterLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      class="nav-link"
      :class="{ settings: item.to === '/settings' }"
    >
      <span class="nav-icon"><i class="fa-solid" :class="item.icon"></i></span>
      <span class="nav-label">{{ item.label }}</span>
    </RouterLink>
  </nav>
</template>

<style scoped>
.nav {
  position: fixed;
  inset: 0 auto 0 0;
  width: var(--nav-w);
  background: var(--surface);
  border-right: 1px solid var(--line);
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
  padding: var(--sp-3) var(--sp-2);
  z-index: 100;
  overflow: hidden;
  transition: width 0.32s var(--ease);
}
.nav:hover {
  width: var(--nav-w-open);
  box-shadow: var(--shadow-lg);
}

.brand {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  height: 56px;
  padding: 0 var(--sp-2);
  margin-bottom: var(--sp-3);
}
.brand-mark {
  flex: 0 0 auto;
  width: 40px;
  height: 40px;
  object-fit: contain;
  border-radius: var(--r-md);
}
.brand-text {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 1.25rem;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.2s var(--ease);
}
.nav:hover .brand-text {
  opacity: 1;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  height: 52px;
  padding: 0 var(--sp-2);
  border-radius: var(--r-md);
  color: var(--ink-soft);
  transition: background 0.18s var(--ease), color 0.18s var(--ease);
}
.nav-link.settings {
  margin-top: auto;
}
.nav-link:hover {
  background: var(--surface-2);
  color: var(--ink);
}
.nav-link.router-link-active {
  background: var(--accent-soft);
  color: var(--accent-strong);
}
.nav-icon {
  flex: 0 0 auto;
  width: 40px;
  display: grid;
  place-items: center;
  font-size: 1.2rem;
}
.nav-label {
  white-space: nowrap;
  font-weight: 600;
  opacity: 0;
  transition: opacity 0.2s var(--ease);
}
.nav:hover .nav-label {
  opacity: 1;
}

/* --- Mobile: Bottom-Nav --- */
@media (max-width: 768px) {
  .nav {
    inset: auto 0 0 0;
    width: 100%;
    height: calc(var(--nav-h-mobile) + env(safe-area-inset-bottom, 0px));
    flex-direction: row;
    align-items: stretch;
    justify-content: space-around;
    gap: 0;
    padding: 0 0 env(safe-area-inset-bottom, 0px);
    border-right: none;
    border-top: 1px solid var(--line);
    transition: none;
  }
  .nav:hover {
    width: 100%;
    box-shadow: none;
  }
  .brand {
    display: none;
  }
  .nav-link {
    flex: 1;
    flex-direction: column;
    gap: 2px;
    height: auto;
    justify-content: center;
    border-radius: 0;
  }
  .nav-link.settings {
    margin-top: 0;
  }
  .nav-link.router-link-active {
    background: transparent;
    color: var(--accent);
  }
  .nav-link.router-link-active .nav-icon {
    transform: translateY(-1px);
  }
  .nav-icon {
    width: auto;
    font-size: 1.25rem;
  }
  .nav-label {
    opacity: 1;
    font-size: 0.66rem;
    font-weight: 600;
  }
}
</style>
