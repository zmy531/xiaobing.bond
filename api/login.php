<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

define('ADMIN_USER', 'zmy');
define('ADMIN_PASS', '080531');
define('JWT_SECRET', 'zmy_admin_secret_key_2026');

function generateToken($username) {
    $payload = ['username' => $username, 'exp' => time() + 7 * 24 * 3600];
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $headerB64 = base64UrlEncode(json_encode($header));
    $payloadB64 = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', "$headerB64.$payloadB64", JWT_SECRET, true);
    $signatureB64 = base64UrlEncode($signature);
    return "$headerB64.$payloadB64.$signatureB64";
}

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

function getIP() {
    if (isset($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

function logLogin($username, $success) {
    $log = ['id' => uniqid(), 'username' => $username, 'ip' => getIP(), 'time' => date('Y-m-d H:i:s'), 'success' => $success, 'ua' => $_SERVER['HTTP_USER_AGENT']];
    $file = __DIR__ . '/../data/logins.json';
    @mkdir(dirname($file), 0755, true);
    $logs = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    array_unshift($logs, $log);
    if (count($logs) > 500) $logs = array_slice($logs, 0, 500);
    file_put_contents($file, json_encode($logs));
}

function logVisitor() {
    $visitor = ['id' => uniqid(), 'ip' => getIP(), 'path' => $_SERVER['REQUEST_URI'], 'time' => date('Y-m-d H:i:s'), 'ua' => $_SERVER['HTTP_USER_AGENT']];
    $file = __DIR__ . '/../data/visitors.json';
    @mkdir(dirname($file), 0755, true);
    $visitors = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    array_unshift($visitors, $visitor);
    if (count($visitors) > 1000) $visitors = array_slice($visitors, 0, 1000);
    file_put_contents($file, json_encode($visitors));
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'POST' && strpos($uri, '/api/login') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        logLogin($username, true);
        $token = generateToken($username);
        echo json_encode(['token' => $token, 'username' => $username]);
    } else {
        logLogin($username, false);
        http_response_code(401);
        echo json_encode(['error' => '用户名或密码错误']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>