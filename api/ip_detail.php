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

function logMessage($msg) {
    $logFile = __DIR__ . '/../data/ip_detail_log.txt';
    $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
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
    logMessage('鉴权失败');
    exit;
}

$ip = $_GET['ip'] ?? '';

if (!$ip || filter_var($ip, FILTER_VALIDATE_IP) === false) {
    echo json_encode(['error' => '无效IP']);
    logMessage('无效IP: ' . $ip);
    exit;
}

$cacheFile = __DIR__ . '/../data/ip_pro_cache.json';
function loadCache() {
    global $cacheFile;
    if (!file_exists($cacheFile)) return [];
    $data = json_decode(@file_get_contents($cacheFile), true);
    return is_array($data) ? $data : [];
}
function saveCache($cache) {
    global $cacheFile;
    @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
}

$cache = loadCache();
if (isset($cache[$ip]['data']) && (time() - ($cache[$ip]['ts'] ?? 0) < 86400)) {
    logMessage('缓存命中: ' . $ip);
    echo json_encode($cache[$ip]['data']);
    exit;
}

function fetchWithRetry($url, $maxRetries = 3, $delaySeconds = 2) {
    for ($i = 0; $i < $maxRetries; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Referer: https://v1.apizero.cn/'
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            logMessage('请求错误(' . ($i+1) . '/' . $maxRetries . '): ' . $url . ' - ' . $err);
        } else {
            logMessage('请求成功(' . ($i+1) . '/' . $maxRetries . '): HTTP ' . $httpCode . ' - ' . $url);
        }

        if ($httpCode === 429) {
            logMessage('触发限流(429)，等待 ' . $delaySeconds . ' 秒后重试');
            sleep($delaySeconds);
            continue;
        }

        if ($result && $httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($result, true);
            if ($data && isset($data['code']) && $data['code'] === 0 && isset($data['data'])) {
                return $data;
            }
        }

        if ($i < $maxRetries - 1) {
            sleep(1);
        }
    }
    return null;
}

function fetchFromBackup($ip) {
    $backups = [
        'https://ip-api.com/json/' . urlencode($ip) . '?lang=zh-CN&fields=status,message,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,isp,org,as,query',
        'https://ip.zxinc.cn/api.php?type=json&ip=' . urlencode($ip),
        'https://ip.useragentinfo.com/json?ip=' . urlencode($ip)
    ];

    foreach ($backups as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$result || $httpCode < 200 || $httpCode >= 300) continue;

        $data = json_decode($result, true);
        if (!$data) continue;

        $mapped = null;

        if (strpos($url, 'ip-api.com') !== false && isset($data['status']) && $data['status'] === 'success') {
            $mapped = [
                'ip' => $data['query'] ?? $ip,
                'country' => $data['country'] ?? '',
                'country_code' => $data['countryCode'] ?? '',
                'province' => $data['regionName'] ?? '',
                'city' => $data['city'] ?? '',
                'district' => $data['district'] ?? '',
                'zip_code' => $data['zip'] ?? '',
                'time_zone' => $data['timezone'] ?? '',
                'isp' => $data['isp'] ?? '',
                'latitude' => $data['lat'] ?? null,
                'longitude' => $data['lon'] ?? null,
                'source' => 'ip-api-backup'
            ];
        } elseif (strpos($url, 'ip.zxinc.cn') !== false && isset($data['code']) && $data['code'] === 200 && isset($data['data'])) {
            $d = $data['data'];
            $mapped = [
                'ip' => $d['ip'] ?? $ip,
                'country' => $d['country'] ?? '',
                'province' => $d['province'] ?? '',
                'city' => $d['city'] ?? '',
                'district' => $d['district'] ?? '',
                'isp' => $d['isp'] ?? '',
                'source' => 'zxinc-backup'
            ];
        } elseif (strpos($url, 'ip.useragentinfo.com') !== false && isset($data['code']) && $data['code'] === 200 && isset($data['data'])) {
            $d = $data['data'];
            $mapped = [
                'ip' => $d['ip'] ?? $ip,
                'country' => $d['country'] ?? '',
                'province' => $d['province'] ?? '',
                'city' => $d['city'] ?? '',
                'district' => $d['district'] ?? '',
                'isp' => $d['isp'] ?? '',
                'source' => 'useragentinfo-backup'
            ];
        }

        if ($mapped) {
            logMessage('备用接口成功: ' . $url);
            return ['code' => 0, 'msg' => '成功', 'data' => $mapped];
        }
    }

    return null;
}

$data = fetchWithRetry("https://v1.apizero.cn/api/ip-pro?ip=" . urlencode($ip), 3, 2);

if (!$data) {
    logMessage('apizero 失败，尝试备用接口: ' . $ip);
    $data = fetchFromBackup($ip);
}

if ($data && isset($data['code']) && $data['code'] === 0) {
    $cache[$ip] = ['data' => $data, 'ts' => time()];
    if (count($cache) > 2000) {
        $cache = array_slice($cache, -1500, null, true);
    }
    saveCache($cache);
    logMessage('查询成功: ' . $ip);
    echo json_encode($data);
    exit;
}

logMessage('全部接口失败: ' . $ip);
echo json_encode(['code' => -1, 'msg' => '查询失败，请稍后重试', 'data' => null]);
?>