<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
    } catch (Exception $e) { return false; }
}
function authenticate() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer ') !== 0) return false;
    return verifyToken(substr($auth, 7));
}
function getIP() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
    return $ip;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(200); exit; }

$dataFile = __DIR__ . '/../data/software.json';
$uploadDir = __DIR__ . '/../data/software_files/';
$keyFile = __DIR__ . '/../data/sfz_keys.json';

function loadSoftware() {
    global $dataFile;
    return file_exists($dataFile) ? (json_decode(file_get_contents($dataFile), true) ?: []) : [];
}
function saveSoftware($data) {
    global $dataFile;
    @mkdir(dirname($dataFile), 0755, true);
    @file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE));
}
function loadKeys() {
    global $keyFile;
    return file_exists($keyFile) ? (json_decode(file_get_contents($keyFile), true) ?: []) : [];
}
function saveKeys($keys) {
    global $keyFile;
    @mkdir(dirname($keyFile), 0755, true);
    @file_put_contents($keyFile, json_encode($keys, JSON_UNESCAPED_UNICODE));
}

// 公开：获取软件列表
if ($method === 'GET' && ($_GET['action'] ?? '') === 'list') {
    $software = loadSoftware();
    $result = [];
    foreach ($software as $s) {
        $ext = strtolower(pathinfo($s['fileName'] ?? '', PATHINFO_EXTENSION));
        $result[] = [
            'id' => $s['id'],
            'name' => $s['name'],
            'description' => $s['description'] ?? '',
            'icon' => $s['icon'] ?? '📦',
            'size' => $s['size'] ?? 0,
            'version' => $s['version'] ?? '1.0',
            'requireKey' => $s['requireKey'] ?? false,
            'keyType' => $s['keyType'] ?? '',
            'downloadCount' => $s['downloadCount'] ?? 0,
            'fileName' => $s['fileName'] ?? '',
            'fileExt' => $ext,
            'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'])
        ];
    }
    echo json_encode($result);
    exit;
}

// 公开：预览图片
if ($method === 'GET' && ($_GET['action'] ?? '') === 'preview') {
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(404); echo 'Not found'; exit; }
    $software = loadSoftware();
    $target = null;
    foreach ($software as $s) {
        if ($s['id'] === $id) { $target = $s; break; }
    }
    if (!$target || !($target['filePath'] ?? '')) { http_response_code(404); echo '文件不存在'; exit; }
    $filePath = $uploadDir . $target['filePath'];
    if (!file_exists($filePath)) { http_response_code(404); echo '文件不存在'; exit; }
    $ext = strtolower(pathinfo($target['filePath'], PATHINFO_EXTENSION));
    $mimeMap = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'
    ];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile($filePath);
    exit;
}

// 公开：验证卡密下载
if ($method === 'POST' && ($_GET['action'] ?? '') === 'verify') {
    $body = json_decode(file_get_contents('php://input'), true);
    $softwareId = $body['softwareId'] ?? '';
    $key = trim($body['key'] ?? '');
    if (!$softwareId) {
        echo json_encode(['success' => false, 'error' => '参数错误']);
        exit;
    }
    $software = loadSoftware();
    $target = null;
    foreach ($software as $s) {
        if ($s['id'] === $softwareId) { $target = $s; break; }
    }
    if (!$target) {
        echo json_encode(['success' => false, 'error' => '软件不存在']);
        exit;
    }
    if (!($target['requireKey'] ?? false)) {
        $token = base64UrlEncode(json_encode([
            'softwareId' => $softwareId,
            'exp' => time() + 3600
        ]));
        echo json_encode(['success' => true, 'token' => $token]);
        exit;
    }
    if (!$key) {
        echo json_encode(['success' => false, 'error' => '请输入卡密']);
        exit;
    }
    $keys = loadKeys();
    foreach ($keys as &$k) {
        if ($k['key'] === $key && ($k['type'] ?? 'sfz') === ($target['keyType'] ?? 'sfz')) {
            if ($k['used'] ?? false) {
                echo json_encode(['success' => false, 'error' => '该卡密已被使用']);
                exit;
            }
            $k['used'] = true;
            $k['usedAt'] = date('Y-m-d H:i:s');
            $k['usedIp'] = getIP();
            $k['softwareId'] = $softwareId;
            saveKeys($keys);
            $token = base64UrlEncode(json_encode([
                'softwareId' => $softwareId,
                'key' => $key,
                'exp' => time() + 3600
            ]));
            echo json_encode(['success' => true, 'token' => $token]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => '卡密无效']);
    exit;
}

// 公开：下载文件
if ($method === 'GET' && ($_GET['action'] ?? '') === 'download') {
    $token = $_GET['token'] ?? '';
    $id = $_GET['id'] ?? '';
    if (!$token || !$id) {
        header('HTTP/1.1 403 Forbidden');
        echo '无效的下载链接';
        exit;
    }
    $data = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $token)), true);
    if (!$data || $data['exp'] < time() || $data['softwareId'] !== $id) {
        header('HTTP/1.1 403 Forbidden');
        echo '下载链接已过期，请重新验证';
        exit;
    }
    $software = loadSoftware();
    $target = null;
    foreach ($software as &$s) {
        if ($s['id'] === $id) { $target = &$s; break; }
    }
    if (!$target || !($target['filePath'] ?? '')) {
        header('HTTP/1.1 404 Not Found');
        echo '文件不存在';
        exit;
    }
    $filePath = $uploadDir . $target['filePath'];
    if (!file_exists($filePath)) {
        header('HTTP/1.1 404 Not Found');
        echo '文件不存在，请联系管理员';
        exit;
    }
    $target['downloadCount'] = ($target['downloadCount'] ?? 0) + 1;
    saveSoftware($software);
    $filename = $target['fileName'] ?? $target['filePath'];
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// 以下需要管理员权限
$user = authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 管理员：获取完整软件列表
if ($method === 'GET' && ($_GET['action'] ?? '') === 'admin_list') {
    echo json_encode(loadSoftware());
    exit;
}

