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
    if (strpos($auth, 'Bearer ') !== 0) return false;
    return verifyToken(substr($auth, 7));
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
    $data = json_decode(file_get_contents('php://input'), true);
    $base64 = $data['photo'] ?? '';
    if (!$base64) {
        // 也支持multipart上传
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = 'png';
            $filename = uniqid('particle_') . '.' . $ext;
            @mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
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
    // base64方式
    $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
    $imgData = base64_decode($base64);
    if (!$imgData) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid base64']);
        exit;
    }
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

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
