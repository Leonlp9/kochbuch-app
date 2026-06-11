<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDiagnosticsStore } from '@/stores/diagnostics'

const store = useDiagnosticsStore()
const router = useRouter()

onMounted(() => {
  // Immer laden – der Server entscheidet ob Neu-Berechnung nötig ist (Cache 24h)
  void store.runChecks()
})

function goEdit(id: number) {
  router.push(`/edit/${id}`)
}

function formatDate(d: Date | null) {
  if (!d) return '–'
  const diff = Math.floor((Date.now() - d.getTime()) / 1000)
  if (diff < 60)  return 'gerade eben'
  if (diff < 3600) return `vor ${Math.floor(diff / 60)} Min.`
  if (diff < 86400) return `vor ${Math.floor(diff / 3600)} Std.`
  return d.toLocaleDateString('de-DE') + ', ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="container diag-page">
    <!-- Header -->
    <header class="diag-header">
      <div class="diag-title-row">
        <div class="diag-icon-wrap">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
          <h1>Diagnose</h1>
          <p class="eyebrow">
            {{ store.lastChecked ? 'Berechnet ' + formatDate(store.lastChecked) : 'Wird geladen…' }}
          </p>
        </div>
      </div>
      <button class="btn btn--primary scan-btn" :disabled="store.loading" @click="store.rescan()">
        <i class="fa-solid" :class="store.loading ? 'fa-spinner fa-spin' : 'fa-rotate-right'"></i>
        {{ store.loading ? 'Scanne…' : 'Neu berechnen' }}
      </button>
    </header>

    <!-- Fehler -->
    <div v-if="store.error" class="diag-error">
      <i class="fa-solid fa-circle-xmark"></i> {{ store.error }}
    </div>

    <!-- Lade-State -->
    <div v-if="store.loading" class="diag-loading">
      <div class="skeleton" style="height: 120px; border-radius: var(--r-lg);" v-for="n in 4" :key="n"></div>
    </div>

    <!-- Kein Ergebnis noch -->
    <div v-else-if="store.results.length === 0 && !store.error" class="diag-empty">
      <i class="fa-solid fa-circle-info"></i>
      <p>Klicke auf „Neu berechnen", um alle Rezepte zu prüfen.</p>
    </div>

    <!-- Alles OK -->
    <div v-else-if="!store.hasIssues && store.results.length > 0" class="diag-ok">
      <i class="fa-solid fa-circle-check"></i>
      <p>Keine Probleme gefunden – alles in Ordnung!</p>
    </div>

    <!-- Ergebnisse -->
    <template v-else-if="store.results.length > 0">
      <!-- Zusammenfassung -->
      <div class="diag-summary">
        <div class="summary-stat" :class="store.hasErrors ? 'stat--error' : 'stat--warn'">
          <span class="stat-num">{{ store.totalIssues }}</span>
          <span class="stat-label">Probleme gefunden</span>
        </div>
        <div class="summary-stat stat--err-count" v-if="store.hasErrors">
          <span class="stat-num">
            {{ store.results.filter(c => c.severity === 'error' && c.issues.length).length }}
          </span>
          <span class="stat-label">Fehler-Kategorien</span>
        </div>
        <div class="summary-stat stat--warn-count">
          <span class="stat-num">
            {{ store.results.filter(c => c.severity === 'warning' && c.issues.length).length }}
          </span>
          <span class="stat-label">Warnungs-Kategorien</span>
        </div>
      </div>

      <!-- Check-Karten: nur die mit Issues -->
      <section
        v-for="check in store.results.filter(c => c.issues.length > 0)"
        :key="check.id"
        class="check-card"
        :class="`check-card--${check.severity}`"
      >
        <div class="check-head">
          <span class="check-badge" :class="`badge--${check.severity}`">
            <i class="fa-solid" :class="check.severity === 'error' ? 'fa-circle-xmark' : 'fa-triangle-exclamation'"></i>
            {{ check.severity === 'error' ? 'Fehler' : 'Warnung' }}
          </span>
          <h2 class="check-title">{{ check.title }}</h2>
          <span class="check-count">{{ check.issues.length }} {{ check.is_merge_check ? 'Gruppe(n)' : (check.issues.length !== 1 ? 'Rezepte' : 'Rezept') }}</span>
        </div>

        <p class="check-desc">{{ check.description }}</p>

        <!-- Merge-Check: Doppelte Zutaten -->
        <ul v-if="check.is_merge_check" class="issue-list">
          <li
            v-for="issue in check.issues"
            :key="issue.rezepte_ID"
            class="issue-item merge-item"
          >
            <div class="merge-row">
              <div class="merge-info">
                <i class="fa-solid fa-layer-group merge-icon"></i>
                <span class="issue-name">{{ issue.name }}</span>
                <span class="issue-details">{{ issue.details }}</span>
                <span class="merge-ids">IDs: {{ issue.merge_ids?.join(', ') }}</span>
              </div>
              <button
                class="btn btn--accent btn--sm merge-btn"
                :disabled="store.merging !== null"
                @click="issue.merge_ids && store.mergeGroup(issue.merge_ids[0], issue.merge_ids)"
              >
                <i class="fa-solid" :class="store.merging === issue.rezepte_ID ? 'fa-spinner fa-spin' : 'fa-code-merge'"></i>
                {{ store.merging === issue.rezepte_ID ? 'Führe zusammen…' : 'Zusammenführen' }}
              </button>
            </div>
          </li>
        </ul>

        <!-- Normale Checks: Rezepte-Liste -->
        <ul v-else class="issue-list">
          <li
            v-for="issue in check.issues"
            :key="issue.rezepte_ID"
            class="issue-item"
          >
            <button class="issue-btn" @click="goEdit(issue.rezepte_ID)">
              <span class="issue-name">{{ issue.name }}</span>
              <span v-if="issue.details" class="issue-details">{{ issue.details }}</span>
              <i class="fa-solid fa-pen-to-square issue-edit-icon"></i>
            </button>
          </li>
        </ul>
      </section>

      <!-- Checks ohne Issues (grün, zusammengeklappt) -->
      <details class="ok-checks">
        <summary>
          <i class="fa-solid fa-circle-check"></i>
          {{ store.results.filter(c => c.issues.length === 0).length }} Prüfungen bestanden
        </summary>
        <ul class="ok-list">
          <li v-for="check in store.results.filter(c => c.issues.length === 0)" :key="check.id">
            <i class="fa-solid fa-check"></i> {{ check.title }}
          </li>
        </ul>
      </details>
    </template>
  </div>
</template>

<style scoped>
.diag-page {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
  padding-bottom: var(--sp-8);
}

/* ── Header ─────────────────────────────────────────────────────────── */
.diag-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  flex-wrap: wrap;
}
.diag-title-row {
  display: flex;
  align-items: center;
  gap: var(--sp-4);
}
.diag-icon-wrap {
  width: 52px;
  height: 52px;
  border-radius: var(--r-lg);
  background: color-mix(in srgb, #f59e0b 18%, var(--surface));
  color: #f59e0b;
  display: grid;
  place-items: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}
.diag-header h1 {
  font-size: var(--fs-h2);
  margin: 0;
}
.eyebrow {
  font-size: var(--fs-xs, 0.75rem);
  color: var(--ink-soft);
  font-weight: 500;
  margin: 0;
}
.scan-btn { flex-shrink: 0; }

/* ── Loading ──────────────────────────────────────────────────────────── */
.diag-loading {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

/* ── Error ────────────────────────────────────────────────────────────── */
.diag-error {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  background: color-mix(in srgb, #ef4444 12%, var(--surface));
  color: #ef4444;
  padding: var(--sp-3) var(--sp-4);
  border-radius: var(--r-md);
  font-weight: 600;
  font-size: var(--fs-sm);
}

/* ── Empty / OK ───────────────────────────────────────────────────────── */
.diag-empty,
.diag-ok {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-8) var(--sp-4);
  text-align: center;
  color: var(--ink-soft);
}
.diag-empty i { font-size: 2.5rem; }
.diag-ok i { font-size: 2.5rem; color: var(--green, #5c8054); }

/* ── Zusammenfassung ──────────────────────────────────────────────────── */
.diag-summary {
  display: flex;
  gap: var(--sp-3);
  flex-wrap: wrap;
}
.summary-stat {
  flex: 1;
  min-width: 120px;
  padding: var(--sp-4);
  border-radius: var(--r-lg);
  background: var(--surface-2);
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
}
.stat--error { background: color-mix(in srgb, #ef4444 14%, var(--surface)); }
.stat--warn  { background: color-mix(in srgb, #f59e0b 14%, var(--surface)); }
.stat--err-count { background: color-mix(in srgb, #ef4444 10%, var(--surface)); }
.stat--warn-count { background: color-mix(in srgb, #f59e0b 10%, var(--surface)); }
.stat-num {
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 700;
  line-height: 1;
}
.stat-label {
  font-size: var(--fs-xs, 0.75rem);
  color: var(--ink-soft);
  font-weight: 500;
}

/* ── Check-Karte ────────────────────────────────────────────────────────── */
.check-card {
  border-radius: var(--r-lg);
  border: 1px solid var(--line);
  overflow: hidden;
}
.check-card--error  { border-color: color-mix(in srgb, #ef4444 40%, var(--line)); }
.check-card--warning { border-color: color-mix(in srgb, #f59e0b 40%, var(--line)); }

.check-head {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-5);
  background: var(--surface-2);
  flex-wrap: wrap;
}
.check-card--error .check-head   { background: color-mix(in srgb, #ef4444 8%, var(--surface)); }
.check-card--warning .check-head { background: color-mix(in srgb, #f59e0b 8%, var(--surface)); }

.check-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: var(--r-full, 999px);
  font-size: var(--fs-xs, 0.72rem);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.badge--error   { background: color-mix(in srgb, #ef4444 18%, var(--surface)); color: #ef4444; }
.badge--warning { background: color-mix(in srgb, #f59e0b 18%, var(--surface)); color: #b45309; }

.check-title {
  font-size: var(--fs-body);
  font-weight: 700;
  margin: 0;
  flex: 1;
}
.check-count {
  font-size: var(--fs-sm, 0.875rem);
  color: var(--ink-soft);
  font-weight: 500;
}

.check-desc {
  padding: var(--sp-3) var(--sp-5);
  font-size: var(--fs-sm, 0.875rem);
  color: var(--ink-soft);
  margin: 0;
  border-bottom: 1px solid var(--line);
}

/* ── Issue-Liste ─────────────────────────────────────────────────────────── */
.issue-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.issue-item:not(:last-child) {
  border-bottom: 1px solid var(--line);
}
.issue-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-5);
  background: none;
  border: none;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s var(--ease);
  color: var(--ink);
}
.issue-btn:hover { background: var(--surface-2); }
.issue-name {
  flex: 1;
  font-weight: 600;
  font-size: var(--fs-sm, 0.875rem);
}
.issue-details {
  font-size: var(--fs-xs, 0.75rem);
  color: var(--ink-soft);
  white-space: nowrap;
}
.issue-edit-icon {
  color: var(--ink-soft);
  font-size: 0.85rem;
  opacity: 0;
  transition: opacity 0.15s var(--ease);
}
.issue-btn:hover .issue-edit-icon { opacity: 1; }

/* ── Merge-Zeile ─────────────────────────────────────────────────────────── */
.merge-item {
  padding: var(--sp-3) var(--sp-5);
}
.merge-row {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  flex-wrap: wrap;
}
.merge-info {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  flex: 1;
  flex-wrap: wrap;
}
.merge-icon { color: var(--ink-soft); font-size: 0.9rem; }
.merge-ids {
  font-size: var(--fs-xs, 0.72rem);
  color: var(--ink-faint);
  font-family: monospace;
}
.btn--sm {
  padding: var(--sp-2) var(--sp-3);
  min-height: 36px;
  font-size: var(--fs-sm);
  white-space: nowrap;
  flex-shrink: 0;
}
.merge-btn { gap: var(--sp-2); }

/* ── Bestandene Checks ───────────────────────────────────────────────────── */
.ok-checks {
  border: 1px solid var(--line);
  border-radius: var(--r-lg);
  overflow: hidden;
}
.ok-checks summary {
  padding: var(--sp-3) var(--sp-5);
  cursor: pointer;
  font-weight: 600;
  font-size: var(--fs-sm, 0.875rem);
  color: var(--ink-soft);
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  background: var(--surface-2);
  user-select: none;
}
.ok-checks summary i { color: var(--green, #5c8054); }
.ok-list {
  list-style: none;
  margin: 0;
  padding: var(--sp-2) var(--sp-5) var(--sp-3);
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}
.ok-list li {
  font-size: var(--fs-sm, 0.875rem);
  color: var(--ink-soft);
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}
.ok-list li i { color: var(--green, #5c8054); font-size: 0.8rem; }
</style>
