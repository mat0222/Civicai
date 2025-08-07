<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$baseDir = dirname(__DIR__);
$uploadsDir = $baseDir . '/public/uploads';
$dataDir = $baseDir . '/data';
$dbPath = $dataDir . '/app.sqlite';

if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0775, true);
}
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

function getPdo(string $dbPath): PDO {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS reclamos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        texto TEXT NOT NULL,
        imagen TEXT,
        categoria TEXT,
        created_at TEXT NOT NULL
    )');
    return $pdo;
}

function categorize(string $text) /*: ?string*/ {
    $t = mb_strtolower($text);

    // ✅ Válidos
    $valid = [
        'Baches, calles en mal estado' => [
            'bache','pozo','calle rota','calle en mal estado','grieta','asfalto','hoyo','hundimiento'
        ],
        'Semáforos dañados' => [
            'semáforo','semaforo','semaforos','semáforos','semáforo dañado','semáforo roto','semáforo sin luz'
        ],
        'Basura acumulada' => [
            'basura','residuos','escombros','microbasural','bolsas de basura','contendedor lleno','contenedor lleno'
        ],
        'Problemas de alumbrado' => [
            'alumbrado','farola','poste de luz','luz quemada','sin luz','luminaria','lámpara','lampara'
        ],
        'Alcantarillas destapadas' => [
            'alcantarilla','cloaca','tapa de cloaca','desagüe','desague','rejilla','tormenta tapada'
        ],
        'Infraestructura deteriorada' => [
            'vereda rota','vereda','cordón roto','cordon roto','banco roto','cartel caído','cartel caido','baranda rota','juego infantil roto','plaza deteriorada'
        ],
    ];

    foreach ($valid as $category => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($t, $kw)) {
                return $category;
            }
        }
    }

    return null;
}

function isInvalidContent(string $text): bool {
    $t = mb_strtolower($text);
    $blocked = [
        // ❌ Personas en situaciones privadas
        'privado','intimo','íntimo','baño','dormitorio','habitacion','habitación','desnudo','rostro','persona identificable',
        // ❌ Ofensivo/violento
        'violencia','sangre','arma','amenaza','agresión','agresion','discriminación','discriminacion','insulto',
        // ❌ Interiores privados
        'interior de casa','casa por dentro','living','cocina','oficina privada','dentro de','domicilio',
        // ❌ No municipal
        'privado no municipal','empresa privada','propiedad privada','dentro de negocio','dentro de comercio',
        // ❌ Memes o gráficas
        'meme','humor gráfico','dibujo','caricatura',
        // ❌ Documentos de texto
        'pdf','documento','docx','word','texto escaneado'
    ];
    foreach ($blocked as $kw) {
        if (str_contains($t, $kw)) return true;
    }
    return false;
}

function ensureImageUploadIsValid(array $file): void {
    if (!is_uploaded_file($file['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Archivo inválido']);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg','image/png','image/webp'];
    if (!in_array($mime, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Solo se aceptan imágenes JPG, PNG o WEBP']);
        exit;
    }
}

try {
    $pdo = getPdo($dbPath);

    if ($path === '/api/health') {
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($path === '/api/reclamos' && $method === 'GET') {
        $stmt = $pdo->query('SELECT id, texto, imagen, categoria, created_at FROM reclamos ORDER BY id DESC LIMIT 50');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($path === '/api/reclamos' && $method === 'POST') {
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === false && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/x-www-form-urlencoded') === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Content-Type inválido']);
            exit;
        }

        $texto = trim((string)($_POST['texto'] ?? ''));
        $hasImage = !empty($_FILES['imagen']['name'] ?? '');

        if ($texto === '' && !$hasImage) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe enviar una descripción y/o imagen']);
            exit;
        }

        if ($hasImage) {
            ensureImageUploadIsValid($_FILES['imagen']);
        }

        if ($texto === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Agregue una descripción para validar el reclamo']);
            exit;
        }

        if (isInvalidContent($texto)) {
            http_response_code(422);
            echo json_encode(['error' => 'Contenido no válido según criterios (privado/ofensivo/no municipal/meme/documento).']);
            exit;
        }

        $categoria = categorize($texto);
        if ($categoria === null) {
            http_response_code(422);
            echo json_encode(['error' => 'Situación no municipal o no reconocida. Describa un problema urbano válido.']);
            exit;
        }

        $savedFilename = null;
        if ($hasImage) {
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $basename = bin2hex(random_bytes(8));
            $filename = $basename . ($ext ? ('.' . strtolower($ext)) : '');
            $dest = $uploadsDir . '/' . $filename;
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $dest)) {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo guardar la imagen']);
                exit;
            }
            $savedFilename = $filename;
        }

        $createdAt = (new DateTime('now', new DateTimeZone('UTC')))->format(DateTime::ATOM);

        $stmt = $pdo->prepare('INSERT INTO reclamos (texto, imagen, categoria, created_at) VALUES (:texto, :imagen, :categoria, :created_at)');
        $stmt->execute([
            ':texto' => $texto,
            ':imagen' => $savedFilename,
            ':categoria' => $categoria,
            ':created_at' => $createdAt,
        ]);

        $id = (int)$pdo->lastInsertId();

        echo json_encode([
            'id' => $id,
            'texto' => $texto,
            'imagen' => $savedFilename,
            'categoria' => $categoria,
            'created_at' => $createdAt,
        ]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}