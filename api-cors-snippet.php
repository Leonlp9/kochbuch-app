<?php
/* =====================================================================
   CORS-SNIPPET fuer deine bestehende api.php (auf dem Raspberry Pi)
   ---------------------------------------------------------------------
   Damit die App (und die Browser-Vorschau) auf die API zugreifen darf,
   fuege die folgenden vier Zeilen in api.php DIREKT NACH der Zeile

       header('Content-Type: application/json');

   ein. Mehr ist nicht noetig.
   ===================================================================== */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* Hinweis: In der gebauten Android-App umgeht CapacitorHttp CORS ohnehin
   nativ – das Snippet ist vor allem fuer die Browser-Vorschau (npm run dev)
   relevant, wo der Vite-Proxy genutzt wird. Schadet aber nie. */
