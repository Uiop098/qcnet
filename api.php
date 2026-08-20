<?php
// api.php — chat backend: fetch, send text messages, and clear a room.

require_once __DIR__ . '/common.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $room = isset($_GET['room']) ? $_GET['room'] : 'default';
    $since = isset($_GET['since']) ? intval($_GET['since']) : 0;

    $messages = read_messages($room);

    if ($since > 0) {
        $messages = array_values(array_filter($messages, function ($m) use ($since) {
            return isset($m['id']) && $m['id'] > $since;
        }));
    }

    echo json_encode([
        'ok' => true,
        'messages' => $messages,
    ]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!$body || empty($body['room']) || empty($body['user']) || !isset($body['text'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing room, user, or text']);
        exit;
    }

    $room = $body['room'];
    $user = mb_substr(trim(strip_tags($body['user'])), 0, 40);
    $text = trim(strip_tags($body['text']));

    if ($text === '' || $user === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Empty user or text']);
        exit;
    }
    if (mb_strlen($text) > 2000) {
        $text = mb_substr($text, 0, 2000);
    }

    $newMsg = append_message($room, [
        'type' => 'text',
        'user' => $user,
        'text' => $text,
    ]);

    echo json_encode(['ok' => true, 'message' => $newMsg]);
    exit;
}

if ($method === 'DELETE') {
    $room = isset($_GET['room']) ? $_GET['room'] : 'default';
    write_messages($room, []);

    // Also remove uploaded files for this room
    $dir = room_files_dir($room);
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') as $f) {
            if (is_file($f)) @unlink($f);
        }
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
