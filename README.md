# Kochbuch – App

Offline-fähige Kochbuch-App auf Basis deiner bestehenden `api.php`.
Gebaut mit **Vite + Vue 3 + TypeScript + Capacitor**. Läuft als Android-APK
und als Live-Vorschau im Browser auf dem PC.

---

## 1. In WebStorm öffnen

Du hast alle JetBrains-Programme – das richtige hier ist **WebStorm**
(IntelliJ IDEA Ultimate geht auch).

1. ZIP entpacken.
2. In WebStorm: **File → Open…** und den entpackten Ordner `kochbuch-app` wählen
   (den Ordner, **nicht** eine einzelne Datei).
3. WebStorm erkennt das Projekt automatisch. Einmal im Terminal unten:

```bash
npm install
```

---

## 2. Server-Adresse eintragen (wichtig!)

Trage die Adresse deines Raspberry Pi an **zwei** Stellen ein – am besten die
Tailscale-Adresse, dann erreichst du den Pi auch von unterwegs:

- `src/config.ts` → Konstante `PI_SERVER`
- `vite.config.ts` → Konstante `PI_SERVER`

```ts
// Beispiele:
const PI_SERVER = 'http://100.x.y.z/Kochbuch/'      // Tailscale
const PI_SERVER = 'http://192.168.178.50/Kochbuch/' // nur Heimnetz
```

> Mit **abschließendem Slash**. Die App ruft daraus `…/api.php` und `…/uploads/…` auf.

Dann einmalig **vier Zeilen CORS** in deine `api.php` einfügen –
siehe `api-cors-snippet.php`. (Für die App auf dem Handy nicht zwingend nötig,
da Capacitor CORS nativ umgeht, aber für die Browser-Vorschau praktisch.)

---

## 3. Live-Vorschau auf dem PC (dein „tauri dev")

```bash
npm run dev
```

Öffnet die App unter `http://localhost:5173` mit Hot-Reload – Änderungen am Code
erscheinen sofort. Anfragen an den Pi laufen im Dev-Modus über einen Proxy
(`/pi-api/`), damit kein CORS-Problem entsteht.

---

## 4. APK bauen – per GitHub Action (kein Android-SDK nötig)

Du installierst **nichts** lokal für Android. Der Build läuft in der Cloud:

1. Neues GitHub-Repository anlegen und das Projekt hochladen:

```bash
git init
git add .
git commit -m "Kochbuch App"
git branch -M main
git remote add origin https://github.com/DEIN-NAME/kochbuch-app.git
git push -u origin main
```

2. Auf GitHub in den Tab **Actions** wechseln. Der Workflow „Android APK bauen"
   startet automatisch bei jedem Push (oder manuell über **Run workflow**).
3. Nach ~3–5 Minuten ist der Lauf grün. Unten unter **Artifacts** liegt
   **`kochbuch-apk`** zum Herunterladen (ZIP mit der `app-debug.apk` darin).
4. APK auf dein Handy kopieren, antippen, „Installation aus unbekannten Quellen"
   erlauben, installieren. Fertig.

> Es ist eine **Debug-APK** – perfekt zum Sideloaden in der Familie. Für eine
> signierte Release-Version müsste man später einen Keystore ergänzen.

---

## 5. Was die App kann

**Lesen – auch offline** (zuletzt geladener Stand wird in IndexedDB gecacht):
- Startseite mit rotierendem Hero, Kategorien, „Heute geplant", Zufall/Shake,
  Neueste, Zuletzt aufgerufen
- Suche mit Filter (Sortierung, Zeit, Kategorie)
- Rezeptansicht mit Bildern, **Portions-Umrechnung**, Zutaten zum Abhaken,
  Zubereitung, Anmerkungen, Bewertungen, Drucken
- Einkaufsliste
- Kalender (nach Datum gruppiert)
- 7 Themes (hell/dunkel + Halloween, Weihnachten, Frühling, Dracula, Mitternacht)

