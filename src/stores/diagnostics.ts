import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getDiagnostics, type ServerDiagnosticCheck } from '@/services/api'
import { mergeZutaten, fixIngredientUnit } from '@/services/writeApi'

// ─── Typen ───────────────────────────────────────────────────────────────────

export type { ServerDiagnosticCheck as DiagnosticCheck }

export interface DiagnosticIssue {
  rezepte_ID: number
  name: string
  details?: string
  merge_ids?: number[]
}

// ─── Store ───────────────────────────────────────────────────────────────────

export const useDiagnosticsStore = defineStore('diagnostics', () => {
  const loading    = ref(false)
  const merging    = ref<number | null>(null)
  const fixing     = ref<number | null>(null) // zutat_id being fixed
  const lastChecked = ref<Date | null>(null)
  const results    = ref<ServerDiagnosticCheck[]>([])
  const error      = ref('')

  const hasErrors = computed(() =>
    results.value.some((c) => c.severity === 'error' && c.issues.length > 0),
  )
  const hasIssues = computed(() =>
    results.value.some((c) => c.issues.length > 0),
  )
  const totalIssues = computed(() =>
    results.value.reduce((sum, c) => sum + c.issues.length, 0),
  )

  async function runChecks(force = false) {
    loading.value = true
    error.value = ''
    try {
      const data = await getDiagnostics(force)
      if (!data.success) throw new Error('Server-Fehler')
      results.value = data.checks ?? []
      lastChecked.value = data.computed_at ? new Date(data.computed_at * 1000) : new Date()
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Fehler beim Laden der Diagnose'
    } finally {
      loading.value = false
    }
  }

  function rescan() {
    return runChecks(true)
  }

  /** Führt eine Duplikat-Gruppe zusammen (keep_id bleibt, merge_ids werden gelöscht). */
  async function mergeGroup(keepId: number, allIds: number[]) {
    merging.value = keepId
    error.value = ''
    try {
      const mergeIds = allIds.filter((id) => id !== keepId)
      await mergeZutaten(keepId, mergeIds)
      // Nach erfolgreichem Merge: Diagnose neu laden (force, damit Cache verworfen wird)
      await runChecks(true)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Fehler beim Zusammenführen'
    } finally {
      merging.value = null
    }
  }

  async function fixUnit(zutatId: number, newUnit: string) {
    fixing.value = zutatId
    error.value = ''
    try {
      await fixIngredientUnit(zutatId, newUnit)
      await runChecks(true)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Fehler beim Korrigieren der Einheit'
    } finally {
      fixing.value = null
    }
  }

  return { loading, merging, fixing, lastChecked, results, error, hasErrors, hasIssues, totalIssues, runChecks, rescan, mergeGroup, fixUnit }
})
