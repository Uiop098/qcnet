<?php
// upload.php — handles file uploads (images, documents, audio, video, zip)
// and records them as messages, stored on disk under files/<room>/

require_once __DIR__ . '/common.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// --- Config: allowed file types and size limit ---
$allowedExtensions = [
    // images
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
    // documents
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf',
    // audio
    'mp3', 'wav', 'ogg', 'm4a', 'aac',
    // video
    'mp4', 'webm', 'mov', 'mkv', 'avi',
    // archives
    'zip', 'rar', '7z',
];
$maxFileSizeBytes = 25 * 1024 * 1024; // 25 MB — raise if your host allows larger uploads

// --- Validate request ---
if (empty($_POST['room']) || empty($_POST['user'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing room or user']);
    exit;
}
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err = isset($_FILES['file']) ? $_FILES['file']['error'] : 'no file';
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Upload failed (code: ' . $err . ')']);
    exit;
}

$room = $_POST['room'];
$user = mb_substr(trim(strip_tags($_POST['user'])), 0, 40);
$caption = isset($_POST['caption']) ? trim(strip_tags($_POST['caption'])) : '';

$file = $_FILES['file'];

if ($file['size'] > $maxFileSizeBytes) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File too large (max ' . round($maxFileSizeBytes / 1024 / 1024) . ' MB)']);
    exit;
}

$origName = basename($file['name']);
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File type .' . $ext . ' is not allowed']);
    exit;
}

// Build a safe, unique filename to avoid collisions/overwrites
$safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
$safeBase = substr($safeBase, 0, 60) ?: 'file';
$uniqueName = $safeBase . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;

$destDir = room_files_dir($room);
$destPath = $destDir . '/' . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save file on server']);
    exit;
}

// Category used by frontend to decide how to render (image/audio/video/other)
$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
$audioExts = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
$videoExts = ['mp4', 'webm', 'mov', 'mkv', 'avi'];

if (in_array($ext, $imageExts, true)) {
    $category = 'image';
} elseif (in_array($ext, $audioExts, true)) {
    $category = 'audio';
} elseif (in_array($ext, $videoExts, true)) {
    $category = 'video';
} else {
    $category = 'file';
}

// Relative URL the frontend can use directly (files/<room>/<name>)
$fileUrl = 'files/' . rawurlencode(safe_room_name($room)) . '/' . rawurlencode($uniqueName);

$newMsg = append_message($room, [
    'type' => 'file',
    'user' => $user,
    'text' => $caption,
    'fileName' => $origName,
    'fileUrl' => $fileUrl,
    'fileSize' => $file['size'],
    'fileExt' => $ext,
    'fileCategory' => $category,
]);

echo json_encode(['ok' => true, 'message' => $newMsg]);
