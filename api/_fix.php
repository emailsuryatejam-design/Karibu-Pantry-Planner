<?php
/**
 * Hot-deploy helper — uploads a file to the server.
 * POST with multipart: file + path (relative to project root).
 * Protected by a simple token.
 */
$token = 'karibu-deploy-2026';

if (($_GET['token'] ?? '') !== $token && ($_POST['token'] ?? '') !== $token) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? 'upload';

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $path = $_POST['path'] ?? '';
    if (!$path || strpos($path, '..') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid path']);
        exit;
    }

    $fullPath = realpath(__DIR__ . '/..') . '/' . ltrim($path, '/');
    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (isset($_FILES['file'])) {
        move_uploaded_file($_FILES['file']['tmp_name'], $fullPath);
        echo json_encode(['ok' => true, 'path' => $path, 'size' => filesize($fullPath)]);
    } elseif (isset($_POST['content'])) {
        file_put_contents($fullPath, $_POST['content']);
        echo json_encode(['ok' => true, 'path' => $path, 'size' => strlen($_POST['content'])]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'No file or content']);
    }
    exit;
}

if ($action === 'read') {
    $path = $_GET['path'] ?? '';
    $fullPath = realpath(__DIR__ . '/..') . '/' . ltrim($path, '/');
    if (file_exists($fullPath)) {
        header('Content-Type: text/plain');
        readfile($fullPath);
    } else {
        http_response_code(404);
        echo 'Not found';
    }
    exit;
}

echo json_encode(['status' => 'ready', 'action' => $action]);
