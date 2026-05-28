# Kochbuch – Server-API

Überarbeitete `api.php` für die App. Sie ist **abwärtskompatibel** zur alten
Website. Du hast zwei Möglichkeiten:

---

## Variante A (empfohlen): neuer, separater Ordner `KochbuchNewApi`
Das alte `Kochbuch` bleibt komplett unangetastet, die App nutzt den neuen Ordner.

Inhalt dieser ZIP:
```
KochbuchNewApi/
  api.php                überarbeitete API (CORS inkl., JSON/Redirect-Weiche, editZutat/deleteZutat)
  .htaccess              CORS-Header für Bilder (für den Offline-Cache der App)
  config.ini.example     Vorlage – zu config.ini machen (oder alte kopieren)
  shared/global.php      DB-Verbindung + Tabellen (gemeinsame DB "kochbuch")
  shared/BringApi.php    Bring-Integration
  ingredientIcons/       alle Zutaten-Icons (enthalten, inkl. default.svg)
  uploads/               leer – Rezept-/Gerätebilder hierher kopieren (siehe unten)
```

1. Ordner `KochbuchNewApi` neben `Kochbuch` auf den Pi legen, Inhalt reinkopieren.
2. `config.ini` anlegen (am einfachsten die aus `Kochbuch` kopieren – gleiche DB).
3. Bilder kopieren: `cp -r .../Kochbuch/uploads/* .../KochbuchNewApi/uploads/`
4. `sudo a2enmod headers && sudo systemctl restart apache2` (für Bild-CORS).
5. Rechte: `sudo chown -R www-data:www-data .../KochbuchNewApi/ && sudo chmod -R 775 .../KochbuchNewApi/`
6. Test: `http://<PI>/KochbuchNewApi/api.php?task=getKategorien` → JSON = läuft.

In der App ist `PI_SERVER` bereits auf `.../KochbuchNewApi/` gesetzt.

---

## Variante B: alte `api.php` einfach überschreiben (eine API für App + Website)
Funktioniert, weil die API jetzt erkennt, wer fragt:
- **Website** (Formular „Rezept hinzufügen") → wie früher Weiterleitung zum Rezept.
- **App** (schickt `app=1`) → JSON.

So gehst du vor:
1. Im alten `Kochbuch`-Ordner **nur `api.php` ersetzen** (CORS ist eingebaut,
   `shared/cors.php` wird nicht mehr gebraucht).
2. **Wichtig: NICHT** die alte `.htaccess` überschreiben – die enthält die
   Rewrite-Regeln der Website. Wenn du den Offline-Bild-Cache der App auch hier
   willst, füge nur diesen Block in die bestehende `.htaccess` ein:
   ```apache
   <IfModule mod_headers.c>
       <FilesMatch "\.(webp|png|jpe?g|svg|gif|ico)$">
           Header set Access-Control-Allow-Origin "*"
       </FilesMatch>
   </IfModule>
   ```
   und `sudo a2enmod headers && sudo systemctl restart apache2`.
3. In der App `PI_SERVER` (in `src/config.ts`) auf den **alten** Ordner zeigen
   lassen, z. B. `http://<PI>/Kochbuch/`.
4. `shared/global.php` und `shared/BringApi.php` der alten Seite **behalten** –
   meine `api.php` kommt mit deiner alten `global.php` klar.

### Eine Einschränkung bei Variante B
Der **Backup-Button** in den Website-Einstellungen (`export_db`) funktioniert
nicht mehr – den habe ich aus Sicherheitsgründen entfernt (er ruft `mysqldump`
per Shell mit DB-Passwort auf). Alles andere auf der Website läuft normal weiter.
Wenn du den Button behalten willst, sag Bescheid – dann baue ich `export_db`
wieder ein (mit Hinweis auf das Risiko).

---

## Was sich generell geändert hat
- CORS eingebaut – die App darf zugreifen.
- `addRezept`: JSON für die App, Weiterleitung für die Website (automatische Weiche).
- Neue Endpunkte `editZutat` / `deleteZutat` für die Zutaten-Verwaltung der App.
- `deleteKategorie` / `deleteZutat` verweigern das Löschen, wenn noch verwendet.
- Entfernt: `export_db`, und die Seiten `github.php`/`setup.php` sind nicht Teil
  dieser API (liegen weiter bei dir in der alten Website).

## GD & Sicherheit
- Bild-Upload braucht PHP-`gd` (`sudo apt install php-gd`).
- Kein Login – Pi nicht offen ins Internet, sondern via **Tailscale**.
