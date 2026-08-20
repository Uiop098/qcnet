<?php
// common.php — shared helpers for reading/writing room message data.

$GLOBALS['dataDir'] = __DIR__ . '/data';
$GLOBALS['filesDir'] = __DIR__ . '/files';
$GLOBALS['maxMessages'] = 300; // trim old messages beyond this count

// Auto-expiry: messages (and their uploaded files) older than this are
// purged automatically. No cron job needed — expiry is checked lazily
// whenever anyone loads or polls the chat, which works even on hosts
// that don't support scheduled tasks.
$GLOBALS['messageExpirySeconds'] = 24 * 60 * 60; // 24 hours

if (!is_dir($GLOBALS['dataDir'])) {
    mkdir($GLOBALS['dataDir'], 0755, true);
}
if (!is_dir($GLOBALS['filesDir'])) {
    mkdir($GLOBALS['filesDir'], 0755, true);
}

function safe_room_name($room) {
    $room = preg_replace('/[^a-zA-Z0-9_\-]/', '', $room);
    return substr($room, 0, 64) ?: 'default';
}

function room_file($room) {
    return $GLOBALS['dataDir'] . '/room_' . safe_room_name($room) . '.json';
}

function room_files_dir($room) {
    $dir = $GLOBALS['filesDir'] . '/' . safe_room_name($room);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

// Removes messages older than $messageExpirySeconds, deleting any
// uploaded files that belonged to them. Returns the surviving messages.
function purge_expired($room, $messages) {
    $expiryMs = $GLOBALS['messageExpirySeconds'] * 1000;
    $nowMs = round(microtime(true) * 1000);
    $changed = false;
    $kept = [];

    foreach ($messages as $m) {
        $age = $nowMs - (isset($m['ts']) ? $m['ts'] : 0);
        if ($age > $expiryMs) {
            $changed = true;
            if (isset($m['type']) && $m['type'] === 'file' && !empty($m['fileUrl'])) {
                $path = __DIR__ . '/' . ltrim($m['fileUrl'], '/');
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        } else {
            $kept[] = $m;
        }
    }

    if ($changed) {
        write_messages($room, $kept);
    }

    return $kept;
}

function read_messages($room) {
    $file = room_file($room);
    if (!file_exists($file)) {
        return [];
    }
    $fp = fopen($file, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    $messages = is_array($data) ? $data : [];
    return purge_expired($room, $messages);
}

function write_messages($room, $messages) {
    $file = room_file($room);
    $fp = fopen($file, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    if (count($messages) > $GLOBALS['maxMessages']) {
        $messages = array_slice($messages, -1 * $GLOBALS['maxMessages']);
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function next_message_id($messages) {
    $nextId = 1;
    foreach ($messages as $m) {
        if (isset($m['id']) && $m['id'] >= $nextId) {
            $nextId = $m['id'] + 1;
        }
    }
    return $nextId;
}

function append_message($room, $msg) {
    $messages = read_messages($room); // also purges anything expired
    $msg['id'] = next_message_id($messages);
    $msg['ts'] = round(microtime(true) * 1000);
    $messages[] = $msg;
    write_messages($room, $messages);
    return $msg;
}
