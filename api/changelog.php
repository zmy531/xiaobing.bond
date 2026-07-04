<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
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

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$file = __DIR__ . '/../data/changelog.json';
$changelog = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($changelog)) $changelog = [];

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if ($method === 'GET') {
    usort($changelog, function($a, $b) {
        return strtotime($b['date'] ?? '') - strtotime($a['date'] ?? '');
    });
    echo json_encode(array_values($changelog));
} elseif ($method === 'POST') {
    if ($action !== 'add') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }
    
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $date = $data['date'] ?? '';
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    
    if (!$date || !$title || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $newId = uniqid('cl_');
    $newItem = [
        'id' => $newId,
        'date' => $date,
        'title' => $title,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $changelog[] = $newItem;
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, json_encode($changelog, JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'item' => $newItem]);
} elseif ($method === 'PUT') {
    if ($action !== 'edit' || !$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action or missing id']);
        exit;
    }
    
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $date = $data['date'] ?? '';
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    
    if (!$date || !$title || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $found = false;
    foreach ($changelog as &$item) {
        if (($item['id'] ?? '') === $id) {
            $item['date'] = $date;
            $item['title'] = $title;
            $item['content'] = $content;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        exit;
    }
    
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, json_encode($changelog, JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
} elseif ($method === 'DELETE') {
    if ($action !== 'delete' || !$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action or missing id']);
        exit;
    }
    
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $changelog = array_filter($changelog, function($item) use ($id) {
        return ($item['id'] ?? '') !== $id;
    });
    
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, json_encode(array_values($changelog), JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
