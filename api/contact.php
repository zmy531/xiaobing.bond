<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT');
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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$file = __DIR__ . '/../data/contact.json';

$defaultContact = [
    'wechat' => 'zmy_wechat',
    'qq' => '3372991529',
    'email' => '3372991529@qq.com',
    'wechat_qr' => './static/img/wxzsm.jpg'
];

function loadContact() {
    global $file, $defaultContact;
    if (!file_exists($file)) return $defaultContact;
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return $defaultContact;
    return array_merge($defaultContact, $data);
}

function saveContact($data) {
    global $file;
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

if ($method === 'GET') {
    echo json_encode(loadContact());
    exit;
}

if ($method === 'PUT' && $action === 'save') {
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        exit;
    }
    $contact = loadContact();
    $contact['wechat'] = $input['wechat'] ?? $contact['wechat'];
    $contact['qq'] = $input['qq'] ?? $contact['qq'];
    $contact['email'] = $input['email'] ?? $contact['email'];
    $contact['wechat_qr'] = $input['wechat_qr'] ?? $contact['wechat_qr'];
    saveContact($contact);
    echo json_encode(['success' => true, 'data' => $contact]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
