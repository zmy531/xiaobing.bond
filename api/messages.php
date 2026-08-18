<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

define('JWT_SECRET', 'zmy_admin_secret_key_2026');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

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

function saveFile($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'ogg'];
    if (!in_array(strtolower($ext), $allowed)) return null;
    $filename = uniqid() . '.' . $ext;
    $path = UPLOAD_DIR . $filename;
    @mkdir(UPLOAD_DIR, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $filename;
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$file = __DIR__ . '/../data/messages.json';
$messages = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$uri = $_SERVER['REQUEST_URI'];
$id = null;
if (preg_match('/\/api\/messages(?:\.php)?\/([^\/]+)$/', $uri, $matches)) {
    $id = $matches[1];
}

if ($method === 'GET') {
    if ($id) {
        $found = array_filter($messages, fn($m) => ($m['id'] ?? '') === $id);
        if ($found) {
            $item = reset($found);
            $item['location'] = getIPLocation($item['ip'] ?? '');
            echo json_encode($item);
        } else {
            echo json_encode(null);
        }
    } else {
        foreach ($messages as &$m) {
            $m['location'] = getIPLocation($m['ip'] ?? '');
        }
        echo json_encode($messages);
    }
} elseif ($method === 'POST') {
    $name = $_POST['name'] ?? '匿名';
    $content = $_POST['content'] ?? '';
    $qq = trim($_POST['qq'] ?? '');
    $image = saveFile($_FILES['image'] ?? null);
    $voice = saveFile($_FILES['voice'] ?? null);
    
    function getIP() {
        if (isset($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    $msg = [
        'id' => uniqid(),
        'name' => $name,
        'qq' => $qq,
        'content' => $content,
        'image' => $image,
        'voice' => $voice,
        'ip' => getIP(),
        'time' => date('Y-m-d H:i:s')
    ];
    array_unshift($messages, $msg);
    if (count($messages) > 500) $messages = array_slice($messages, 0, 500);
    file_put_contents($file, json_encode($messages));
    echo json_encode(['success' => true, 'message' => $msg]);
} elseif ($method === 'DELETE') {
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    if ($id) {
        foreach ($messages as $i => $m) {
            if (($m['id'] ?? '') === $id) {
                if ($m['image']) {
                    @unlink(UPLOAD_DIR . $m['image']);
                }
                if ($m['voice']) {
                    @unlink(UPLOAD_DIR . $m['voice']);
                }
                array_splice($messages, $i, 1);
                file_put_contents($file, json_encode($messages));
                echo json_encode(['success' => true]);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ID']);
    }
}
?>