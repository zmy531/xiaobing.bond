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

function getIPLocation($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') return '本地网络';
    if (!$ip) return '未知';
    $cacheFile = __DIR__ . '/../data/ip_cache.json';
    $cache = file_exists($cacheFile) ? (json_decode(file_get_contents($cacheFile), true) ?: []) : [];
    if (isset($cache[$ip])) return $cache[$ip];
    $location = '未知地区';
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://whois.pconline.com.cn/ipJson.jsp?ip=$ip&json=true");
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $data = json_decode($result, true);
            if ($data && isset($data['addr'])) $location = $data['addr'];
        }
    } catch (Exception $e) {}
    if ($location === '未知地区') {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/$ip?lang=zh-CN");
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result) {
                $data = json_decode($result, true);
                if ($data && $data['status'] === 'success') {
                    $loc = [];
                    if ($data['regionName']) $loc[] = $data['regionName'];
                    if ($data['city']) $loc[] = $data['city'];
                    $location = implode(' ', $loc) ?: '未知地区';
                }
            }
        } catch (Exception $e) {}
    }
    $cache[$ip] = $location;
    @mkdir(dirname($cacheFile), 0755, true);
    @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
    return $location;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$user = authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$file = __DIR__ . '/../data/logins.json';
$logins = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$uri = $_SERVER['REQUEST_URI'];
$id = null;
if (preg_match('/\/api\/logins(?:\.php)?\/([^\/]+)$/', $uri, $matches)) {
    $id = $matches[1];
}

if ($method === 'GET') {
    if ($id) {
        $found = array_filter($logins, fn($l) => ($l['id'] ?? '') === $id);
        if ($found) {
            $item = reset($found);
            $item['location'] = getIPLocation($item['ip'] ?? '');
            echo json_encode($item);
        } else {
            echo json_encode(null);
        }
    } else {
        foreach ($logins as &$l) {
            $l['location'] = getIPLocation($l['ip'] ?? '');
        }
        echo json_encode($logins);
    }
} elseif ($method === 'DELETE') {
    if ($id) {
        $logins = array_filter($logins, fn($l) => ($l['id'] ?? '') !== $id);
        file_put_contents($file, json_encode(array_values($logins)));
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ID']);
    }
} elseif ($method === 'PUT') {
    if ($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $note = $data['note'] ?? '';
        foreach ($logins as &$l) {
            if (($l['id'] ?? '') === $id) {
                $l['note'] = $note;
                break;
            }
        }
        file_put_contents($file, json_encode($logins));
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ID']);
    }
}
?>