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
    } catch (Exception $e) {
        return false;
    }
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
    if ($auth && strpos($auth, 'Bearer ') === 0) {
        $token = substr($auth, 7);
        $result = verifyToken($token);
        if ($result) return $result;
    }
    $token = $_GET['token'] ?? '';
    if ($token) {
        $result = verifyToken($token);
        if ($result) return $result;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$user = authenticate();
if ($user) {
    echo json_encode(['username' => $user['username']]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
}
?>