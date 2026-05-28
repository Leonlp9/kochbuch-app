/**
 * OTA-Update-Service
 *
 * Ablauf:
 *  1. App startet → notifyAppReady() (Pflicht! Sonst Rollback nach 10 s)
 *  2. GitHub-Releases-API prüfen (https://api.github.com/repos/Leonlp9/kochbuch-app/releases/latest)
 *  3. Ist die Tag-Version > lokale Version? → dist.zip herunterladen
 *  4. Bundle für nächsten Start vormerken (next())
 *  5. Reaktives Flag setzen → App.vue zeigt Update-Banner
 *
 * Beim nächsten App-Start wird das neue Bundle automatisch geladen.
 * Nur nativer Code (Plugins, Berechtigungen) erfordert weiterhin ein neues APK.
 */
import { CapacitorUpdater } from '@capgo/capacitor-updater'
import { Capacitor } from '@capacitor/core'
import { ref } from 'vue'

const GITHUB_REPO = 'Leonlp9/kochbuch-app'
const CURRENT_VERSION: string = __APP_VERSION__

/** Wird auf true gesetzt, sobald ein Update heruntergeladen und vorgemerkt wurde. */
export const updateReady = ref(false)

/**
 * Muss einmal beim App-Start aufgerufen werden (noch vor dem ersten Render).
 * Bestätigt dem Plugin, dass das aktuelle Bundle stabil ist → kein Rollback.
 */
export async function notifyReady(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return
  try {
    await CapacitorUpdater.notifyAppReady()
  } catch (err) {
    console.warn('[Updater] notifyAppReady fehlgeschlagen:', err)
  }
}

/**
 * Prüft GitHub-Releases auf eine neuere Version und lädt das Web-Bundle herunter.
 * Läuft vollständig im Hintergrund; wirft keine Fehler nach außen.
 */
export async function checkForUpdate(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return

  try {
    const res = await fetch(
      `https://api.github.com/repos/${GITHUB_REPO}/releases/latest`,
      { headers: { Accept: 'application/vnd.github+json' } },
    )
    if (!res.ok) return

    const release = await res.json() as {
      tag_name: string
      assets: Array<{ name: string; browser_download_url: string }>
    }

    const latestVersion = release.tag_name.replace(/^v/, '')

    if (!isNewerVersion(latestVersion, CURRENT_VERSION)) {
      console.info(`[Updater] Aktuell (${CURRENT_VERSION}) – kein Update nötig.`)
      return
    }

    // dist.zip Release-Asset suchen
    const asset = release.assets.find(a => a.name === 'dist.zip')
    if (!asset) {
      console.warn('[Updater] Kein dist.zip im Release gefunden.')
      return
    }

    console.info(`[Updater] Neue Version ${latestVersion} gefunden. Lade herunter…`)

    const bundle = await CapacitorUpdater.download({
      url: asset.browser_download_url,
      version: latestVersion,
    })

    // Für den nächsten App-Start vormerken (kein sofortiger Reload)
    await CapacitorUpdater.next({ id: bundle.id })

    updateReady.value = true
    console.info(`[Updater] Bundle ${latestVersion} bereit – wird beim nächsten Start angewendet.`)
  } catch (err) {
    console.warn('[Updater] Update-Check fehlgeschlagen:', err)
  }
}

/** Vergleicht zwei Semver-Strings (nur x.y.z, ohne Pre-Release). */
function isNewerVersion(latest: string, current: string): boolean {
  const parse = (v: string): [number, number, number] => {
    const parts = v.split('.').map(Number)
    return [parts[0] ?? 0, parts[1] ?? 0, parts[2] ?? 0]
  }
  const [lMaj, lMin, lPat] = parse(latest)
  const [cMaj, cMin, cPat] = parse(current)
  if (lMaj !== cMaj) return lMaj > cMaj
  if (lMin !== cMin) return lMin > cMin
  return lPat > cPat
}


