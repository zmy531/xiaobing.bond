<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getIP() {
    // 优先从代理头获取真实IP
    if (isset($_SERVER['HTTP_X_REAL_IP'])) return trim($_SERVER['HTTP_X_REAL_IP']);
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        // 取第一个非私有IP
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if ($ip && !preg_match('/^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|127\.|::1$)/', $ip)) {
                return $ip;
            }
        }
        // 如果都是私有IP，取最后一个
        return trim($ips[0]);
    }
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    return $_SERVER['REMOTE_ADDR'];
}

$ip = getIP();
$path = $_GET['path'] ?? $_SERVER['HTTP_REFERER'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$file = __DIR__ . '/../data/visitors.json';
@mkdir(dirname($file), 0755, true);
$visitors = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

// 同一IP访问同一页面 2分钟内不重复记录，不同页面各自独立记录
$shouldLog = true;
foreach ($visitors as $v) {
    if ($v['ip'] === $ip && ($v['path'] ?? '') === $path) {
        $lastTime = strtotime($v['time']);
        if (time() - $lastTime < 120) {
            $shouldLog = false;
            break;
        }
    }
}

if ($shouldLog) {
    $visitor = [
        'id' => uniqid(),
        'ip' => $ip,
        'path' => $path,
        'time' => date('Y-m-d H:i:s'),
        'ua' => $ua
    ];
    array_unshift($visitors, $visitor);
    if (count($visitors) > 1000) $visitors = array_slice($visitors, 0, 1000);
    file_put_contents($file, json_encode($visitors));
}

$count = 0;
$today = date('Y-m-d');
foreach ($visitors as $v) {
    if (strpos($v['time'], $today) === 0) $count++;
}

echo json_encode([
    'success' => true,
    'total' => count($visitors),
    'today' => $count
]);