**Schreiben & Verwalten – nur online** (offline ausgegraut, weil dein Pi die
Daten und Bilder verarbeitet):
- **Rezepte anlegen & bearbeiten**: Name, Kategorie, Dauer, Portionen,
  Küchengeräte, Zusatzinfos, Zutaten-Editor (Suchen, neue Zutaten anlegen,
  Mengen/Infos, mehrere Tabellen), Zubereitung (Rich-Text), **Bild-Upload**
  (wird serverseitig zu WebP konvertiert) inkl. Löschen vorhandener Bilder
- **Rezept löschen**, **bewerten** (anlegen/ändern/löschen), **Anmerkung**
  bearbeiten, **auf den Kalender** setzen
- **Einkaufsliste**: Einträge löschen, ganzes Rezept per Bring hinzufügen
- **Kalender**: Einträge anlegen/bearbeiten/löschen
- **Verwaltung** unter Einstellungen: Kategorien, Zutaten (inkl. Icon-Upload),
  Küchengeräte – jeweils anlegen/bearbeiten/löschen

Damit ersetzt die App die alte Website vollständig. Reines Lesen/Nachkochen
funktioniert weiter offline.

> **Server:** Die App spricht den neuen Ordner `KochbuchNewApi` an (siehe
> `server.zip`). Adresse in `src/config.ts` → `PI_SERVER`. Die schreibenden
> Funktionen brauchen die überarbeitete API mit CORS und JSON-Rückgabe; lege
> dazu `server.zip` wie in dessen README beschrieben auf dem Pi ab.

---

## 6. Projektstruktur

```
src/
  config.ts            Server-Adresse + URL-Helfer (mediaUrl, apiUrl)
  main.ts              App-Start (Fonts, Styles, Pinia, Router)
  router/              Routen
  styles/              tokens.css (Design-System) + base.css
  types/models.ts      TypeScript-Typen zur API
  services/
    cache.ts           IndexedDB-Wrapper
    network.ts         Online-Status
    http.ts            network-first + Offline-Fallback
    api.ts             typisierte Lese-API (+ Zutaten-/Geräte-Suche)
    writeApi.ts        alle schreibenden Aufrufe (Rezept, Bewertung, …)
  stores/ui.ts         Theme-Status (Pinia)
  components/          NavBar, RecipeCard, RecipeGrid, StarRating,
                       OfflineBanner, Modal, RichText
  views/               Home, Search, Recipe, RecipeEdit (Anlegen/Bearbeiten),
                       Cart, Calendar, Settings, Categories, Ingredients,
                       Appliances, NotFound
.github/workflows/     android.yml (APK-Build)
capacitor.config.ts    App-ID, CapacitorHttp (CORS-Umgehung auf dem Gerät)
```

---

## 7. Design-Notizen

Weg vom rosa Pastell, hin zu einem **warmen, redaktionellen Kochmagazin-Look**:
cremiges Papier als Hintergrund, tiefe Tinte als Text, ein Terrakotta-Akzent und
Gold für Sterne. Display-Schrift **Fraunces**, Lese-Schrift **Work Sans** – beide
gebündelt, also offline verfügbar. Konsistente Abstände (8pt-Raster), klare
Radien und Schatten, große Touch-Targets, auf dem Handy eine Bottom-Navigation
mit Safe-Area-Abständen. Alle Farben laufen über CSS-Variablen, daher sind neue
Themes mit wenigen Zeilen ergänzbar.

---

## 8. Nächste mögliche Schritte

- **Bilder offline cachen** (aktuell wird nur die JSON-Antwort gecacht; Bilder
  brauchen online erstmaligen Abruf). Machbar über die Cache API oder
  IndexedDB-Blobs für angesehene Rezepte.
- **Release-Signierung** der APK über einen Keystore.
- **Drag & Drop** für Zutaten-Reihenfolge im Editor (aktuell: Tabellen-Zuordnung
  über Auswahl; reicht funktional, ginge aber komfortabler).
```
