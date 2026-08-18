<?php
@ini_set('display_errors', '0');
@error_reporting(0);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';
$musicDir = __DIR__ . '/../music/';

define('MUSIC_JWT_SECRET', 'zmy_admin_secret_key_2026');

function base64UrlEncodeMusic($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

function musicAuthenticate() {
    // 兼容 GET 参数 token
    $token = $_GET['token'] ?? '';
    if (!$token) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$auth && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (!$auth && function_exists('getallheaders')) {
            $hdrs = getallheaders() ?: [];
            foreach ($hdrs as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) { $auth = $v; break; }
            }
        }
        if (!$auth && isset($_SERVER['HTTP_AUTHENTICATION'])) {
            $auth = $_SERVER['HTTP_AUTHENTICATION'];
        }
        if (!$auth) {
            foreach ($_SERVER as $k => $v) {
                if (is_string($v) && (strpos($k, 'AUTHORIZ') !== false || strpos($k, 'AUTHENTIC') !== false)) { $auth = $v; break; }
            }
        }
        if (strpos($auth, 'Bearer ') === 0) {
            $token = substr($auth, 7);
        }
    }
    if (!$token) return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    $secret = MUSIC_JWT_SECRET;
    $headerB64 = $parts[0];
    $payloadB64 = $parts[1];
    $sig = $parts[2];
    $expectedSig = base64UrlEncodeMusic(hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true));
    if ($sig !== $expectedSig) return false;
    $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) return false;
    return $payload;
}

if ($action === 'upload' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = musicAuthenticate();
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    
    @mkdir($musicDir, 0755, true);
    $files = $_FILES['music'] ?? null;
    if (!$files) {
        echo json_encode(['error' => '没有上传文件']);
        exit;
    }
    
    $count = 0;
    $names = [];
    $fileArray = is_array($files['name']) ? $files : ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']], 'size' => [$files['size']]];
    
    $namesList = $fileArray['name'];
    $tmpNames = $fileArray['tmp_name'];
    $errors = $fileArray['error'];
    
    for ($i = 0; $i < count($namesList); $i++) {
        if ($errors[$i] !== UPLOAD_ERR_OK) continue;
        $name = $namesList[$i];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac', 'lrc', 'txt'];
        if (!in_array($ext, $allowed)) continue;
        // 防止覆盖，重名则加后缀
        $destName = $name;
        $base = pathinfo($name, PATHINFO_FILENAME);
        $n = 1;
        while (file_exists($musicDir . $destName)) {
            $destName = $base . '_' . $n . '.' . $ext;
            $n++;
        }
        if ($tmpNames[$i] && move_uploaded_file($tmpNames[$i], $musicDir . $destName)) {
            $count++;
            $names[] = $destName;
        }
    }
    
    echo json_encode(['success' => true, 'count' => $count, 'files' => $names], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'delete' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = musicAuthenticate();
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $filename = $input['filename'] ?? '';
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => '缺少文件名']);
        exit;
    }
    // 安全检查：禁止路径穿越
    $basename = basename($filename);
    if ($basename !== $filename || strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        http_response_code(400);
        echo json_encode(['error' => '非法文件名']);
        exit;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac', 'lrc', 'txt'])) {
        http_response_code(400);
        echo json_encode(['error' => '不允许删除的类型']);
        exit;
    }
    $path = $musicDir . $filename;
    if (!file_exists($path)) {
        http_response_code(404);
        echo json_encode(['error' => '文件不存在']);
        exit;
    }
    if (@unlink($path)) {
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '删除失败']);
    }
    exit;
}

if ($action === 'list') {
    $songs = [];
    if (is_dir($musicDir)) {
        $files = scandir($musicDir);
        if ($files) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'])) {
                    $lrc = pathinfo($file, PATHINFO_FILENAME) . '.lrc';
                    $hasLrc = file_exists($musicDir . $lrc);
                    $songs[] = [
                        'name' => $file,
                        'url' => './music/' . $file,
                        'title' => pathinfo($file, PATHINFO_FILENAME),
                        'size' => filesize($musicDir . $file) ?: 0,
                        'hasLrc' => $hasLrc
                    ];
                }
            }
        }
    }
    echo json_encode(['success' => true, 'songs' => $songs], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
