<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
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
if ($method === 'OPTIONS') { http_response_code(200); exit; }

$keysFile = __DIR__ . '/../data/sfz_keys.json';
$recordsFile = __DIR__ . '/../data/sfz_records.json';
$exePath = __DIR__ . '/../tools/sfz.exe';
$typesFile = __DIR__ . '/../data/key_types.json';

// 读取卡密
function loadKeys() {
    global $keysFile;
    return file_exists($keysFile) ? (json_decode(file_get_contents($keysFile), true) ?: []) : [];
}
function saveKeys($keys) {
    global $keysFile;
    @mkdir(dirname($keysFile), 0755, true);
    @file_put_contents($keysFile, json_encode($keys, JSON_UNESCAPED_UNICODE));
}
function loadRecords() {
    global $recordsFile;
    return file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
}
function saveRecords($records) {
    global $recordsFile;
    @mkdir(dirname($recordsFile), 0755, true);
    @file_put_contents($recordsFile, json_encode($records, JSON_UNESCAPED_UNICODE));
}
function loadKeyTypes() {
    global $typesFile;
    if (!file_exists($typesFile)) {
        $default = [['prefix' => 'sfz', 'name' => '身份证合成']];
        @mkdir(dirname($typesFile), 0755, true);
        @file_put_contents($typesFile, json_encode($default, JSON_UNESCAPED_UNICODE));
        return $default;
    }
    $data = json_decode(file_get_contents($typesFile), true);
    if (!is_array($data) || empty($data)) {
        $default = [['prefix' => 'sfz', 'name' => '身份证合成']];
        @file_put_contents($typesFile, json_encode($default, JSON_UNESCAPED_UNICODE));
        return $default;
    }
    return $data;
}
function saveKeyTypes($types) {
    global $typesFile;
    @mkdir(dirname($typesFile), 0755, true);
    @file_put_contents($typesFile, json_encode($types, JSON_UNESCAPED_UNICODE));
}

// 生成卡密：zmy-XXXglbXXX（字母数字结合，中间含glb）
function generateKeyString($prefix = 'sfz') {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $len = strlen($chars);
    $part1 = '';
    $part2 = '';
    for ($i = 0; $i < 3; $i++) {
        $part1 .= $chars[rand(0, $len - 1)];
        $part2 .= $chars[rand(0, $len - 1)];
    }
    return $prefix . '-' . $part1 . 'glb' . $part2;
}

// 公开接口：获取卡密类型列表
if ($method === 'GET' && ($_GET['action'] ?? '') === 'types') {
    echo json_encode(['success' => true, 'types' => loadKeyTypes()]);
    exit;
}