// 管理员：上传软件
if ($method === 'POST' && ($_GET['action'] ?? '') === 'upload') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $icon = $_POST['icon'] ?? '📦';
    $version = $_POST['version'] ?? '1.0';
    $requireKey = isset($_POST['requireKey']) ? $_POST['requireKey'] === 'true' : false;
    $keyType = $_POST['keyType'] ?? 'sfz';
    
    if (!$name) {
        echo json_encode(['success' => false, 'error' => '请输入软件名称']);
        exit;
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => '请选择文件上传']);
        exit;
    }
    @mkdir($uploadDir, 0755, true);
    $fileExt = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $fileName = $_FILES['file']['name'];
    $storedName = uniqid('soft_') . '.' . $fileExt;
    $targetPath = $uploadDir . $storedName;
    
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'error' => '文件上传失败']);
        exit;
    }
    $software = loadSoftware();
    $newItem = [
        'id' => uniqid('s_'),
        'name' => $name,
        'description' => $description,
        'icon' => $icon,
        'version' => $version,
        'requireKey' => $requireKey,
        'keyType' => $keyType,
        'fileName' => $fileName,
        'filePath' => $storedName,
        'size' => $_FILES['file']['size'],
        'downloadCount' => 0,
        'createdAt' => date('Y-m-d H:i:s')
    ];
    $software[] = $newItem;
    saveSoftware($software);
    echo json_encode(['success' => true, 'data' => $newItem]);
    exit;
}

// 管理员：更新软件信息
if ($method === 'POST' && ($_GET['action'] ?? '') === 'update') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id = $body['id'] ?? '';
    $software = loadSoftware();
    $found = false;
    foreach ($software as &$s) {
        if ($s['id'] === $id) {
            $s['name'] = $body['name'] ?? $s['name'];
            $s['description'] = $body['description'] ?? $s['description'];
            $s['icon'] = $body['icon'] ?? $s['icon'];
            $s['version'] = $body['version'] ?? $s['version'];
            $s['requireKey'] = $body['requireKey'] ?? $s['requireKey'];
            $s['keyType'] = $body['keyType'] ?? $s['keyType'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo json_encode(['success' => false, 'error' => '软件不存在']);
        exit;
    }
    saveSoftware($software);
    echo json_encode(['success' => true]);
    exit;
}

// 管理员：删除软件
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id = $body['id'] ?? '';
    $software = loadSoftware();
    $deleted = null;
    $software = array_values(array_filter($software, function($s) use ($id, &$deleted) {
        if ($s['id'] === $id) { $deleted = $s; return false; }
        return true;
    }));
    if ($deleted && ($deleted['filePath'] ?? '')) {
        @unlink($uploadDir . $deleted['filePath']);
    }
    saveSoftware($software);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
