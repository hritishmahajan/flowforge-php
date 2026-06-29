<?php
declare(strict_types=1);

/**
 * FlowForge — single-file REST API + static file server.
 *
 * No framework, no Composer install required. PHP's edge in one file:
 * run anywhere with `php -S localhost:8000`. Routes /api/* to the engine,
 * serves the dashboard for everything else.
 */

require __DIR__ . '/lib/Storage.php';
require __DIR__ . '/lib/Engine.php';

use FlowForge\{Engine, JsonStorage, MysqlStorage};

// --- Storage selection: MySQL if configured, else zero-config JSON --------
$store = getenv('FLOWFORGE_DSN')
    ? new MysqlStorage(getenv('FLOWFORGE_DSN'), getenv('FLOWFORGE_DB_USER') ?: 'root', getenv('FLOWFORGE_DB_PASS') ?: '')
    : new JsonStorage(__DIR__ . '/data');

$engine = new Engine($store);

// --- Tiny router ----------------------------------------------------------
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Serve the dashboard at root.
if ($path === '/' || $path === '') {
    header('Content-Type: text/html');
    readfile(__DIR__ . '/docs/index.html');
    exit;
}

// Static assets for the dashboard.
if (in_array($path, ['/app.js', '/style.css'], true)) {
    $file = __DIR__ . '/docs' . $path;
    if (is_file($file)) {
        header('Content-Type: ' . (str_ends_with($path, '.js') ? 'application/javascript' : 'text/css'));
        readfile($file);
        exit;
    }
}

if (!str_starts_with($path, '/api/')) {
    http_response_code(404);
    exit('Not found');
}

// --- REST API -------------------------------------------------------------
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($method === 'OPTIONS') {
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    switch ("$method $path") {
        case 'GET /api/health':
            echo json_encode(['ok' => true, 'storage' => getenv('FLOWFORGE_DSN') ? 'mysql' : 'json', 'php' => PHP_VERSION]);
            break;

        case 'GET /api/workflows':
            echo json_encode($store->all('workflows'));
            break;

        case 'POST /api/workflows':
            $wf = $store->insert('workflows', [
                'name'       => (string)($body['name'] ?? 'Untitled'),
                'trigger'    => (string)($body['trigger'] ?? ''),
                'conditions' => (array)($body['conditions'] ?? []),
                'actions'    => (array)($body['actions'] ?? []),
            ]);
            http_response_code(201);
            echo json_encode($wf);
            break;

        case 'POST /api/events':
            // The integration entry point — fire an event, get back the runs.
            echo json_encode($engine->ingest($body));
            break;

        case 'GET /api/runs':
            echo json_encode(array_reverse($store->all('runs')));
            break;

        case 'DELETE /api/reset':
            $store->clear('workflows');
            $store->clear('runs');
            echo json_encode(['reset' => true]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'no such endpoint']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