// 验证卡密（公开）
if ($method === 'POST' && ($_GET['action'] ?? '') === 'verify') {
    $body = json_decode(file_get_contents('php://input'), true);
    $key = trim($body['key'] ?? '');
    if (!$key) {
        echo json_encode(['success' => false, 'error' => '请输入卡密']);
        exit;
    }
    $keys = loadKeys();
    foreach ($keys as &$k) {
        if ($k['key'] === $key) {
            if ($k['used']) {
                echo json_encode(['success' => false, 'error' => '该卡密已被使用']);
                exit;
            }
            $k['used'] = true;
            $k['usedAt'] = date('Y-m-d H:i:s');
            $k['usedIp'] = getIP();
            saveKeys($keys);
            // 记录
            $records = loadRecords();
            $records[] = [
                'id' => uniqid('r_'),
                'key' => $key,
                'type' => $k['type'] ?? 'sfz',
                'ip' => getIP(),
                'time' => date('Y-m-d H:i:s'),
                'action' => 'download'
            ];
            saveRecords($records);
            // 生成临时下载token（1小时有效）
            $token = base64UrlEncode(json_encode([
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

// 下载文件（通过token）
if ($method === 'GET' && ($_GET['action'] ?? '') === 'download') {
    $token = $_GET['token'] ?? '';
    if (!$token) {
        header('HTTP/1.1 403 Forbidden');
        echo '无效的下载链接';
        exit;
    }
    $data = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $token)), true);
    if (!$data || $data['exp'] < time()) {
        header('HTTP/1.1 403 Forbidden');
        echo '下载链接已过期，请重新验证卡密';
        exit;
    }
    if (!file_exists($exePath)) {
        header('HTTP/1.1 404 Not Found');
        echo '文件不存在，请联系管理员';
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="sfz.exe"');
    header('Content-Length: ' . filesize($exePath));
    readfile($exePath);
    exit;
}

// 以下需要管理员权限
$user = authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 获取卡密列表/使用记录
if ($method === 'GET') {
    $type = $_GET['type'] ?? 'keys';
    $keyType = $_GET['keyType'] ?? '';
    if ($type === 'records') {
        $records = loadRecords();
        if ($keyType) {
            $records = array_values(array_filter($records, fn($r) => ($r['type'] ?? 'sfz') === $keyType));
        }
        echo json_encode(array_reverse($records));
    } elseif ($type === 'stats') {
        $keys = loadKeys();
        $total = count($keys);
        $used = count(array_filter($keys, fn($k) => $k['used'] ?? false));
        $unused = $total - $used;
        // 按类型分组
        $byType = [];
        foreach ($keys as $k) {
            $t = $k['type'] ?? 'sfz';
            if (!isset($byType[$t])) $byType[$t] = ['total' => 0, 'used' => 0, 'unused' => 0];
            $byType[$t]['total']++;
            if ($k['used'] ?? false) $byType[$t]['used']++;
            else $byType[$t]['unused']++;
        }
        echo json_encode(['total' => $total, 'used' => $used, 'unused' => $unused, 'byType' => $byType]);
    } else {
        $keys = loadKeys();
        if ($keyType) {
            $keys = array_values(array_filter($keys, fn($k) => ($k['type'] ?? 'sfz') === $keyType));
        }
        echo json_encode(array_reverse($keys));
    }
    exit;
}

// 生成卡密（批量）
if ($method === 'POST' && ($_GET['action'] ?? '') === 'add') {
    $body = json_decode(file_get_contents('php://input'), true);
    $count = intval($body['count'] ?? 1);
    if ($count < 1) $count = 1;
    if ($count > 500) $count = 500;
    $keyType = strtolower(trim($body['keyType'] ?? 'sfz'));
    // 校验类型合法性
    $types = loadKeyTypes();
    $validPrefixes = array_map(fn($t) => strtolower($t['prefix']), $types);
    if (!in_array($keyType, $validPrefixes)) {
        echo json_encode(['success' => false, 'error' => '无效的卡密类型：' . $keyType]);
        exit;
    }
    $days = intval($body['days'] ?? 365);
    $keys = loadKeys();
    $newKeys = [];
    for ($i = 0; $i < $count; $i++) {
        $keyStr = generateKeyString($keyType);
        $tries = 0;
        while (count(array_filter($keys, fn($k) => $k['key'] === $keyStr)) > 0 && $tries < 10) {
            $keyStr = generateKeyString($keyType);
            $tries++;
        }
        $newKeys[] = [
            'id' => uniqid('k_'),
            'key' => $keyStr,
            'type' => $keyType,
            'days' => $days,
            'used' => false,
            'usedAt' => null,
            'usedIp' => null,
            'createdAt' => date('Y-m-d H:i:s')
        ];
    }
    $keys = array_merge($keys, $newKeys);
    saveKeys($keys);
    echo json_encode(['success' => true, 'keys' => $newKeys]);
    exit;
}

// 批量导入卡密（手动上传）
if ($method === 'POST' && ($_GET['action'] ?? '') === 'import') {
    $body = json_decode(file_get_contents('php://input'), true);
    $keysText = trim($body['keys'] ?? '');
    $keyType = strtolower(trim($body['keyType'] ?? 'sfz'));
    // 校验类型合法性
    $types = loadKeyTypes();
    $validPrefixes = array_map(fn($t) => strtolower($t['prefix']), $types);
    if (!in_array($keyType, $validPrefixes)) {
        echo json_encode(['success' => false, 'error' => '无效的卡密类型：' . $keyType]);
        exit;
    }
    if (!$keysText) {
        echo json_encode(['success' => false, 'error' => '请输入卡密']);
        exit;
    }
    $lines = array_filter(array_map('trim', explode("\n", $keysText)));
    $keys = loadKeys();
    $existingKeys = array_column($keys, 'key');
    $imported = 0;
    $skipped = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        if (in_array($line, $existingKeys)) { $skipped++; continue; }
        $keys[] = [
            'id' => uniqid('k_'),
            'key' => $line,
            'type' => $keyType,
            'days' => 365,
            'used' => false,
            'usedAt' => null,
            'usedIp' => null,
            'createdAt' => date('Y-m-d H:i:s')
        ];
        $existingKeys[] = $line;
        $imported++;
    }
    saveKeys($keys);
    echo json_encode(['success' => true, 'imported' => $imported, 'skipped' => $skipped]);
    exit;
}

// 删除卡密
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id = $body['id'] ?? '';
    $keys = loadKeys();
    $keys = array_values(array_filter($keys, fn($k) => $k['id'] !== $id));
    saveKeys($keys);
    echo json_encode(['success' => true]);
    exit;
}

// 批量删除卡密
if ($method === 'POST' && ($_GET['action'] ?? '') === 'batch_delete') {
    $body = json_decode(file_get_contents('php://input'), true);
    $ids = $body['ids'] ?? [];
    $keys = loadKeys();
    $keys = array_values(array_filter($keys, fn($k) => !in_array($k['id'], $ids)));
    saveKeys($keys);
    echo json_encode(['success' => true, 'deleted' => count($ids)]);
    exit;
}

// 重置卡密（标记为未使用）
if ($method === 'POST' && ($_GET['action'] ?? '') === 'reset') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id = $body['id'] ?? '';
    $keys = loadKeys();
    foreach ($keys as &$k) {
        if ($k['id'] === $id) {
            $k['used'] = false;
            $k['usedAt'] = null;
            $k['usedIp'] = null;
            break;
        }
    }
    saveKeys($keys);
    echo json_encode(['success' => true]);
    exit;
}

// 添加卡密类型
if ($method === 'POST' && ($_GET['action'] ?? '') === 'add_type') {
    $body = json_decode(file_get_contents('php://input'), true);
    $prefix = trim($body['prefix'] ?? '');
    $name = trim($body['name'] ?? '');
    if (!$prefix || !$name) {
        echo json_encode(['success' => false, 'error' => '前缀和名称不能为空']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9]+$/', $prefix)) {
        echo json_encode(['success' => false, 'error' => '前缀只能包含字母和数字']);
        exit;
    }
    if (strlen($prefix) > 10) {
        echo json_encode(['success' => false, 'error' => '前缀长度不能超过10个字符']);
        exit;
    }
    $types = loadKeyTypes();
    $prefix = strtolower($prefix);
    foreach ($types as $t) {
        if (strtolower($t['prefix']) === $prefix) {
            echo json_encode(['success' => false, 'error' => '该前缀已存在']);
            exit;
        }
    }
    $types[] = ['prefix' => $prefix, 'name' => $name];
    saveKeyTypes($types);
    echo json_encode(['success' => true, 'types' => $types]);
    exit;
}

// 删除卡密类型
if ($method === 'POST' && ($_GET['action'] ?? '') === 'del_type') {
    $body = json_decode(file_get_contents('php://input'), true);
    $prefix = trim($body['prefix'] ?? '');
    if (!$prefix) {
        echo json_encode(['success' => false, 'error' => '前缀不能为空']);
        exit;
    }
    $types = loadKeyTypes();
    if (count($types) <= 1) {
        echo json_encode(['success' => false, 'error' => '至少保留一个类型']);
        exit;
    }
    $prefix = strtolower($prefix);
    $found = false;
    $types = array_values(array_filter($types, function($t) use ($prefix, &$found) {
        if (strtolower($t['prefix']) === $prefix) { $found = true; return false; }
        return true;
    }));
    if (!$found) {
        echo json_encode(['success' => false, 'error' => '类型不存在']);
        exit;
    }
    saveKeyTypes($types);
    echo json_encode(['success' => true, 'types' => $types]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
