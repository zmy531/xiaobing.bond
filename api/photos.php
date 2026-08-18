<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

define('JWT_SECRET', 'zmy_admin_secret_key_2026');

function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

function verifyToken($token) {
    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        list($headerB64, $payloadB64, $signatureB64) = $parts;
        $expectedSignature = base64UrlEncode(hash_hmac('sha256', "$headerB64.$payloadB64", JWT_SECRET, true));
        if ($signatureB64 !== $expectedSignature) return false;
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payloadB64)), true);
        if (!$payload || $payload['exp'] < time()) return false;
        return $payload;
    } catch (Exception $e) {
        return false;
    }
}

function authenticate() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer ') !== 0) return false;
    $token = substr($auth, 7);
    return verifyToken($token);
}

define('PHOTO_DIR', __DIR__ . '/../uploads/photos/');
define('PHOTO_URL', '/uploads/photos/');
define('PHOTO_DATA', __DIR__ . '/../data/photos.json');
define('PHOTO_CONFIG', __DIR__ . '/../data/photo_config.json');

function loadPhotos() {
    if (!file_exists(PHOTO_DATA)) return [];
    return json_decode(file_get_contents(PHOTO_DATA), true) ?: [];
}

function savePhotos($photos) {
    @mkdir(dirname(PHOTO_DATA), 0755, true);
    file_put_contents(PHOTO_DATA, json_encode($photos, JSON_UNESCAPED_UNICODE));
}

function loadPhotoConfig() {
    if (!file_exists(PHOTO_CONFIG)) {
        return ['barrier_enabled' => false];
    }
    return json_decode(file_get_contents(PHOTO_CONFIG), true) ?: ['barrier_enabled' => false];
}

function savePhotoConfig($config) {
    @mkdir(dirname(PHOTO_CONFIG), 0755, true);
    file_put_contents(PHOTO_CONFIG, json_encode($config, JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'list';

// 公开接口 - 获取照片列表
if ($action === 'list') {
    $config = loadPhotoConfig();
    if ($config['barrier_enabled']) {
        echo json_encode(['success' => true, 'photos' => [], 'barrier' => true, 'message' => '照片墙已被管理员关闭']);
        exit;
    }
    $photos = loadPhotos();
    echo json_encode(['success' => true, 'photos' => $photos]);
    exit;
}

// 获取照片墙配置
if ($action === 'config') {
    $config = loadPhotoConfig();
    echo json_encode(['success' => true, 'config' => $config]);
    exit;
}

// 管理接口
$user = authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 更新照片墙配置（屏障开关）
if ($action === 'update_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['barrier_enabled'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少参数']);
        exit;
    }
    $config = loadPhotoConfig();
    $config['barrier_enabled'] = (bool)$input['barrier_enabled'];
    savePhotoConfig($config);
    echo json_encode(['success' => true, 'config' => $config]);
    exit;
}

// 上传照片
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    @mkdir(PHOTO_DIR, 0755, true);
    $photos = loadPhotos();
    $uploaded = [];

    foreach ($_FILES as $file) {
        if (!isset($file['error']) || is_array($file['error'])) continue;
        if ($file['error'] !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) continue;

        $filename = 'photo_' . uniqid() . '_' . time() . '.' . $ext;
        $path = PHOTO_DIR . $filename;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            $photo = [
                'id' => 'ph' . uniqid(),
                'url' => PHOTO_URL . $filename,
                'filename' => $filename,
                'title' => pathinfo($file['name'], PATHINFO_FILENAME),
                'time' => date('Y-m-d H:i:s')
            ];
            array_unshift($photos, $photo);
            $uploaded[] = $photo;
        }
    }

    savePhotos($photos);
    echo json_encode(['success' => true, 'uploaded' => count($uploaded), 'photos' => $photos]);
    exit;
}

// 删除照片
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => '缺少ID']);
        exit;
    }
    $photos = loadPhotos();
    $found = null;
    foreach ($photos as $k => $p) {
        if ($p['id'] === $id) {
            $found = $p;
            unset($photos[$k]);
            break;
        }
    }
    if ($found) {
        @unlink(PHOTO_DIR . $found['filename']);
        $photos = array_values($photos);
        savePhotos($photos);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => '照片不存在']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
