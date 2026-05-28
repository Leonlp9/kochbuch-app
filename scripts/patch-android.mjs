/**
 * patch-android.mjs
 *
 * Wird nach `cap sync android` ausgefuehrt und setzt Android-Build-Werte,
 * die nicht ueber capacitor.config.ts steuerbar sind.
 * Der android/-Ordner ist gitigniert, daher sichert dieses Script die Konfiguration.
 */

import { readFileSync, writeFileSync } from 'fs'
import { resolve } from 'path'
import { fileURLToPath } from 'url'

const root = resolve(fileURLToPath(new URL('.', import.meta.url)), '..')

function patch(filePath, replacements) {
  let content = readFileSync(filePath, 'utf8')
  let changed = false
  for (const [search, replace] of replacements) {
    const next = content.replace(search, replace)
    if (next !== content) {
      changed = true
      content = next
    }
  }
  if (changed) {
    writeFileSync(filePath, content, 'utf8')
    console.log(`✅  Patched: ${filePath}`)
  } else {
    console.log(`ℹ️   Already up-to-date: ${filePath}`)
  }
}

// 1) variables.gradle – compileSdk + targetSdk auf 35
patch(resolve(root, 'android/variables.gradle'), [
  [/compileSdkVersion\s*=\s*\d+/, 'compileSdkVersion = 35'],
  [/targetSdkVersion\s*=\s*\d+/,  'targetSdkVersion = 35'],
])

// 2) android/build.gradle – Android Gradle Plugin auf 8.7.3
//    (AGP 8.5 benoetigt Gradle >= 8.7, AGP 8.7 benoetigt Gradle >= 8.9)
patch(resolve(root, 'android/build.gradle'), [
  [
    /classpath\s+'com\.android\.tools\.build:gradle:[^']+'/,
    "classpath 'com.android.tools.build:gradle:8.7.3'",
  ],
])

// 3) Gradle Wrapper – auf 8.9 aktualisieren (kompatibel mit AGP 8.7.x)
patch(resolve(root, 'android/gradle/wrapper/gradle-wrapper.properties'), [
  [
    /distributionUrl=.*/,
    'distributionUrl=https\\://services.gradle.org/distributions/gradle-8.9-all.zip',
  ],
])

console.log('\n🎉 Android-Patch abgeschlossen.')


