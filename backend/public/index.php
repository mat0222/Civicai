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

function categorize(string $text): string {
    $t = mb_strtolower($text);
    if (str_contains($t, 'bache')) return 'Bache';
    if (str_contains($t, 'luz') || str_contains($t, 'alumbrado')) return 'Alumbrado público';
    if (str_contains($t, 'basura')) return 'Basura acumulada';
    if (str_contains($t, 'agua')) return 'Pérdida de agua';
    return 'General';
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
        if ($texto === '' && empty($_FILES['imagen']['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe enviar texto o imagen']);
            exit;
        }

        $savedFilename = null;
        if (!empty($_FILES['imagen']['name'])) {
            if (!is_uploaded_file($_FILES['imagen']['tmp_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Archivo inválido']);
                exit;
            }
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

        $categoria = $texto ? categorize($texto) : 'General';
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