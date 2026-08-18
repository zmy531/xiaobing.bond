<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
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
            if (strpos($k, 'AUTHORIZ') !== false && is_string($v)) { $auth = $v; break; }
        }
    }
    if (strpos($auth, 'Bearer ') !== 0) return false;
    $token = substr($auth, 7);
    return verifyToken($token);
}
function getIP() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
    return $ip;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if ($method === 'OPTIONS') { http_response_code(200); exit; }

$recordsFile = __DIR__ . '/../data/particle_records.json';
$uploadDir = __DIR__ . '/../uploads/particle/';
$videoDir = __DIR__ . '/../uploads/particle/video/';

// 记录使用
if ($method === 'POST' && $action === 'record') {
    $data = json_decode(file_get_contents('php://input'), true);
    $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
    $newRecord = [
        'id' => uniqid('pt_'),
        'ip' => getIP(),
        'shape' => $data['shape'] ?? 'heart',
        'duration' => $data['duration'] ?? 0,
        'time' => date('Y-m-d H:i:s')
    ];
    $records[] = $newRecord;
    @mkdir(dirname($recordsFile), 0755, true);
    @file_put_contents($recordsFile, json_encode($records, JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit;
}

// 上传照片
if ($method === 'POST' && $action === 'upload') {
    // 支持 image 和 photo 两个字段名
    $file = null;
    $fieldName = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $fieldName = 'image';
    } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $fieldName = 'photo';
    }
    
    if ($file) {
        $ext = 'jpg';
        $filename = uniqid('particle_') . '.' . $ext;
        @mkdir($uploadDir, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
            $records[] = [
                'id' => uniqid('pt_'),
                'ip' => getIP(),
                'type' => 'photo',
                'photo' => './uploads/particle/' . $filename,
                'time' => date('Y-m-d H:i:s')
            ];
            @mkdir(dirname($recordsFile), 0755, true);
            @file_put_contents($recordsFile, json_encode($records, JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true, 'url' => './uploads/particle/' . $filename]);
            exit;
        }
    }
    
    // base64方式
    $data = json_decode(file_get_contents('php://input'), true);
    $base64 = $data['photo'] ?? $data['image'] ?? '';
    if ($base64) {
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $imgData = base64_decode($base64);
        if ($imgData) {
            $filename = uniqid('particle_') . '.png';
            @mkdir($uploadDir, 0755, true);
            file_put_contents($uploadDir . $filename, $imgData);
            $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
            $records[] = [
                'id' => uniqid('pt_'),
                'ip' => getIP(),
                'type' => 'photo',
                'photo' => './uploads/particle/' . $filename,
                'time' => date('Y-m-d H:i:s')
            ];
            @mkdir(dirname($recordsFile), 0755, true);
            @file_put_contents($recordsFile, json_encode($records, JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true, 'url' => './uploads/particle/' . $filename]);
            exit;
        }
    }
    
    http_response_code(400);
    echo json_encode(['error' => 'No photo data']);
    exit;
}

// 上传视频片段
if ($method === 'POST' && $action === 'upload_video') {
    $file = null;
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['video'];
    }
    
    if (!$file) {
        http_response_code(400);
        echo json_encode(['error' => 'No video data']);
        exit;
    }
    
    $ext = 'webm';
    $originalName = $file['name'] ?? 'segment.webm';
    $nameExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($nameExt, ['webm', 'mp4', 'mov', 'mkv'])) {
        $ext = $nameExt;
    }
    
    $filename = uniqid('particle_vid_') . '.' . $ext;
    @mkdir($videoDir, 0755, true);
    
    if (move_uploaded_file($file['tmp_name'], $videoDir . $filename)) {
        $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
        $records[] = [
            'id' => uniqid('pt_'),
            'ip' => getIP(),
            'type' => 'video',
            'video' => './uploads/particle/video/' . $filename,
            'size' => $file['size'] ?? 0,
            'time' => date('Y-m-d H:i:s')
        ];
        @mkdir(dirname($recordsFile), 0755, true);
        @file_put_contents($recordsFile, json_encode($records, JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'url' => './uploads/particle/video/' . $filename]);
        exit;
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

// 获取记录（管理员）
if ($method === 'GET') {
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
    $records = array_reverse($records);
    echo json_encode($records);
    exit;
}

// 删除照片（管理员）
if ($method === 'DELETE') {
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $id = $_GET['id'] ?? '';
    $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
    foreach ($records as $i => $r) {
        if (($r['id'] ?? '') === $id) {
            if (isset($r['photo'])) {
                $photoPath = __DIR__ . '/../' . $r['photo'];
                @unlink($photoPath);
            }
            array_splice($records, $i, 1);
            break;
        }
    }
    @file_put_contents($recordsFile, json_encode($records, JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit;
}

// 批量删除照片（管理员）
if ($method === 'POST' && $action === 'batch_delete') {
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['success' => true, 'deleted' => 0]);
        exit;
    }
    $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
    $deleted = 0;
    foreach ($records as $i => $r) {
        if (in_array($r['id'] ?? '', $ids)) {
            if (isset($r['photo'])) {
                $photoPath = __DIR__ . '/../' . $r['photo'];
                @unlink($photoPath);
            }
            if (isset($r['video'])) {
                $videoPath = __DIR__ . '/../' . $r['video'];
                @unlink($videoPath);
            }
            unset($records[$i]);
            $deleted++;
        }
    }
    $records = array_values($records);
    @file_put_contents($recordsFile, json_encode($records, JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
