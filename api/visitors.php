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
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return '无效IP';

    // 接口1：腾讯IP定位（对中国IP最准确，含运营商信息）
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://qt.gtimg.cn/?r=0.1&ip=" . urlencode($ip));
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            // 腾讯返回格式：v_ipCallBack({"ip":"x.x.x.x","province":"xx","city":"xx","isp":"xx"})
            if (preg_match('/"province"\s*:\s*"([^"]*)".*"city"\s*:\s*"([^"]*)".*"isp"\s*:\s*"([^"]*)"/s', $result, $m)) {
                $loc = trim($m[1] . ' ' . $m[2]);
                $isp = trim($m[3]);
                if ($loc && $loc !== ' ') return $loc . ($isp ? ' ' . $isp : '');
            }
            // 兼容其他返回格式
            if (preg_match('/"city"\s*:\s*"([^"]*)"/', $result, $m) && $m[1]) {
                return $m[1];
            }
        }
    } catch (Exception $e) {}

    // 接口2：太平洋电脑网（国内IP较准确）
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://whois.pconline.com.cn/ipJson.jsp?ip=" . urlencode($ip) . "&json=true");
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            // 尝试GBK转UTF-8
            $utf8Result = mb_convert_encoding($result, 'UTF-8', 'GBK');
            if (!$utf8Result) $utf8Result = $result;
            $data = json_decode($utf8Result, true);
            if ($data) {
                $loc = trim(($data['pro'] ?? '') . ($data['city'] ?? '') . ($data['region'] ?? ''));
                if ($loc) return $loc;
                if (isset($data['addr']) && $data['addr']) return $data['addr'];
            }
        }
    } catch (Exception $e) {}

    // 接口3：ip-api.com（国际IP较准确，支持中文）
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/" . urlencode($ip) . "?lang=zh-CN&fields=status,regionName,city,isp");
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $data = json_decode($result, true);
            if ($data && $data['status'] === 'success') {
                $loc = trim(($data['regionName'] ?? '') . ' ' . ($data['city'] ?? ''));
                $isp = $data['isp'] ?? '';
                if ($loc) return $loc . ($isp ? ' ' . $isp : '');
            }
        }
    } catch (Exception $e) {}

    // 接口4：ip.sb（轻量备用）
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.ip.sb/geoip/" . urlencode($ip));
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $data = json_decode($result, true);
            if ($data) {
                $loc = trim(($data['region'] ?? '') . ' ' . ($data['city'] ?? ''));
                if ($loc) return $loc;
            }
        }
    } catch (Exception $e) {}

    return '未知地区';
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

$file = __DIR__ . '/../data/visitors.json';
$visitors = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$uri = $_SERVER['REQUEST_URI'];
$id = null;
if (isset($_GET['id'])) {
    $id = $_GET['id'];
} elseif (preg_match('/\/api\/visitors(?:\.php)?\/([^\/]+)$/', $uri, $matches)) {
    $id = $matches[1];
}

if ($method === 'GET') {
    if ($id) {
        $found = array_filter($visitors, fn($v) => ($v['id'] ?? '') === $id);
        if ($found) {
            $item = reset($found);
            if (!isset($item['location']) || $item['location'] === '') {
                $item['location'] = getIPLocation($item['ip'] ?? '');
            }
            echo json_encode($item);
        } else {
            echo json_encode(null);
        }
    } else {
        // 优化：支持分页参数，只查询当前页的IP位置
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $pageSize = isset($_GET['pageSize']) ? min(100, intval($_GET['pageSize'])) : 20;
        $total = count($visitors);
        $offset = ($page - 1) * $pageSize;
        $pageData = array_slice($visitors, $offset, $pageSize);
        
        // 只对当前页数据查询IP位置，且缓存结果
        foreach ($pageData as &$v) {
            if (!isset($v['location']) || $v['location'] === '') {
                $v['location'] = getIPLocation($v['ip'] ?? '');
            }
        }
        
        // 同时把缓存的位置写回原文件（异步优化，不影响响应速度）
        $needSave = false;
        foreach ($visitors as &$vv) {
            foreach ($pageData as $pd) {
                if (($vv['id'] ?? '') === ($pd['id'] ?? '') && isset($pd['location'])) {
                    $vv['location'] = $pd['location'];
                    $needSave = true;
                    break;
                }
            }
        }
        if ($needSave) {
            @file_put_contents($file, json_encode($visitors, JSON_UNESCAPED_UNICODE));
        }
        
        echo json_encode(['data' => $pageData, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }
} elseif ($method === 'DELETE') {
    if ($id) {
        $visitors = array_filter($visitors, fn($v) => ($v['id'] ?? '') !== $id);
        file_put_contents($file, json_encode(array_values($visitors)));
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ID']);
    }
} elseif ($method === 'PUT') {
    if ($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $note = $data['note'] ?? '';
        foreach ($visitors as &$v) {
            if (($v['id'] ?? '') === $id) {
                $v['note'] = $note;
                break;
            }
        }
        file_put_contents($file, json_encode($visitors));
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ID']);
    }
}
?>