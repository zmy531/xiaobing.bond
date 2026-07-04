<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

$exePath = __DIR__ . '/../tools/sfz.exe';
$recordsFile = __DIR__ . '/../data/idcard_records.json';

// 启动exe
if ($method === 'POST' && ($_GET['action'] ?? '') === 'launch') {
    if (!file_exists($exePath)) {
        echo json_encode(['success' => false, 'error' => '程序文件不存在，请先上传 sfz.exe 到 tools 目录']);
        exit;
    }
    // Windows下启动exe（非阻塞）
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = 'start "" "' . $exePath . '"';
        pclose(popen($cmd, 'w'));
    } else {
        $cmd = escapeshellarg($exePath) . ' &';
        exec($cmd);
    }
    // 记录启动
    $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
    $records[] = [
        'id' => uniqid('id_'),
        'ip' => getIP(),
        'time' => date('Y-m-d H:i:s'),
        'action' => 'launch'
    ];
    @mkdir(dirname($recordsFile), 0755, true);
    @file_put_contents($recordsFile, json_encode($records, JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'message' => '程序已启动，请查看桌面']);
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
    echo json_encode(array_reverse($records));
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
