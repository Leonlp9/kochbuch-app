<?php

// PHP-Fehler/Warnungen NICHT in den Response-Body ausgeben – würde JSON-Parsing brechen.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

header('Content-Type: application/json');

// CORS für die App (eigenständig, keine separate Datei nötig).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Max-Age: 86400');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include 'shared/global.php';
global $pdo;

if (!isset($_GET['task'])) {
    echo json_encode(['error' => 'No task provided']);
    die();
}

$task = $_GET['task'];

switch ($task) {
    case 'getImages':
        if (isset($_GET['rezept_id'])) {
            $id = $_GET['rezept_id'];
            $sql = $pdo->prepare('SELECT * FROM bilder WHERE Rezept_ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();
            $bilder = $sql->fetchAll(PDO::FETCH_ASSOC);

            foreach ($bilder as &$bild) {
                $bild['Image'] = 'uploads/' . $bild['Image'];
            }

            echo json_encode($bilder);
            die();
        } else {
            echo json_encode(['error' => 'No rezept_id provided']);
            die();
        }
    case 'deleteImage':
        if (isset($_GET['rezept_id']) && isset($_GET['image'])) {
            $rezept_id = $_GET['rezept_id'];
            $image = $_GET['image'];

            $sql = $pdo->prepare('SELECT * FROM bilder WHERE Rezept_ID = :rezept_id AND ID = :image');
            $sql->bindValue(':rezept_id', $rezept_id);
            $sql->bindValue(':image', $image);
            $sql->execute();
            $bild = $sql->fetch(PDO::FETCH_ASSOC);

            if ($bild && file_exists("uploads/" . $bild['Image'])) {
                unlink("uploads/" . $bild['Image']);
            }

            $sql = $pdo->prepare('DELETE FROM bilder WHERE Rezept_ID = :rezept_id AND ID = :image');
            $sql->bindValue(':rezept_id', $rezept_id);
            $sql->bindValue(':image', $image);
            $sql->execute();

            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'No rezept_id or image provided']);
            die();
        }
    case 'deleteRezept':
        if (isset($_GET['id'])) {
            $id = $_GET['id'];

            $stmt = $pdo->prepare("SELECT * FROM bilder WHERE Rezept_ID = ?");
            $stmt->execute([$id]);
            $bilder = $stmt->fetchAll();
            foreach ($bilder as $bild) {
                if (file_exists("uploads/" . $bild['Image'])) {
                    unlink("uploads/" . $bild['Image']);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM rezepte WHERE ID = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM bilder WHERE Rezept_ID = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM bewertungen WHERE Rezept_ID = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM anmerkungen WHERE Rezept_ID = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM kalender WHERE Rezept_ID = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'No id provided']);
            die();
        }
    case 'getZutaten':
        $name = isset($_GET['name']) ? $_GET['name'] : '';
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 20;

        if (substr($name, -1) == '*') {
            $name = substr($name, 0, -1);
            $limit = 10000;
        }

        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $sql = $pdo->prepare('SELECT * FROM zutaten WHERE ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();
            $zutaten = $sql->fetchAll(PDO::FETCH_ASSOC);

            foreach ($zutaten as $key => $zutat) {
                if (!file_exists('ingredientIcons/' . $zutat['Image'])) {
                    $zutaten[$key]['Image'] = 'ingredientIcons/default.svg';
                } else {
                    $zutaten[$key]['Image'] = 'ingredientIcons/' . $zutat['Image'];
                }
            }

            echo json_encode($zutaten);
            die();
        }

        $sql = $pdo->prepare('
            SELECT *
            FROM zutaten
            WHERE Name LIKE :name
            ORDER BY
                CASE WHEN Name LIKE :prefix THEN 0 ELSE 1 END,
                Name
            LIMIT :limit
        ');

        $prefix = $name . '%';
        $fullText = '%' . $name . '%';

        $sql->bindValue(':name', $fullText);
        $sql->bindValue(':prefix', $prefix);
        $sql->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $sql->execute();
        $zutaten = $sql->fetchAll(PDO::FETCH_ASSOC);

        foreach ($zutaten as $key => $zutat) {
            if (!file_exists('ingredientIcons/' . $zutat['Image'])) {
                $zutaten[$key]['Image'] = 'ingredientIcons/default.svg';
            } else {
                $zutaten[$key]['Image'] = 'ingredientIcons/' . $zutat['Image'];
            }
        }

        echo json_encode($zutaten);
        die();
    case 'getRezept':
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $sql = $pdo->prepare('SELECT * FROM rezepte WHERE ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();
            $rezepte = $sql->fetchAll(PDO::FETCH_ASSOC);

            if (count($rezepte) == 0) {
                echo json_encode(['error' => 'No recipe found']);
                die();
            }

            if (isset($_GET['zutaten'])) {
                $zutaten = json_decode($rezepte[0]['Zutaten_JSON'], true);
                $zutaten_array = [];
                if (is_array($zutaten)) {
                    foreach ($zutaten as $zutat) {
                        $sql = $pdo->prepare('SELECT Name, unit, Image FROM zutaten WHERE ID = :id');
                        $sql->bindValue(':id', $zutat['ID']);
                        $sql->execute();
                        $zutat_name = $sql->fetch(PDO::FETCH_ASSOC);
                        $zutaten_array[] = [
                            'ID' => $zutat['ID'],
                            'Menge' => $zutat['Menge'],
                            'unit' => $zutat_name['unit'] ?? '',
                            'Name' => $zutat_name['Name'] ?? '',
                            'Image' => $zutat_name['Image'] ?? 'default.svg',
                            'additionalInfo' => $zutat['additionalInfo'] ?? '',
                            'table' => $zutat['table'] ?? ''
                        ];
                    }
                }
                $rezepte[0]['Zutaten_JSON'] = $zutaten_array;

                foreach ($rezepte[0]['Zutaten_JSON'] as &$zutat) {
                    if (!file_exists('ingredientIcons/' . $zutat['Image'])) {
                        $zutat['Image'] = 'ingredientIcons/default.svg';
                    } else {
                        $zutat['Image'] = 'ingredientIcons/' . $zutat['Image'];
                    }
                }
                unset($zutat);

                $zutatenTables = [""];
                foreach ($zutaten_array as $zutat) {
                    if (!in_array($zutat['table'], $zutatenTables)) {
                        $zutatenTables[] = $zutat['table'];
                    }
                }
                $rezepte[0]['ZutatenTables'] = $zutatenTables;
            } else {
                unset($rezepte[0]['Zutaten_JSON']);
            }

            $sql = $pdo->prepare('SELECT Name, ColorHex FROM kategorien WHERE ID = :id');
            $sql->bindValue(':id', $rezepte[0]['Kategorie_ID']);
            $sql->execute();
            $kategorie = $sql->fetch(PDO::FETCH_ASSOC);
            $rezepte[0]['Kategorie'] = $kategorie['Name'] ?? '';
            $rezepte[0]['KategorieColor'] = $kategorie['ColorHex'] ?? '#888888';

            $sql = $pdo->prepare('SELECT * FROM anmerkungen WHERE Rezept_ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();
            $rezepte[0]['Anmerkungen'] = $sql->fetchAll(PDO::FETCH_ASSOC);

            $sql = $pdo->prepare('SELECT * FROM bewertungen WHERE Rezept_ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();
            $rezepte[0]['Bewertungen'] = $sql->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rezepte[0]['Bewertungen'] as &$bewertung) {
                $bewertung['Image'] = 'https://api.dicebear.com/9.x/bottts-neutral/svg?seed=' . urlencode($bewertung['Name']);
            }
            unset($bewertung);

            $sql = $pdo->prepare('SELECT * FROM kalender WHERE Rezept_ID = :id AND Datum >= CURDATE()');
            $sql->bindValue(':id', $id);
            $sql->execute();
            $rezepte[0]['Kalender'] = $sql->fetchAll(PDO::FETCH_ASSOC);

            $sql = $pdo->prepare('SELECT * FROM bilder WHERE Rezept_ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();
            $bilder = $sql->fetchAll(PDO::FETCH_ASSOC);
            foreach ($bilder as &$bild) {
                $bild['Image'] = 'uploads/' . $bild['Image'];
            }
            unset($bild);
            $rezepte[0]['Bilder'] = $bilder;

            $current_timestamp = time();
            $sql = $pdo->prepare('UPDATE rezepte SET last_visit = :current_timestamp WHERE ID = :id');
            $sql->bindValue(':current_timestamp', $current_timestamp);
            $sql->bindValue(':id', $id);
            $sql->execute();

            $kitchenAppliances = json_decode($rezepte[0]['KitchenAppliances'] != null && $rezepte[0]['KitchenAppliances'] != "" ? $rezepte[0]['KitchenAppliances'] : "[]");
            $kitchenAppliances_array = [];
            if (is_array($kitchenAppliances)) {
                foreach ($kitchenAppliances as $appliance) {
                    $sql = $pdo->prepare('SELECT Name, Image FROM kitchenAppliances WHERE ID = :id');
                    $sql->bindValue(':id', $appliance);
                    $sql->execute();
                    $appliance_name = $sql->fetch(PDO::FETCH_ASSOC);
                    if ($appliance_name) {
                        $kitchenAppliances_array[] = [
                            'ID' => $appliance,
                            'Name' => $appliance_name['Name'],
                            'Image' => "uploads/" . $appliance_name['Image']
                        ];
                    }
                }
            }
            $rezepte[0]['KitchenAppliances'] = json_encode($kitchenAppliances_array);

            echo json_encode($rezepte);
            die();
        } else {
            echo json_encode(['error' => 'No id provided']);
            die();
        }
    case "addEvaluation":
        if (isset($_GET['rezept'], $_GET['rating'], $_GET['name'], $_GET['text'])) {
            $sql = $pdo->prepare('INSERT INTO bewertungen (Rezept_ID, Bewertung, Name, Text) VALUES (:rezept, :bewertung, :name, :text)');
            $sql->bindValue(':rezept', $_GET['rezept']);
            $sql->bindValue(':bewertung', $_GET['rating']);
            $sql->bindValue(':name', $_GET['name']);
            $sql->bindValue(':text', $_GET['text']);
            $sql->execute();
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided']);
            die();
        }
    case "editEvaluation":
        if (isset($_GET['rezept'], $_GET['rating'], $_GET['name'], $_GET['text'])) {
            $sql = $pdo->prepare('UPDATE bewertungen SET Bewertung = :bewertung, Name = :name, Text = :text WHERE ID = :rezept');
            $sql->bindValue(':rezept', $_GET['rezept']);
            $sql->bindValue(':bewertung', $_GET['rating']);
            $sql->bindValue(':name', $_GET['name']);
            $sql->bindValue(':text', $_GET['text']);
            $sql->execute();
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided']);
            die();
        }
    case "deleteEvaluation":
        if (isset($_GET['id'])) {
            $sql = $pdo->prepare('DELETE FROM bewertungen WHERE ID = :id');
            $sql->bindValue(':id', $_GET['id']);
            $sql->execute();
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided']);
            die();
        }
    case "search":
        if (!isset($_GET['search'])) {
            echo json_encode(['error' => 'No search term provided']);
            die();
        }

        $search = $_GET['search'];
        $order = (isset($_GET['order'])) ? $_GET['order'] : "Name";
        $zeit = (isset($_GET['zeit'])) ? $_GET['zeit'] : "4";
        $kategorie = (isset($_GET['kategorie']) && $_GET['kategorie'] != "") ? $_GET['kategorie'] : "*";
        $random = (isset($_GET['random'])) ? $_GET['random'] : false;
        $neueste = (isset($_GET['neueste'])) ? $_GET['neueste'] : false;
        $last_visit = (isset($_GET['last_visit'])) ? $_GET['last_visit'] : false;

        // Suchbegriff/Kategorie sicher einbinden
        $search = str_replace(["%", "_", "'"], ["\\%", "\\_", "''"], $search);
        $join = "LEFT JOIN bilder ON rezepte.ID = bilder.Rezept_ID";
        $where = "WHERE rezepte.Name LIKE '%$search%'";
        if ($kategorie != "*") {
            $kategorie = (int)$kategorie;
            $where .= " AND rezepte.Kategorie_ID = $kategorie";
        }

        $kitchenAppliances = (isset($_GET['kitchenAppliances'])) ? $_GET['kitchenAppliances'] : [];
        if (is_array($kitchenAppliances) && count($kitchenAppliances) > 0 && $kitchenAppliances[0] != "*") {
            $applianceConditions = [];
            foreach ($kitchenAppliances as $appliance) {
                $appliance = (int)$appliance;
                $applianceConditions[] = "JSON_CONTAINS(rezepte.KitchenAppliances, '[$appliance]')";
            }
            $where .= " AND (" . implode(" OR ", $applianceConditions) . ")";
        }

        $blacklistIngredients = (isset($_GET['blacklistIngredients']) && $_GET['blacklistIngredients'] != "") ? json_decode($_GET['blacklistIngredients'], true) : [];
        $whitelistIngredients = (isset($_GET['whitelistIngredients']) && $_GET['whitelistIngredients'] != "") ? json_decode($_GET['whitelistIngredients'], true) : [];
        $profileID = (isset($_GET['profileID']) && $_GET['profileID'] != "") ? $_GET['profileID'] : null;

        if ($profileID != null) {
            $sql = $pdo->prepare('SELECT Filter FROM filterprofile WHERE ID = :id');
            $sql->bindValue(':id', $profileID);
            $sql->execute();
            $filter = $sql->fetch(PDO::FETCH_ASSOC);
            $filter = json_decode(isset($filter['Filter']) && $filter['Filter'] != "" ? $filter['Filter'] : '{"likes":[],"dislikes":[]}', true);
            $blacklistIngredients = array_merge($blacklistIngredients, $filter['dislikes'] ?? []);
        }

        if (is_array($blacklistIngredients) && count($blacklistIngredients) > 0) {
            $ingredientConditions = [];
            foreach ($blacklistIngredients as $ingredient) {
                $ingredient = (int)$ingredient;
                $ingredientConditions[] = "NOT JSON_CONTAINS(rezepte.Zutaten_JSON, '[{\"ID\":\"$ingredient\"}]')";
            }
            $where .= " AND (" . implode(" AND ", $ingredientConditions) . ")";
        }

        if (is_array($whitelistIngredients) && count($whitelistIngredients) > 0) {
            $ingredientConditions = [];
            foreach ($whitelistIngredients as $ingredient) {
                $ingredient = (int)$ingredient;
                $ingredientConditions[] = "JSON_CONTAINS(rezepte.Zutaten_JSON, '[{\"ID\":\"$ingredient\"}]')";
            }
            $where .= " AND (" . implode(" OR ", $ingredientConditions) . ")";
        }

        switch ($zeit) {
            case "0": $where .= " AND rezepte.Zeit <= 15"; break;
            case "1": $where .= " AND rezepte.Zeit > 15 AND rezepte.Zeit <= 30"; break;
            case "2": $where .= " AND rezepte.Zeit > 30 AND rezepte.Zeit <= 60"; break;
            case "3": $where .= " AND rezepte.Zeit > 60"; break;
        }

        $allowedOrder = ['Name', 'Zeit', 'Rating', 'ID'];
        if (!in_array($order, $allowedOrder)) { $order = 'Name'; }
        $orderSql = "ORDER BY rezepte.$order";

        $join .= " LEFT JOIN bewertungen ON rezepte.ID = bewertungen.Rezept_ID";
        if ($orderSql == "ORDER BY rezepte.Rating") {
            $orderSql = "ORDER BY AVG(bewertungen.Bewertung) DESC";
        }
        if ($random) { $orderSql = "ORDER BY RAND() LIMIT 8"; }
        if ($neueste) { $orderSql = "ORDER BY rezepte.ID DESC LIMIT 8"; }
        if ($last_visit) { $orderSql = "ORDER BY rezepte.last_visit DESC LIMIT 8"; }

        $rezepte = $pdo->query("
            SELECT rezepte.ID as rezepte_ID, rezepte.Name as Name, MIN(bilder.Image) as Image,
                   rezepte.Zeit, AVG(bewertungen.Bewertung) as Durchschnittsbewertung
            FROM rezepte
            $join
            $where
            GROUP BY rezepte.ID
            $orderSql
        ")->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($rezepte as $rezept) {
            $image = (!file_exists("uploads/" . $rezept['Image']) || $rezept['Image'] == null) ? "ingredientIcons/default.svg" : "uploads/" . $rezept['Image'];
            $zeit = $rezept['Zeit'];
            $stunden = floor($zeit / 60);
            $minuten = $zeit % 60;
            $zeitString = ($stunden > 0 ? $stunden . "h " : "") . ($minuten > 0 ? $minuten . "min" : "");

            $rating = $pdo->query("SELECT AVG(Bewertung) as Rating, COUNT(Bewertung) as Anzahl FROM bewertungen WHERE Rezept_ID = " . (int)$rezept['rezepte_ID'])->fetch();
            $count = $rating['Anzahl'];
            $rating = $rating['Rating'] ?? 0;

            $response[] = [
                'rezepte_ID' => $rezept['rezepte_ID'],
                'Name' => $rezept['Name'],
                'Image' => $image,
                'Zeit' => $zeitString,
                'Rating' => (float)$rating,
                'RatingCount' => (int)$count
            ];
        }

        echo json_encode($response);
        die();
    case "getKategorien":
        if (isset($_GET['includeCount']) && $_GET['includeCount'] == 'true') {
            $kategorien = $pdo->query("SELECT k.ID, k.Name, k.ColorHex, COUNT(rk.Kategorie_ID) AS usage_count FROM kategorien k LEFT JOIN rezepte rk ON k.ID = rk.Kategorie_ID GROUP BY k.ID ORDER BY k.Name")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $kategorien = $pdo->query("SELECT ID, Name, ColorHex FROM kategorien ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($kategorien);
        die();
    case "getFilterprofile":
        $filterprofile = $pdo->query("SELECT * FROM filterprofile")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filterprofile as $key => $filter) {
            $filterprofile[$key]['Filter'] = json_decode($filter['Filter'], true);
        }
        foreach ($filterprofile as &$filter) {
            $filter['Image'] = 'https://api.dicebear.com/9.x/bottts-neutral/svg?seed=' . urlencode($filter['Name']);
        }
        echo json_encode($filterprofile);
        die();
    case "getAnmerkungen":
        if (isset($_GET['rezept'])) {
            $sql = $pdo->prepare("SELECT * FROM anmerkungen WHERE Rezept_ID = :id");
            $sql->bindValue(':id', $_GET['rezept']);
            $sql->execute();
            echo json_encode($sql->fetchAll(PDO::FETCH_ASSOC));
            die();
        } else {
            echo json_encode(['error' => 'No rezept provided']);
            die();
        }
    case "addZutat":
        if (isset($_GET['name']) && isset($_GET['unit'])) {
            $name = $_GET['name'];
            $image = strtolower($name) . '.svg';
            $unit = $_GET['unit'];

            $sql = $pdo->prepare('INSERT INTO zutaten (Name, Image, unit) VALUES (:name, :image, :unit)');
            $sql->bindValue(':name', $name);
            $sql->bindValue(':image', $image);
            $sql->bindValue(':unit', $unit);
            $sql->execute();

            echo json_encode(['success' => true, 'ID' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
        }
        die();
    case "editZutat":
        // NEU: Zutat bearbeiten (Name, Einheit, optional SVG-Icon). POST.
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['name'], $_POST['unit'])) {
            $imageName = strtolower($_POST['name']) . '.svg';

            $sql = $pdo->prepare('UPDATE zutaten SET Name = :name, unit = :unit, Image = :image WHERE ID = :id');
            $sql->bindValue(':name', $_POST['name']);
            $sql->bindValue(':unit', $_POST['unit']);
            $sql->bindValue(':image', $imageName);
            $sql->bindValue(':id', $_POST['id']);
            $sql->execute();

            if (isset($_FILES['icon']) && $_FILES['icon']['error'] === 0) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['icon']['tmp_name']);
                if ($mime === 'image/svg+xml' || strtolower(pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION)) === 'svg') {
                    if (!is_dir('ingredientIcons')) { mkdir('ingredientIcons', 0777, true); }
                    move_uploaded_file($_FILES['icon']['tmp_name'], "ingredientIcons/" . $imageName);
                }
            }

            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
            die();
        }
    case "deleteZutat":
        // NEU: Zutat loeschen, nur wenn in keinem Rezept verwendet.
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $count = $pdo->query("SELECT COUNT(*) FROM rezepte WHERE JSON_CONTAINS(Zutaten_JSON, JSON_OBJECT('ID', $id))")->fetchColumn();
            if ($count > 0) {
                echo json_encode(['error' => 'Zutat wird in ' . $count . ' Rezepten verwendet', 'success' => false]);
                die();
            }

            $image = $pdo->query("SELECT Image FROM zutaten WHERE ID = $id")->fetchColumn();
            if ($image && file_exists("ingredientIcons/" . $image)) {
                unlink("ingredientIcons/" . $image);
            }

            $sql = $pdo->prepare('DELETE FROM zutaten WHERE ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();

            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'No id provided', 'success' => false]);
            die();
        }
    case "anmerkung":
        if (isset($_GET['rezept']) && isset($_GET['text'])) {
            $rezept = $_GET['rezept'];
            $text = $_GET['text'];

            $sql = $pdo->prepare('SELECT * FROM anmerkungen WHERE Rezept_ID = :rezept');
            $sql->bindValue(':rezept', $rezept);
            $sql->execute();
            $anmerkung = $sql->fetch();

            if ($anmerkung) {
                $sql = $pdo->prepare('UPDATE anmerkungen SET Anmerkung = :text WHERE Rezept_ID = :rezept');
            } else {
                $sql = $pdo->prepare('INSERT INTO anmerkungen (Rezept_ID, Anmerkung) VALUES (:rezept, :text)');
            }
            $sql->bindValue(':rezept', $rezept);
            $sql->bindValue(':text', $text);
            $sql->execute();

            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
            die();
        }
    case "getKalender":
        if (isset($_GET['showPast']) && $_GET['showPast'] == 'true') {
            $showPast = '';
        } else {
            $showPast = 'WHERE kalender.Datum >= CURDATE()';
        }

        $kalender = $pdo->query("
            SELECT kalender.ID as Kalender_ID, kalender.Datum, kalender.Rezept_ID, kalender.Text,
                   rezepte.ID, rezepte.Name, MIN(bilder.Image) as Image
            FROM kalender
            LEFT JOIN rezepte ON kalender.Rezept_ID = rezepte.ID
            LEFT JOIN bilder ON rezepte.ID = bilder.Rezept_ID
            $showPast
            GROUP BY kalender.ID, kalender.Datum, kalender.Rezept_ID, kalender.Text, rezepte.ID, rezepte.Name
            ORDER BY Datum ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($kalender as &$kal) {
            if (!file_exists('uploads/' . $kal['Image']) || $kal['Image'] == null) {
                $kal['Image'] = ($kal['Image'] == null) ? null : 'ingredientIcons/default.svg';
            } else {
                $kal['Image'] = 'uploads/' . $kal['Image'];
            }
        }
        unset($kal);

        echo json_encode($kalender);
        die();
    case "addKalender":
        if (isset($_GET['date']) && isset($_GET['info'])) {
            $rezept = isset($_GET['rezept']) && $_GET['rezept'] !== '' ? $_GET['rezept'] : null;
            $stmt = $pdo->prepare("INSERT INTO kalender (Datum, Rezept_ID, Text) VALUES (?, ?, ?)");
            $stmt->execute([$_GET['date'], $rezept, $_GET['info']]);
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
            die();
        }
    case "deleteKalender":
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("DELETE FROM kalender WHERE ID = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
            die();
        }
    case "updateKalender":
        if (isset($_GET['id']) && (isset($_GET['text']) || isset($_GET['date']))) {
            $fields = [];
            $params = [];
            if (isset($_GET['text'])) { $fields[] = 'Text = ?'; $params[] = $_GET['text']; }
            if (isset($_GET['date'])) { $fields[] = 'Datum = ?'; $params[] = $_GET['date']; }
            $params[] = $_GET['id'];
            $stmt = $pdo->prepare("UPDATE kalender SET " . implode(', ', $fields) . " WHERE ID = ?");
            $stmt->execute($params);
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
            die();
        }
    case "getEinkaufsliste":
        $einkaufsliste = $pdo->query("
            SELECT einkaufsliste.ID as Einkaufsliste_ID, einkaufsliste.Zutat_ID, einkaufsliste.Menge,
                   einkaufsliste.Einheit, zutaten.ID, zutaten.Name, zutaten.Image
            FROM einkaufsliste
            LEFT JOIN zutaten ON einkaufsliste.Zutat_ID = zutaten.ID
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($einkaufsliste as &$item) {
            $item['Image'] = 'ingredientIcons/' . $item['Image'];
            if (!file_exists($item['Image'])) {
                $item['Image'] = 'ingredientIcons/default.svg';
            }
        }
        unset($item);

        echo json_encode($einkaufsliste);
        die();
    case "addEinkaufsliste":
        if (isset($_POST['zutat'], $_POST['menge'], $_POST['einheit'])) {
            $zutat = $_POST['zutat'];
            $menge = $_POST['menge'];
            $einheit = $_POST['einheit'];

            $stmt = $pdo->prepare("SELECT * FROM einkaufsliste WHERE Zutat_ID = ?");
            $stmt->execute([$zutat]);
            $item = $stmt->fetch();

            if ($item) {
                $menge += $item['Menge'];
                $stmt = $pdo->prepare("UPDATE einkaufsliste SET Menge = ? WHERE Zutat_ID = ?");
                $stmt->execute([$menge, $zutat]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO einkaufsliste (Zutat_ID, Menge, Einheit) VALUES (?, ?, ?)");
                $stmt->execute([$zutat, $menge, $einheit]);
            }

            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
            die();
        }
    case "deleteEinkaufsliste":
        if (isset($_POST['id'])) {
            $stmt = $pdo->prepare("DELETE FROM einkaufsliste WHERE ID = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
            die();
        }
    case "addRezept":
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (!isset($_POST['name'], $_POST['kategorie'], $_POST['dauer'], $_POST['portionen'], $_POST['anleitung'], $_POST['zutaten'], $_POST['extraCustomInfos'], $_POST['kitchenAppliances'])) {
                echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
                die();
            }

            $name = $_POST['name'];
            $kategorie = $_POST['kategorie'];
            $dauer = $_POST['dauer'];
            $portionen = $_POST['portionen'];
            $anleitung = $_POST['anleitung'];
            $zutaten = json_decode($_POST['zutaten']);
            $optionalInfos = json_decode($_POST['extraCustomInfos']);
            $kitchenAppliances = json_decode($_POST['kitchenAppliances']);
            $files = isset($_FILES['bilder']) ? $_FILES['bilder'] : null;

            $isEdit = isset($_GET['edit']) && isset($_GET['rezept']);

            if ($isEdit) {
                $rezeptID = $_GET['rezept'];
                $sql = "UPDATE rezepte SET Name = :name, Kategorie_ID = :kategorie, Zeit = :dauer, Portionen = :portionen, Zubereitung = :anleitung, Zutaten_JSON = :zutaten, OptionalInfos = :optionalInfos, KitchenAppliances = :kitchenAppliances WHERE ID = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $name, 'kategorie' => $kategorie, 'dauer' => $dauer, 'portionen' => $portionen,
                    'anleitung' => $anleitung, 'zutaten' => json_encode($zutaten),
                    'optionalInfos' => json_encode($optionalInfos),
                    'kitchenAppliances' => json_encode(array_map('intval', (array)$kitchenAppliances)),
                    'id' => $rezeptID
                ]);
            } else {
                $sql = "INSERT INTO rezepte (Name, Kategorie_ID, Zeit, Portionen, Zubereitung, Zutaten_JSON, OptionalInfos, KitchenAppliances) VALUES (:name, :kategorie, :dauer, :portionen, :anleitung, :zutaten, :optionalInfos, :kitchenAppliances)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $name, 'kategorie' => $kategorie, 'dauer' => $dauer, 'portionen' => $portionen,
                    'anleitung' => $anleitung, 'zutaten' => json_encode($zutaten),
                    'optionalInfos' => json_encode($optionalInfos),
                    'kitchenAppliances' => json_encode(array_map('intval', (array)$kitchenAppliances)),
                ]);
                $rezeptID = $pdo->lastInsertId();
            }

            // Bilder als WebP konvertieren und speichern
            if ($files && isset($files['name']) && is_array($files['name'])) {
                foreach ($files['name'] as $key => $fileName) {
                    $fileTmpName = $files['tmp_name'][$key];
                    $fileError = $files['error'][$key];
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $fileMime = mime_content_type($fileTmpName) ?: '';
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                    if ((in_array($fileExt, $allowed) || in_array($fileMime, $allowedMimes)) && $fileError === 0) {
                        $img = imagecreatefromstring(file_get_contents($fileTmpName));
                        if ($img === false) { continue; }
                        imagepalettetotruecolor($img);
                        imagealphablending($img, true);
                        imagesavealpha($img, true);

                        $width = imagesx($img);
                        $height = imagesy($img);
                        $maxWidth = 1080;
                        $maxHeight = 566;
                        $aspectRatio = $width / $height;

                        if ($width > $maxWidth || $height > $maxHeight) {
                            if ($aspectRatio > ($maxWidth / $maxHeight)) {
                                $newWidth = $maxWidth;
                                $newHeight = (int)($maxWidth / $aspectRatio);
                            } else {
                                $newHeight = $maxHeight;
                                $newWidth = (int)($maxHeight * $aspectRatio);
                            }
                            $newImg = imagecreatetruecolor($newWidth, $newHeight);
                            imagealphablending($newImg, false);
                            imagesavealpha($newImg, true);
                            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                            imagedestroy($img);
                            $img = $newImg;
                        }

                        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
                        // Schreibrechte sicherstellen (z. B. nach manuellem mkdir)
                        if (is_dir('uploads') && !is_writable('uploads')) {
                            @chmod('uploads', 0775);
                        }
                        $fileNameNew = uniqid('', true) . ".webp";
                        $written = @imagewebp($img, 'uploads/' . $fileNameNew, 45);
                        imagedestroy($img);

                        // Nur DB-Eintrag anlegen wenn Datei wirklich geschrieben wurde
                        if ($written && file_exists('uploads/' . $fileNameNew)) {
                            $stmt = $pdo->prepare("INSERT INTO bilder (Rezept_ID, Image) VALUES (:rezeptID, :image)");
                            $stmt->execute(['rezeptID' => $rezeptID, 'image' => $fileNameNew]);
                        } else {
                            $imageError = 'Bild konnte nicht gespeichert werden. Bitte Schreibrechte auf dem Server prüfen: chmod 775 uploads && chown www-data:www-data uploads';
                        }
                    }
                }
            }

            // App (Parameter app=1 oder Accept: application/json) bekommt JSON,
            // die alte Website bekommt wie gewohnt eine Weiterleitung zum Rezept.
            $wantsJson = isset($_GET['app'])
                || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
            if ($wantsJson) {
                $resp = ['success' => true, 'ID' => (int)$rezeptID];
                if (!empty($imageError)) { $resp['imageWarning'] = $imageError; }
                echo json_encode($resp);
            } else {
                header('Location: rezept.php?id=' . $rezeptID);
            }
            die();
        } else {
            echo json_encode(['error' => 'POST required', 'success' => false]);
            die();
        }
    case "addKategorie":
        if (isset($_GET['name']) && isset($_GET['color'])) {
            $sql = $pdo->prepare('INSERT INTO kategorien (Name, ColorHex) VALUES (:name, :color)');
            $sql->bindValue(':name', $_GET['name']);
            $sql->bindValue(':color', $_GET['color']);
            $sql->execute();
            echo json_encode(['success' => true, 'ID' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
        }
        die();
    case "editKategorie":
        if (isset($_GET['id'], $_GET['name'], $_GET['color'])) {
            $sql = $pdo->prepare('UPDATE kategorien SET Name = :name, ColorHex = :color WHERE ID = :id');
            $sql->bindValue(':id', $_GET['id']);
            $sql->bindValue(':name', $_GET['name']);
            $sql->bindValue(':color', $_GET['color']);
            $sql->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
        }
        die();
    case "deleteKategorie":
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $count = $pdo->query("SELECT COUNT(*) FROM rezepte WHERE Kategorie_ID = $id")->fetchColumn();
            if ($count > 0) {
                echo json_encode(['error' => 'Kategorie wird in ' . $count . ' Rezepten verwendet', 'success' => false]);
                die();
            }
            $sql = $pdo->prepare('DELETE FROM kategorien WHERE ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided']);
            die();
        }
    case "addFilterprofile":
        if (isset($_GET['name'])) {
            $sql = $pdo->prepare('INSERT INTO filterprofile (Name, Filter) VALUES (:name, :filter)');
            $sql->bindValue(':name', $_GET['name']);
            $sql->bindValue(':filter', '{"likes":[],"dislikes":[]}');
            $sql->execute();
            echo json_encode(['success' => true, 'ID' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
        }
        die();
    case "getKitchenAppliances":
        $kitchenAppliances = $pdo->query("
            SELECT ka.*, COUNT(r.ID) AS recipe_count
            FROM kitchenAppliances ka
            LEFT JOIN rezepte r ON JSON_CONTAINS(r.KitchenAppliances, CONCAT('[', ka.ID, ']'))
            GROUP BY ka.ID
            ORDER BY ka.Name
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($kitchenAppliances as &$appliance) {
            $appliance['Image'] = 'uploads/' . $appliance['Image'];
        }
        unset($appliance);

        echo json_encode($kitchenAppliances);
        die();
    case "addKitchenAppliance":
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['Name']) && isset($_FILES['Image'])) {
            $name = $_POST['Name'];
            $image = $_FILES['Image'];
            $fileExt = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
            $fileNameNew = null;

            if (in_array($fileExt, $allowed) && $image['error'] === 0) {
                if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
                if ($fileExt === 'svg') {
                    $fileNameNew = uniqid('', true) . ".svg";
                    move_uploaded_file($image['tmp_name'], 'uploads/' . $fileNameNew);
                } else {
                    $img = imagecreatefromstring(file_get_contents($image['tmp_name']));
                    imagepalettetotruecolor($img);
                    imagealphablending($img, true);
                    imagesavealpha($img, true);
                    $width = imagesx($img); $height = imagesy($img);
                    $maxWidth = 1080; $maxHeight = 566;
                    $aspectRatio = $width / $height;
                    if ($width > $maxWidth || $height > $maxHeight) {
                        if ($aspectRatio > ($maxWidth / $maxHeight)) { $newWidth = $maxWidth; $newHeight = (int)($maxWidth / $aspectRatio); }
                        else { $newHeight = $maxHeight; $newWidth = (int)($maxHeight * $aspectRatio); }
                        $newImg = imagecreatetruecolor($newWidth, $newHeight);
                        imagealphablending($newImg, false); imagesavealpha($newImg, true);
                        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                        imagedestroy($img); $img = $newImg;
                    }
                    $fileNameNew = uniqid('', true) . ".webp";
                    $written = @imagewebp($img, 'uploads/' . $fileNameNew, 45);
                    imagedestroy($img);
                    if (!$written) {
                        echo json_encode(['error' => 'Bild konnte nicht gespeichert werden (Schreibrechte prüfen)', 'success' => false]);
                        die();
                    }
                }

                $sql = $pdo->prepare('INSERT INTO kitchenAppliances (Name, Image) VALUES (:name, :image)');
                $sql->bindValue(':name', $name);
                $sql->bindValue(':image', $fileNameNew);
                $sql->execute();
                echo json_encode(['success' => true, 'ID' => $pdo->lastInsertId()]);
            } else {
                echo json_encode(['error' => 'Ungültiges Bild', 'success' => false]);
            }
            die();
        }
        echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
        die();
    case "updateKitchenAppliance":
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['Name'], $_POST['id'])) {
            $name = $_POST['Name'];
            $id = $_POST['id'];
            $image = isset($_FILES['Image']) && $_FILES['Image']['error'] === 0 ? $_FILES['Image'] : null;

            if ($image) {
                $fileExt = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
                if (in_array($fileExt, $allowed)) {
                    if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
                    if ($fileExt === 'svg') {
                        $fileNameNew = uniqid('', true) . ".svg";
                        move_uploaded_file($image['tmp_name'], 'uploads/' . $fileNameNew);
                    } else {
                        $img = imagecreatefromstring(file_get_contents($image['tmp_name']));
                        imagepalettetotruecolor($img);
                        imagealphablending($img, true);
                        imagesavealpha($img, true);
                        $width = imagesx($img); $height = imagesy($img);
                        $maxWidth = 1080; $maxHeight = 566;
                        $aspectRatio = $width / $height;
                        if ($width > $maxWidth || $height > $maxHeight) {
                            if ($aspectRatio > ($maxWidth / $maxHeight)) { $newWidth = $maxWidth; $newHeight = (int)($maxWidth / $aspectRatio); }
                            else { $newHeight = $maxHeight; $newWidth = (int)($maxHeight * $aspectRatio); }
                            $newImg = imagecreatetruecolor($newWidth, $newHeight);
                            imagealphablending($newImg, false); imagesavealpha($newImg, true);
                            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                            imagedestroy($img); $img = $newImg;
                        }
                        $fileNameNew = uniqid('', true) . ".webp";
                        $written = @imagewebp($img, 'uploads/' . $fileNameNew, 45);
                        imagedestroy($img);
                        if (!$written) {
                            echo json_encode(['error' => 'Bild konnte nicht gespeichert werden (Schreibrechte prüfen)', 'success' => false]);
                            die();
                        }
                    }
                    $sql = $pdo->prepare('UPDATE kitchenAppliances SET Name = :name, Image = :image WHERE ID = :id');
                    $sql->bindValue(':image', $fileNameNew);
                } else {
                    $sql = $pdo->prepare('UPDATE kitchenAppliances SET Name = :name WHERE ID = :id');
                }
            } else {
                $sql = $pdo->prepare('UPDATE kitchenAppliances SET Name = :name WHERE ID = :id');
            }
            $sql->bindValue(':name', $name);
            $sql->bindValue(':id', $id);
            $sql->execute();
            echo json_encode(['success' => true]);
            die();
        }
        echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
        die();
    case "deleteKitchenAppliance":
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $image = $pdo->query("SELECT Image FROM kitchenAppliances WHERE ID = $id")->fetchColumn();
            if ($image && file_exists('uploads/' . $image)) {
                unlink('uploads/' . $image);
            }
            $sql = $pdo->prepare('DELETE FROM kitchenAppliances WHERE ID = :id');
            $sql->bindValue(':id', $id);
            $sql->execute();
            echo json_encode(['success' => true]);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided']);
            die();
        }
    case "bringApiAddMyRecipeIngredients":
        if (isset($_GET['recipe_id'])) {
            $recipe_id = $_GET['recipe_id'];
            $startTime = microtime(true);

            $stmt = $pdo->prepare("SELECT Zutaten_JSON FROM rezepte WHERE ID = ?");
            $stmt->execute([$recipe_id]);
            $recipe = $stmt->fetch();
            $ingredients = json_decode($recipe['Zutaten_JSON'], true);

            $zutaten_array = [];
            foreach ($ingredients as $ingredient) {
                $stmt = $pdo->prepare("SELECT * FROM zutaten WHERE ID = ?");
                $stmt->execute([$ingredient['ID']]);
                $zutat = $stmt->fetch();
                if (!$zutat) { continue; }
                $zutaten_array[] = [
                    'ID' => $zutat['ID'],
                    'Name' => trim($zutat['Name']),
                    'additionalInfo' => $ingredient['additionalInfo'] ?? '',
                    'Menge' => $ingredient['Menge'],
                    'Einheit' => $zutat['unit']
                ];
            }
            $ingredients = $zutaten_array;

            include_once 'shared/BringApi.php';
            $data = parse_ini_file('config.ini');

            if (empty($data['bring_email']) || empty($data['bring_password'])) {
                echo json_encode(['error' => 'Email oder Passwort nicht in config vorhanden', 'success' => false]);
                die();
            }

            try {
                $bringApi = new BringApi($data['bring_email'], $data['bring_password'], true);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage(), 'success' => false]);
                die();
            }

            $items = $bringApi->getItems()['purchase'];
            foreach ($ingredients as $ingredient) {
                if (in_array($ingredient['Name'], array_column($items, 'name'))) {
                    $item = $items[array_search($ingredient['Name'], array_column($items, 'name'))];
                    $bringApi->purchaseItem($ingredient['Name'], $item['specification'] . ' %2B ' . $ingredient['Menge'] . ' ' . $ingredient['Einheit'] . ' ' . $ingredient['additionalInfo']);
                } else {
                    $bringApi->purchaseItem($ingredient['Name'], $ingredient['Menge'] . ' ' . $ingredient['Einheit'] . ' ' . $ingredient['additionalInfo']);
                }
            }

            echo json_encode(['success' => true, 'message' => count($ingredients) . ' Zutaten hinzugefügt! (' . round(microtime(true) - $startTime, 2) . 's)']);
            die();
        } else {
            echo json_encode(['error' => 'Not all parameters provided', 'success' => false]);
            die();
        }
    case 'geminiGenerateImage':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST required', 'success' => false]);
            die();
        }

        $imgIni   = parse_ini_file('config.ini');
        $imgToken = $imgIni['gemini_token'] ?? '';
        if (!$imgToken) {
            echo json_encode(['error' => 'Kein Gemini-Token', 'success' => false]);
            die();
        }

        $imgName = trim($_POST['recipe_name'] ?? '');
        $imgDesc = trim($_POST['recipe_description'] ?? '');
        $imgMode = $_POST['mode'] ?? 'text'; // 'text' | 'image'

        if (!$imgName) {
            echo json_encode(['error' => 'Kein Rezeptname angegeben', 'success' => false]);
            die();
        }

        // Konsistenter Kochbuch-Fotografie-Stil fuer alle generierten Bilder
        $styleGuide =
            "Professional cookbook food photography style consistent across all images. " .
            "Three-quarter overhead angle at exactly 45 degrees. " .
            "Soft diffused natural window light entering from the upper-left, creating gentle shadows. " .
            "Warm, golden-hour color temperature (approx 5500K). " .
            "Matte ceramic or earthenware plate on a dark slate or aged oak wooden surface. " .
            "Shallow depth of field, soft bokeh in the background. " .
            "A small scattering of key ingredients and fresh herbs as props around the plate. " .
            "Rich, saturated, appetizing colors. Clean uncluttered composition. " .
            "Shot as if with a 85mm prime lens. Magazine-quality, Michelin-star restaurant aesthetic.";

        $parts = [];

        if ($imgMode === 'image' && isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === 0) {
            // Bild-zu-Bild: Referenzbild verbessern und inszenieren
            $refFile = $_FILES['reference_image'];
            if ($refFile['size'] > 10 * 1024 * 1024) {
                echo json_encode(['error' => 'Referenzbild zu gross (max. 10 MB)', 'success' => false]);
                die();
            }
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $refMime = finfo_file($finfo, $refFile['tmp_name']);
            finfo_close($finfo);

            $parts[] = ['inlineData' => [
                'mimeType' => $refMime,
                'data'     => base64_encode(file_get_contents($refFile['tmp_name']))
            ]];

            $imgPrompt =
                "Restage and enhance this dish for a high-end cookbook. " .
                "The dish is '{$imgName}'" . ($imgDesc ? " ({$imgDesc})" : '') . ". " .
                "Keep the exact same food and dish but completely transform the presentation, plating, " .
                "lighting, surface, and background to match this style. " .
                $styleGuide;
        } else {
            // Text-zu-Bild: vollstaendig aus Rezeptdaten generieren
            $descFragment = $imgDesc ? " The dish contains: " . substr($imgDesc, 0, 400) . "." : '';

            $imgPrompt =
                "Generate a professional cookbook photo of the dish '{$imgName}'.{$descFragment} " .
                "The dish is beautifully plated, perfectly portioned and garnished. " .
                $styleGuide;
        }

        $parts[] = ['text' => $imgPrompt];

        $imgPayload = [
            'contents'         => [['parts' => $parts]],
            'generationConfig' => [
                'response_modalities' => ['IMAGE']
            ]
        ];

        $chImg  = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent');
        curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chImg, CURLOPT_POST, true);
        curl_setopt($chImg, CURLOPT_POSTFIELDS, json_encode($imgPayload));
        curl_setopt($chImg, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $imgToken
        ]);
        curl_setopt($chImg, CURLOPT_TIMEOUT, 120);

        $imgRaw  = curl_exec($chImg);
        $imgCode = curl_getinfo($chImg, CURLINFO_HTTP_CODE);
        $imgErr  = curl_error($chImg);
        curl_close($chImg);

        if ($imgRaw === false || $imgErr) {
            echo json_encode(['error' => 'Gemini nicht erreichbar: ' . $imgErr, 'success' => false]);
            die();
        }

        $imgResp = json_decode($imgRaw, true);
        if ($imgCode !== 200) {
            $imgErrMsg = $imgResp['error']['message'] ?? 'HTTP ' . $imgCode;
            echo json_encode(['error' => 'Gemini Fehler: ' . $imgErrMsg, 'success' => false]);
            die();
        }

        // Bild-Part aus der Antwort extrahieren (Thought-Parts ueberspringen)
        $imgBase64 = null;
        $imgMimeOut = 'image/png';
        $debugParts = [];
        foreach ($imgResp['candidates'] ?? [] as $cand) {
            foreach ($cand['content']['parts'] ?? [] as $part) {
                $debugParts[] = array_keys($part); // fuer Debugging merken
                if (!empty($part['thought'])) continue;
                if (isset($part['inlineData'])) {
                    $imgBase64  = $part['inlineData']['data'];
                    $imgMimeOut = $part['inlineData']['mimeType'] ?? 'image/png';
                    break 2;
                }
            }
        }

        if (!$imgBase64) {
            $fullText = '';
            foreach ($imgResp['candidates'] ?? [] as $cand) {
                $finishReason = $cand['finishReason'] ?? 'unknown';
                foreach ($cand['content']['parts'] ?? [] as $part) {
                    if (!empty($part['text'])) $fullText .= $part['text'];
                }
            }
            // Debug: Rohstruktur ausgeben damit der Fehler klar wird
            echo json_encode([
                'error'        => 'Kein Bild generiert.' . ($fullText ? ' Modell-Antwort: ' . substr($fullText, 0, 300) : ''),
                'success'      => false,
                'debug_keys'   => $debugParts,
                'debug_finish' => $finishReason ?? null,
                'debug_cands'  => count($imgResp['candidates'] ?? [])
            ]);
            die();
        }

        echo json_encode(['success' => true, 'image_data' => $imgBase64, 'mime_type' => $imgMimeOut]);
        die();

    case 'geminiChat':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST required', 'success' => false]);
            die();
        }

        $iniData2 = parse_ini_file('config.ini');
        $geminiToken2 = $iniData2['gemini_token'] ?? '';
        if (!$geminiToken2) {
            echo json_encode(['error' => 'Kein Gemini-Token in config.ini', 'success' => false]);
            die();
        }

        $chatBody   = json_decode(file_get_contents('php://input'), true);
        $chatMsg    = trim($chatBody['message'] ?? '');
        $chatHist   = $chatBody['history']   ?? [];
        $chatRid    = $chatBody['recipe_id'] ?? null;

        if (!$chatMsg) {
            echo json_encode(['error' => 'Keine Nachricht', 'success' => false]);
            die();
        }

        // Alle Rezepte (kompakt)
        $allRezepte = $pdo->query("
            SELECT r.ID, r.Name, k.Name AS Kategorie, r.Zeit
            FROM rezepte r
            LEFT JOIN kategorien k ON r.Kategorie_ID = k.ID
            ORDER BY r.last_visit DESC, r.Name
            LIMIT 200
        ")->fetchAll(PDO::FETCH_ASSOC);

        $rezepteLines = array_map(function($r) {
            return "ID={$r['ID']}: {$r['Name']} (Kategorie: {$r['Kategorie']}, Zeit: {$r['Zeit']}min)";
        }, $allRezepte);
        $rezepteText = implode("\n", $rezepteLines);

        // Kontext fuer aktuell geoeffnetes Rezept
        $currentRecipeCtx = '';
        if ($chatRid) {
            $rStmt = $pdo->prepare("SELECT r.*, k.Name AS KatName FROM rezepte r LEFT JOIN kategorien k ON r.Kategorie_ID = k.ID WHERE r.ID = ?");
            $rStmt->execute([(int)$chatRid]);
            $rRow = $rStmt->fetch(PDO::FETCH_ASSOC);
            if ($rRow) {
                $zArr = json_decode($rRow['Zutaten_JSON'], true) ?? [];
                $zNames = [];
                foreach ($zArr as $z) {
                    $zStmt = $pdo->prepare("SELECT Name, unit FROM zutaten WHERE ID = ?");
                    $zStmt->execute([$z['ID']]);
                    $zRow2 = $zStmt->fetch(PDO::FETCH_ASSOC);
                    if ($zRow2) {
                        $zNames[] = "{$z['Menge']} {$zRow2['unit']} {$zRow2['Name']}" .
                            (!empty($z['additionalInfo']) ? " ({$z['additionalInfo']})" : '');
                    }
                }
                $currentRecipeCtx = "\n\nAKTUELL GEOEFFNETES REZEPT:\n" .
                    "Name: {$rRow['Name']}\n" .
                    "Kategorie: {$rRow['KatName']}\n" .
                    "Zeit: {$rRow['Zeit']} Min, Portionen: {$rRow['Portionen']}\n" .
                    "Zutaten: " . implode(', ', $zNames) . "\n" .
                    "Zubereitung: " . substr(strip_tags($rRow['Zubereitung']), 0, 800);
            }
        }

        // Zutaten / Kategorien / Geraete fuer Rezeptentwuerfe
        $chatZutaten    = $pdo->query("SELECT ID, Name, unit FROM zutaten ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
        $chatKategorien = $pdo->query("SELECT ID, Name FROM kategorien ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
        $chatGeraete    = $pdo->query("SELECT ID, Name FROM kitchenAppliances ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);

        $chatZutatenLines = array_map(fn($z) => "ID={$z['ID']}: {$z['Name']} ({$z['unit']})", $chatZutaten);

        $systemPrompt2 =
            "Du bist ein freundlicher Kochassistent fuer eine Kochbuch-App.\n\n" .
            "REZEPTE IN DER APP:\n" . $rezepteText .
            $currentRecipeCtx . "\n\n" .
            "VERFUEGBARE ZUTATEN-DB (fuer Rezeptentwuerfe):\n" .
            implode("\n", array_slice($chatZutatenLines, 0, 150)) . "\n\n" .
            "VERFUEGBARE KATEGORIEN: " . json_encode($chatKategorien, JSON_UNESCAPED_UNICODE) . "\n" .
            "VERFUEGBARE KUECHENGERAETE: " . json_encode($chatGeraete, JSON_UNESCAPED_UNICODE) . "\n\n" .
            "REGELN:\n" .
            "- Antworte IMMER auf Deutsch.\n" .
            "- Formatiere Antworten als HTML: <p>, <strong>, <ul><li>, <ol><li>.\n" .
            "- recipe_links: Fuege IDs + Namen hinzu wenn du auf Rezepte aus der App hinweist.\n" .
            "- has_draft=true und recipe_draft befuellen: NUR wenn der Nutzer EXPLIZIT ein neues Rezept erstellen moechte oder fragt.\n" .
            "- Bei recipe_draft: Zutaten den IDs aus der Zutaten-DB zuordnen (ingredient_id=0 wenn unbekannt).\n" .
            "- Ansonsten has_draft=false und recipe_draft weglassen.\n" .
            "- Tipp-Funktion: Schlage aehnliche Rezepte vor, beantworte Kochfragen, gib Tipps.";

        // Antwort-Schema
        $chatSchema = [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'description' => 'Die Antwort als HTML. Verwende <p>, <strong>, <ul><li>, <ol><li>.'],
                'recipe_links' => [
                    'type' => 'array',
                    'description' => 'Maximal 5 relevante Rezepte aus der App. Leer wenn keine. Nur die Top-5 passendsten.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id'   => ['type' => 'integer'],
                            'name' => ['type' => 'string']
                        ],
                        'required' => ['id', 'name']
                    ]
                ],
                'has_draft' => ['type' => 'boolean', 'description' => 'true wenn ein Rezeptentwurf erstellt werden soll'],
                'recipe_draft' => [
                    'type' => 'object',
                    'description' => 'Nur befuellen wenn has_draft=true.',
                    'properties' => [
                        'recipe_name'           => ['type' => 'string'],
                        'category_id'           => ['type' => 'integer'],
                        'prep_time_minutes'     => ['type' => 'integer'],
                        'portions'              => ['type' => 'integer'],
                        'instructions'          => ['type' => 'string', 'description' => 'Als HTML mit <ol><li>'],
                        'kitchen_appliance_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'optional_infos'        => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title'   => ['type' => 'string'],
                                    'content' => ['type' => 'string']
                                ],
                                'required' => ['title', 'content']
                            ]
                        ],
                        'ingredient_tables' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'table_name'  => ['type' => 'string'],
                                    'ingredients' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'ingredient_id'   => ['type' => 'integer'],
                                                'ingredient_name' => ['type' => 'string'],
                                                'quantity'        => ['type' => 'number'],
                                                'unit'            => ['type' => 'string'],
                                                'additional_info' => ['type' => 'string']
                                            ],
                                            'required' => ['ingredient_id', 'ingredient_name', 'quantity', 'unit', 'additional_info']
                                        ]
                                    ]
                                ],
                                'required' => ['table_name', 'ingredients']
                            ]
                        ]
                    ],
                    'required' => ['recipe_name', 'category_id', 'prep_time_minutes', 'portions', 'instructions', 'kitchen_appliance_ids', 'optional_infos', 'ingredient_tables']
                ]
            ],
            'required' => ['message', 'recipe_links', 'has_draft']
        ];

        // Verlauf aufbauen
        $chatContents = [];
        foreach (array_slice($chatHist, -14) as $h) {
            $hRole = ($h['role'] === 'model') ? 'model' : 'user';
            $chatContents[] = ['role' => $hRole, 'parts' => [['text' => $h['content']]]];
        }
        $chatContents[] = ['role' => 'user', 'parts' => [['text' => $chatMsg]]];

        $chatPayload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt2]]],
            'contents'           => $chatContents,
            'generationConfig'   => [
                'response_mime_type' => 'application/json',
                'response_schema'    => $chatSchema
            ]
        ];

        $chC = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');
        curl_setopt($chC, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chC, CURLOPT_POST, true);
        curl_setopt($chC, CURLOPT_POSTFIELDS, json_encode($chatPayload, JSON_UNESCAPED_UNICODE));
        curl_setopt($chC, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'x-goog-api-key: ' . $geminiToken2
        ]);
        curl_setopt($chC, CURLOPT_TIMEOUT, 60);
        $chatRaw    = curl_exec($chC);
        $chatCode   = curl_getinfo($chC, CURLINFO_HTTP_CODE);
        $chatErr    = curl_error($chC);
        curl_close($chC);

        if ($chatRaw === false || $chatErr) {
            echo json_encode(['error' => 'Gemini nicht erreichbar: ' . $chatErr, 'success' => false]);
            die();
        }

        $chatGemini = json_decode($chatRaw, true);
        if ($chatCode !== 200) {
            $chatErrMsg = $chatGemini['error']['message'] ?? ('HTTP ' . $chatCode);
            echo json_encode(['error' => 'Gemini Fehler: ' . $chatErrMsg, 'success' => false]);
            die();
        }

        $chatText = $chatGemini['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $chatText = preg_replace('/^```json\s*/i', '', trim($chatText));
        $chatText = preg_replace('/```\s*$/', '', $chatText);
        $chatReply = json_decode(trim($chatText), true);

        if (!$chatReply) {
            echo json_encode(['error' => 'Ungueltige KI-Antwort', 'success' => false]);
            die();
        }

        // Sicherstellen dass recipe_links ein Array ist und max. 5 Einträge hat
        if (!isset($chatReply['recipe_links']) || !is_array($chatReply['recipe_links'])) {
            $chatReply['recipe_links'] = [];
        }
        $chatReply['recipe_links'] = array_slice($chatReply['recipe_links'], 0, 5);

        echo json_encode(['success' => true, 'reply' => $chatReply]);
        die();

    case 'geminiAnalyzeRecipe':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
            echo json_encode(['error' => 'POST mit Datei erforderlich', 'success' => false]);
            die();
        }

        $iniData = parse_ini_file('config.ini');
        $geminiToken = $iniData['gemini_token'] ?? '';

        if (!$geminiToken) {
            echo json_encode(['error' => 'Kein Gemini-Token in der config.ini', 'success' => false]);
            die();
        }

        $file = $_FILES['file'];
        if ($file['error'] !== 0) {
            echo json_encode(['error' => 'Fehler beim Datei-Upload (Code: ' . $file['error'] . ')', 'success' => false]);
            die();
        }

        $maxBytes = 18 * 1024 * 1024; // 18 MB Inline-Limit
        if ($file['size'] > $maxBytes) {
            echo json_encode(['error' => 'Datei zu groß (max. 18 MB)', 'success' => false]);
            die();
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        if (!in_array($mimeType, $allowedMimes)) {
            echo json_encode(['error' => 'Nicht unterstützter Dateityp: ' . $mimeType, 'success' => false]);
            die();
        }

        // Alle Zutaten laden
        $alleZutaten = $pdo->query("SELECT ID, Name, unit FROM zutaten ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);

        // Kategorien laden
        $alleKategorien = $pdo->query("SELECT ID, Name FROM kategorien ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);

        // Kuechengeraete laden
        $alleGeraete = $pdo->query("SELECT ID, Name FROM kitchenAppliances ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);

        // Datei als Base64
        $fileData = base64_encode(file_get_contents($file['tmp_name']));

        // Zutatenliste fuer den Prompt
        $zutatenLines = array_map(function($z) {
            return "ID={$z['ID']}: {$z['Name']} (Einheit: {$z['unit']})";
        }, $alleZutaten);
        $zutatenText = implode("\n", $zutatenLines);

        $kategorienJson  = json_encode($alleKategorien, JSON_UNESCAPED_UNICODE);
        $geraeteJson     = json_encode($alleGeraete,    JSON_UNESCAPED_UNICODE);

        // JSON-Schema fuer Structured Output
        $schema = [
            'type' => 'object',
            'properties' => [
                'recipe_name'  => ['type' => 'string', 'description' => 'Der Name des Rezepts auf Deutsch'],
                'category_id'  => ['type' => 'integer', 'description' => 'Die ID der am besten passenden Kategorie aus der Kategorienliste. PFLICHTFELD - muss immer gesetzt sein.'],
                'prep_time_minutes' => ['type' => 'integer', 'description' => 'Gesamte Zubereitungszeit in Minuten (inkl. Backzeit etc.)'],
                'portions'     => ['type' => 'integer', 'description' => 'Anzahl der Portionen'],
                'instructions' => ['type' => 'string', 'description' => 'Zubereitung als HTML. Verwende <ol><li>...</li></ol> fuer nummerierte Schritte. Nutze <strong> fuer Hinweise.'],
                'kitchen_appliance_ids' => [
                    'type'        => 'array',
                    'description' => 'IDs der Kuechengeraete aus der bereitgestellten Liste, die fuer dieses Rezept benoetigt werden. Leeres Array wenn keine benoetigt.',
                    'items'       => ['type' => 'integer']
                ],
                'optional_infos' => [
                    'type'        => 'array',
                    'description' => 'Optionale Zusatzinformationen wie Kalorien, Naehrwerte, Schwierigkeitsgrad etc., falls im Rezept vorhanden. Sonst leeres Array.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'title'   => ['type' => 'string', 'description' => 'Bezeichnung, z.B. "Kalorien"'],
                            'content' => ['type' => 'string', 'description' => 'Wert, z.B. "350 kcal pro Portion"']
                        ],
                        'required' => ['title', 'content']
                    ]
                ],
                'ingredient_tables' => [
                    'type'  => 'array',
                    'description' => 'Zutatentabellen. Mehrere Tabellen wenn das Rezept verschiedene Komponenten hat (z.B. Teig + Fuellung + Sosse). Sonst eine Tabelle mit leerem Namen.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'table_name'  => ['type' => 'string', 'description' => 'Tabellenname z.B. "Teig", "Sauce". Fuer die einzige/erste Tabelle leerer String.'],
                            'ingredients' => [
                                'type'  => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'ingredient_id'   => ['type' => 'integer', 'description' => 'ID aus der Zutatenliste. 0 wenn keine passende vorhanden.'],
                                        'ingredient_name' => ['type' => 'string', 'description' => 'Name aus der Zutatenliste oder Originalname.'],
                                        'quantity'        => ['type' => 'number', 'description' => 'Menge als Zahl (0 wenn keine Angabe)'],
                                        'unit'            => ['type' => 'string', 'description' => 'Einheit aus der Zutatenliste'],
                                        'additional_info' => ['type' => 'string', 'description' => 'Zusatzinfo wie "gewuerfelt", "gehackt" etc.']
                                    ],
                                    'required' => ['ingredient_id', 'ingredient_name', 'quantity', 'unit', 'additional_info']
                                ]
                            ]
                        ],
                        'required' => ['table_name', 'ingredients']
                    ]
                ]
            ],
            'required' => ['recipe_name', 'category_id', 'prep_time_minutes', 'portions', 'instructions', 'kitchen_appliance_ids', 'optional_infos', 'ingredient_tables']
        ];

        $prompt = "Bitte extrahiere das Rezept vollstaendig aus diesem Dokument/Bild.\n\n" .
                  "Verfuegbare Zutaten-Datenbank:\n" . $zutatenText . "\n\n" .
                  "Verfuegbare Kategorien: " . $kategorienJson . "\n\n" .
                  "Verfuegbare Kuechengeraete: " . $geraeteJson . "\n\n" .
                  "Regeln:\n" .
                  "- category_id MUSS gesetzt sein - waehle immer die am besten passende Kategorie-ID.\n" .
                  "- Ordne jede Zutat der passenden Datenbank-Zutat zu (ingredient_id); 0 wenn keine passt.\n" .
                  "- Trage benoetigt Kuechengeraete als kitchen_appliance_ids ein (leeres Array wenn keine benoetigt).\n" .
                  "- Trage Naehrwerte/Kalorien/Schwierigkeit o.ae. als optional_infos ein, falls vorhanden.\n" .
                  "- Trenne Zutaten in sinnvolle Tabellen (z.B. Teig / Belag / Sosse).\n" .
                  "- Zubereitung als HTML mit <ol><li> fuer Schritte.";

        $payload = [
            'contents' => [[
                'parts' => [
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $fileData]],
                    ['text' => $prompt]
                ]
            ]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'response_schema'    => $schema
            ]
        ];

        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $geminiToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $geminiRaw  = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($geminiRaw === false || $curlError) {
            echo json_encode(['error' => 'Gemini API nicht erreichbar: ' . $curlError, 'success' => false]);
            die();
        }

        $geminiResponse = json_decode($geminiRaw, true);

        if ($httpCode !== 200) {
            $errorMsg = $geminiResponse['error']['message'] ?? ('HTTP ' . $httpCode);
            echo json_encode(['error' => 'Gemini API Fehler: ' . $errorMsg, 'success' => false]);
            die();
        }

        $rawText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (!$rawText) {
            echo json_encode(['error' => 'Keine Antwort von Gemini erhalten', 'success' => false]);
            die();
        }

        // Markdown-Codeblock entfernen falls vorhanden
        $rawText = preg_replace('/^```json\s*/i', '', trim($rawText));
        $rawText = preg_replace('/```\s*$/', '', $rawText);

        $recipeData = json_decode(trim($rawText), true);

        if (!$recipeData) {
            echo json_encode(['error' => 'KI-Antwort konnte nicht als JSON verarbeitet werden', 'success' => false, 'raw' => substr($rawText, 0, 500)]);
            die();
        }

        echo json_encode(['success' => true, 'recipe' => $recipeData]);
        die();

    default:
        echo json_encode(['error' => 'Invalid task', 'task' => $task]);
        die();
}
