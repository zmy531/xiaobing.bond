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

// IP位置缓存文件
$ipCacheFile = __DIR__ . '/../data/ip_location_cache.json';
function loadIPCache() {
    global $ipCacheFile;
    if (!file_exists($ipCacheFile)) return [];
    $data = json_decode(@file_get_contents($ipCacheFile), true);
    return is_array($data) ? $data : [];
}
function saveIPCache($cache) {
    global $ipCacheFile;
    @file_put_contents($ipCacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
}

function getIPLocation($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') return '本地网络';
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return '无效IP';

    // 优先从缓存读取（缓存7天）
    global $ipCacheFile;
    static $ipCache = null;
    if ($ipCache === null) $ipCache = loadIPCache();
    if (isset($ipCache[$ip]['location']) && $ipCache[$ip]['location']
        && (time() - ($ipCache[$ip]['ts'] ?? 0) < 604800)) {
        return $ipCache[$ip]['location'];
    }

    $location = '';
    $detail = [];

    // 接口0：ip9.com.cn（街道级+经纬度+邮编，最全面）
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://ip9.com.cn/get?ip=" . urlencode($ip));
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $data = json_decode($result, true);
            if ($data && ($data['ret'] ?? 0) === 200 && isset($data['data'])) {
                $d = $data['data'];
                $parts = [];
                if (!empty($d['country'])) $parts[] = $d['country'];
                if (!empty($d['prov'])) $parts[] = $d['prov'];
                if (!empty($d['city'])) $parts[] = $d['city'];
                if (!empty($d['area'])) $parts[] = $d['area'];
                if (!empty($d['isp'])) $parts[] = $d['isp'];
                $location = implode(' ', array_filter($parts));
                $detail = $d;
            }
        }
    } catch (Exception $e) {}

    // 接口1：ip.zxinc.cn（国内街道级，最精准）
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://ip.zxinc.cn/ipapi/?ip=" . urlencode($ip) . "&type=json");
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $data = json_decode($result, true);
            if ($data && ($data['code'] ?? 1) === 0 && isset($data['data'])) {
                $d = $data['data'];
                $parts = [];
                if (!empty($d['country'])) $parts[] = $d['country'];
                if (!empty($d['province'])) $parts[] = $d['province'];
                if (!empty($d['city'])) $parts[] = $d['city'];
                if (!empty($d['district'])) $parts[] = $d['district'];
                if (!empty($d['isp'])) $parts[] = $d['isp'];
                $location = implode(' ', array_filter($parts));
                $detail = $d;
            }
        }
    } catch (Exception $e) {}

    // 接口2：ip.useragentinfo.com（街道级，含经纬度）
    if (!$location) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://ip.useragentinfo.com/json?ip=" . urlencode($ip));
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result) {
                $data = json_decode($result, true);
                if ($data && ($data['code'] ?? 1) === 200 && isset($data['data'])) {
                    $d = $data['data'];
                    $parts = [];
                    if (!empty($d['country'])) $parts[] = $d['country'];
                    if (!empty($d['province'])) $parts[] = $d['province'];
                    if (!empty($d['city'])) $parts[] = $d['city'];
                    if (!empty($d['district'])) $parts[] = $d['district'];
                    if (!empty($d['isp'])) $parts[] = $d['isp'];
                    $location = implode(' ', array_filter($parts));
                    $detail = $d;
                }
            }
        } catch (Exception $e) {}
    }

    // 接口3：ip-api.com（带district区县字段+经纬度，支持中文）
    if (!$location) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/" . urlencode($ip) . "?lang=zh-CN&fields=status,country,regionName,city,district,zip,lat,lon,isp");
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result) {
                $data = json_decode($result, true);
                if ($data && $data['status'] === 'success') {
                    $parts = [];
                    if (!empty($data['country'])) $parts[] = $data['country'];
                    if (!empty($data['regionName'])) $parts[] = $data['regionName'];
                    if (!empty($data['city'])) $parts[] = $data['city'];
                    if (!empty($data['district']) && $data['district'] !== $data['city']) $parts[] = $data['district'];
                    if (!empty($data['isp'])) $parts[] = $data['isp'];
                    $location = implode(' ', array_filter($parts));
                    $detail = $data;
                }
            }
        } catch (Exception $e) {}
    }

    // 接口4：ipinfo.io（含postal邮政编码，国际IP较准）
    if (!$location) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://ipinfo.io/" . urlencode($ip) . "/json");
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result) {
                $data = json_decode($result, true);
                if ($data && !isset($data['error'])) {
                    $parts = [];
                    if (!empty($data['country'])) $parts[] = $data['country'];
                    if (!empty($data['region'])) $parts[] = $data['region'];
                    if (!empty($data['city'])) $parts[] = $data['city'];
                    if (!empty($data['postal'])) $parts[] = $data['postal'];
                    if (!empty($data['org'])) $parts[] = $data['org'];
                    $location = implode(' ', array_filter($parts));
                    // 解析 ipinfo.io 的 loc 字段 "lat,lng"
                    if (isset($data['loc']) && strpos($data['loc'], ',') !== false) {
                        list($lat, $lng) = explode(',', $data['loc'], 2);
                        $data['lat'] = trim($lat);
                        $data['lng'] = trim($lng);
                    }
                    $detail = $data;
                }
            }
        } catch (Exception $e) {}
    }

    // 接口5：腾讯IP定位（城市级备用，含运营商）
    if (!$location) {
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
                if (preg_match('/"province"\s*:\s*"([^"]*)".*"city"\s*:\s*"([^"]*)".*"isp"\s*:\s*"([^"]*)"/s', $result, $m)) {
                    $loc = trim($m[1] . ' ' . $m[2]);
                    $isp = trim($m[3]);
                    if ($loc && $loc !== ' ') $location = $loc . ($isp ? ' ' . $isp : '');
                }
            }
        } catch (Exception $e) {}
    }

    // 接口6：太平洋电脑网（城市级备用）
    if (!$location) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://whois.pconline.com.cn/ipJson.jsp?ip=" . urlencode($ip) . "&json=true");
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result) {
                $utf8Result = mb_convert_encoding($result, 'UTF-8', 'GBK');
                if (!$utf8Result) $utf8Result = $result;
                $data = json_decode($utf8Result, true);
                if ($data) {
                    $loc = trim(($data['pro'] ?? '') . ($data['city'] ?? '') . ($data['region'] ?? ''));
                    if ($loc) $location = $loc;
                    elseif (isset($data['addr']) && $data['addr']) $location = $data['addr'];
                }
            }
        } catch (Exception $e) {}
    }

    // 接口7：apizero.cn（街道级+风险评估+经纬度，最详细）
    if (!$location) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://v1.apizero.cn/api/ip-pro?ip=" . urlencode($ip));
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result) {
                $data = json_decode($result, true);
                if ($data && ($data['code'] ?? 1) === 0 && isset($data['data'])) {
                    $d = $data['data'];
                    $parts = [];
                    if (!empty($d['country'])) $parts[] = $d['country'];
                    if (!empty($d['province'])) $parts[] = $d['province'];
                    if (!empty($d['city'])) $parts[] = $d['city'];
                    if (!empty($d['district'])) $parts[] = $d['district'];
                    if (!empty($d['street'])) $parts[] = $d['street'];
                    if (!empty($d['isp'])) $parts[] = $d['isp'];
                    $location = implode(' ', array_filter($parts));
                    $detail = $d;
                }
            }
        } catch (Exception $e) {}
    }

    if (!$location) $location = '未知地区';

    // 写入缓存
    $ipCache[$ip] = ['location' => $location, 'ts' => time(), 'detail' => $detail];
    // 限制缓存数量，最多5000条
    if (count($ipCache) > 5000) {
        $ipCache = array_slice($ipCache, -4000, null, true);
    }
    saveIPCache($ipCache);

    return $location;
}

function getIPLatLng($ip) {
    static $cache = null;
    if ($cache === null) $cache = loadIPCache();
    if (isset($cache[$ip]['detail'])) {
        $d = $cache[$ip]['detail'];
        $lat = $d['lat'] ?? null;
        $lng = $d['lng'] ?? $d['lon'] ?? null;
        if (!$lat && isset($d['loc']) && strpos($d['loc'], ',') !== false) {
            $ll = explode(',', $d['loc'], 2);
            $lat = trim($ll[0]);
            $lng = trim($ll[1]);
        }
        if ($lat && $lng) return ['lat' => (string)$lat, 'lng' => (string)$lng];
    }
    return null;
}

function parseUA($ua) {
    if (!$ua) return ['browser' => '未知', 'os' => '未知', 'device' => '未知', 'brand' => '', 'isBot' => false, 'isMobile' => false];
    $ua = trim($ua);
    $result = [
        'browser' => '未知浏览器', 'browserVer' => '',
        'os' => '未知系统', 'osVer' => '',
        'device' => '桌面电脑', 'brand' => '', 'model' => '',
        'isBot' => false, 'isMobile' => false,
        'raw' => $ua
    ];

    // ===== 1. 爬虫检测 =====
    $botPatterns = ['bot', 'spider', 'crawler', 'slurp', 'wget', 'curl', 'python-requests', 'go-http-client', 'java/', 'libwww', 'httpclient', 'scrapy', 'phantomjs', 'headless', 'googlebot', 'bingbot', 'baiduspider', 'bytespider', 'yandexbot', 'duckduckbot', 'semrush', 'ahrefs', 'mj12bot', 'dotbot', 'rogerbot', 'exabot', 'grapeshot', 'python-urllib', 'okhttp', 'httpurlconnection', 'dalvik', 'com.apple.WebKit'];
    foreach ($botPatterns as $bp) {
        if (stripos($ua, $bp) !== false) {
            $result['isBot'] = true;
            $result['device'] = '爬虫';
            $result['browser'] = '爬虫/机器人';
            return $result;
        }
    }

    // ===== 2. 移动端综合判断 =====
    $isMobile = false;
    $mobileIndicators = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'Windows Phone', 'webOS', 'BlackBerry', 'Symbian', 'Kindle', 'Silk/', 'Opera Mini', 'Opera Mobi', 'IEMobile', 'WPDesktop', 'Fennec', 'Maemo', 'Midori', 'Dolfin', 'Dolphin', 'Skyfire', 'Tizen', 'Bada', 'MeeGo', 'Series60', 'Series40', 'phone', 'Mobile/', 'Mobile Safari', 'AndroidMobile', 'CFNetwork'];
    foreach ($mobileIndicators as $mi) {
        if (stripos($ua, $mi) !== false) { $isMobile = true; break; }
    }
    $phoneBrands = ['XiaoMi', 'Mi ', 'MI ', 'Redmi', 'HUAWEI', 'HONOR', 'OPPO', 'vivo', 'OnePlus', 'MEIZU', 'Nubia', 'ZTE', 'Lenovo', 'Smartisan', 'Realme', 'Samsung', 'SM-', 'Pixel', 'Nexus', 'LG-', 'HTC', 'Moto', 'motorola', 'OnePlus', 'INFINIX', 'TECNO', 'itel', 'LeTV', 'Letv', 'Coolpad', 'Gionee', 'BBK', 'Alcatel', 'Nokia', 'ASUS', 'ROG', 'ZTE', 'nubia', 'RedMagic', 'Blackshark', '黑鲨', '魅族', '锤子', '坚果'];
    foreach ($phoneBrands as $pb) {
        if (stripos($ua, $pb) !== false) { $isMobile = true; break; }
    }
    $result['isMobile'] = $isMobile;

    // ===== 3. 手机品牌与型号识别 =====
    $brand = '';
    $model = '';
    
    // 小米/红米系列
    if (preg_match('/\bXiaoMi\b/i', $ua) || preg_match('/\bMi\s*(\d+[A-Za-z]*)\b/i', $ua, $m) || preg_match('/\bMI\s+NOTE/i', $ua) || preg_match('/\bMIX\s*(\d*)\b/i', $ua) || preg_match('/\bMi\s+[A-Z]/i', $ua)) {
        $brand = '小米';
        if (preg_match('/\bMi\s+(\d+[A-Za-z]*)/i', $ua, $m)) $model = 'Mi ' . $m[1];
        elseif (preg_match('/\bMIX\s*(\d*)/i', $ua, $m)) $model = 'MIX' . ($m[1] ? ' ' . $m[1] : '');
    } elseif (preg_match('/\bRedmi\b/i', $ua) || preg_match('/\bRed[_-]?Mi\b/i', $ua) || preg_match('/\bK\d\d\b/i', $ua) || preg_match('/\bNote\s+\d+/i', $ua)) {
        $brand = '红米';
        if (preg_match('/\bRedmi\s+(\S+)/i', $ua, $m)) $model = 'Redmi ' . $m[1];
        elseif (preg_match('/\bK(\d+)/i', $ua, $m)) $model = 'K' . $m[1];
    }
    // POCO 系列
    elseif (preg_match('/\bPOCO\b/i', $ua) || preg_match('/\bPoco\s+/i', $ua)) {
        $brand = 'POCO';
        if (preg_match('/\bPOCO\s+(\S+)/i', $ua, $m)) $model = $m[1];
    }
    // 华为系列
    elseif (preg_match('/\bHUAWEI\b/i', $ua) || preg_match('/\bMate\s*(\d+[A-Za-z]*)\b/i', $ua) || preg_match('/\bP\s*(\d+[A-Za-z]*)\b/i', $ua) || preg_match('/\bNova\s*(\d+)/i', $ua) || preg_match('/\bHarmonyOS\b/i', $ua) || preg_match('/\bHMSCore\b/i', $ua)) {
        $brand = '华为';
        if (preg_match('/\bMate\s+(\d+[A-Za-z]*)/i', $ua, $m)) $model = 'Mate ' . $m[1];
        elseif (preg_match('/\bP\s+(\d+[A-Za-z]*)/i', $ua, $m)) $model = 'P' . $m[1];
        elseif (preg_match('/\bNova\s+(\d+[A-Za-z]*)/i', $ua, $m)) $model = 'Nova ' . $m[1];
    }
    // 荣耀系列
    elseif (preg_match('/\bHONOR\b/i', $ua) || preg_match('/\bHonor\b/i', $ua) || preg_match('/\bV\d\d\b/i', $ua)) {
        $brand = '荣耀';
        if (preg_match('/\bHonor\s+(\S+)/i', $ua, $m)) $model = $m[1];
        elseif (preg_match('/\bHONOR\s+(\S+)/i', $ua, $m)) $model = $m[1];
    }
    // OPPO 系列
    elseif (preg_match('/\bOPPO\b/i', $ua) || preg_match('/\bReno\s*(\d+)/i', $ua) || preg_match('/\bFind\s+X/i', $ua) || preg_match('/\bColorOS\b/i', $ua)) {
        $brand = 'OPPO';
        if (preg_match('/\bReno\s+(\d+[A-Za-z]*)/i', $ua, $m)) $model = 'Reno ' . $m[1];
        elseif (preg_match('/\bFind\s+X(\w*)/i', $ua, $m)) $model = 'Find X' . $m[1];
    }
    // realme 真我
    elseif (preg_match('/\bRealme\b/i', $ua) || preg_match('/\brealme\b/i', $ua) || preg_match('/\bRMX\d+/i', $ua)) {
        $brand = '真我';
        if (preg_match('/\b[rR]ealme\s+(\S+)/i', $ua, $m)) $model = $m[1];
    }
    // OnePlus 一加
    elseif (preg_match('/\bOnePlus\b/i', $ua) || preg_match('/\bONEPLUS\b/i', $ua) || preg_match('/\bGM\d+/i', $ua)) {
        $brand = '一加';
        if (preg_match('/\bOnePlus\s+(\d+[A-Za-z]*)/i', $ua, $m)) $model = $m[1];
    }
    // vivo 系列
    elseif (preg_match('/\bvivo\b/i', $ua) || preg_match('/\bX\d\d\b/i', $ua) || preg_match('/\bS\d\d\b/i', $ua) || preg_match('/\biQOO\b/i', $ua)) {
        $brand = 'vivo';
        if (preg_match('/\biQOO\s*(\d*)/i', $ua, $m)) {
            $brand = 'iQOO';
            $model = $m[1] ? 'iQOO ' . $m[1] : 'iQOO';
        }
        elseif (preg_match('/\bvivo\s+(\S+)/i', $ua, $m)) $model = $m[1];
    }
    // 三星系列
    elseif (preg_match('/\bSM-/i', $ua) || preg_match('/\bSamsung/i', $ua) || preg_match('/\bGalaxy\b/i', $ua)) {
        $brand = '三星';
        if (preg_match('/\bSM-(\S+)/i', $ua, $m)) $model = $m[1];
        elseif (preg_match('/\bGa?laxy\s+(\S+)/i', $ua, $m)) $model = 'Galaxy ' . $m[1];
    }
    // 谷歌 Pixel
    elseif (preg_match('/\bPixel\b/i', $ua) || preg_match('/\bNexus\b/i', $ua)) {
        $brand = '谷歌';
        if (preg_match('/\bPixel\s+(\d+[A-Za-z]*)/i', $ua, $m)) $model = 'Pixel ' . $m[1];
        elseif (preg_match('/\bNexus\s+(\S+)/i', $ua, $m)) $model = 'Nexus ' . $m[1];
    }
    // 魅族
    elseif (preg_match('/\bMEIZU\b/i', $ua) || preg_match('/\bMeizu\b/i', $ua) || preg_match('/\bFlyme\b/i', $ua)) {
        $brand = '魅族';
        if (preg_match('/\bMeizu\s+(\S+)/i', $ua, $m)) $model = $m[1];
    }
    // 摩托罗拉
    elseif (preg_match('/\bMoto\b/i', $ua) || preg_match('/\bmotorola\b/i', $ua)) {
        $brand = '摩托罗拉';
        if (preg_match('/\bMoto\s+(\S+)/i', $ua, $m)) $model = $m[1];
    }
    // 联想
    elseif (preg_match('/\bLenovo\b/i', $ua)) {
        $brand = '联想';
        if (preg_match('/\bLenovo\s+(\S+)/i', $ua, $m)) $model = $m[1];
    }
    // 中兴/努比亚
    elseif (preg_match('/\bZTE\b/i', $ua) || preg_match('/\bNubia\b/i', $ua) || preg_match('/\bRedMagic\b/i', $ua) || preg_match('/\b红魔/i', $ua)) {
        $brand = '中兴';
        if (preg_match('/\bNubia\s+(\S+)/i', $ua, $m)) $model = '努比亚 ' . $m[1];
        elseif (preg_match('/\bRedMagic\s*(\S*)/i', $ua, $m)) { $brand = '红魔'; $model = $m[1] ? $m[1] : ''; }
    }
    // 华硕/ROG
    elseif (preg_match('/\bASUS\b/i', $ua) || preg_match('/\bROG\b/i', $ua)) {
        $brand = '华硕';
        if (preg_match('/\bROG\s+(\S+)/i', $ua, $m)) { $brand = 'ROG'; $model = $m[1]; }
        elseif (preg_match('/\bASUS\s+(\S+)/i', $ua, $m)) $model = $m[1];
    }
    // 黑鲨
    elseif (preg_match('/\bBlackshark\b/i', $ua) || preg_match('/黑鲨/i', $ua)) {
        $brand = '黑鲨';
    }
    // 锤子/坚果
    elseif (preg_match('/\bSmartisan\b/i', $ua) || preg_match('/锤子/i', $ua) || preg_match('/坚果/i', $ua)) {
        $brand = '锤子';
    }
    // LG
    elseif (preg_match('/\bLG[-_]/i', $ua) || preg_match('/\bLG\s/i', $ua)) {
        $brand = 'LG';
        if (preg_match('/\bLG[-_](\S+)/i', $ua, $m)) $model = $m[1];
    }
    // HTC
    elseif (preg_match('/\bHTC\b/i', $ua)) {
        $brand = 'HTC';
    }
    // 索尼
    elseif (preg_match('/\bSony\b/i', $ua) || preg_match('/\bXperia\b/i', $ua)) {
        $brand = '索尼';
        if (preg_match('/\bXperia\s+(\S+)/i', $ua, $m)) $model = 'Xperia ' . $m[1];
    }
    // 诺基亚
    elseif (preg_match('/\bNokia\b/i', $ua)) {
        $brand = '诺基亚';
    }

    // ===== 3.5 设备代号精确识别映射表 =====
    if (!$model) {
        $modelMap = [
            // 三星 SM-XXXX
            '/\bSM-G991[BW0-9]*\b/i' => 'Galaxy S21',
            '/\bSM-G996[BW0-9]*\b/i' => 'Galaxy S21+',
            '/\bSM-G998[BW0-9]*\b/i' => 'Galaxy S21 Ultra',
            '/\bSM-G960[0-9]*\b/i' => 'Galaxy S9',
            '/\bSM-G965[0-9]*\b/i' => 'Galaxy S9+',
            '/\bSM-G970[0-9]*\b/i' => 'Galaxy S10e',
            '/\bSM-G973[0-9]*\b/i' => 'Galaxy S10',
            '/\bSM-G975[0-9]*\b/i' => 'Galaxy S10+',
            '/\bSM-G980[0-9]*\b/i' => 'Galaxy S20',
            '/\bSM-G986[0-9]*\b/i' => 'Galaxy S20+',
            '/\bSM-G990[0-9]*\b/i' => 'Galaxy S20 FE',
            '/\bSM-G781[0-9]*\b/i' => 'Galaxy S20 FE',
            '/\bSM-S901[B0-9]*\b/i' => 'Galaxy S22',
            '/\bSM-S906[B0-9]*\b/i' => 'Galaxy S22+',
            '/\bSM-S908[B0-9]*\b/i' => 'Galaxy S22 Ultra',
            '/\bSM-S911[B0-9]*\b/i' => 'Galaxy S23',
            '/\bSM-S916[B0-9]*\b/i' => 'Galaxy S23+',
            '/\bSM-S918[B0-9]*\b/i' => 'Galaxy S23 Ultra',
            '/\bSM-S921[B0-9]*\b/i' => 'Galaxy S24',
            '/\bSM-S926[B0-9]*\b/i' => 'Galaxy S24+',
            '/\bSM-S928[B0-9]*\b/i' => 'Galaxy S24 Ultra',
            '/\bSM-N970[0-9]*\b/i' => 'Galaxy Note10',
            '/\bSM-N975[0-9]*\b/i' => 'Galaxy Note10+',
            '/\bSM-N986[0-9]*\b/i' => 'Galaxy Note20 Ultra',
            '/\bSM-N981[0-9]*\b/i' => 'Galaxy Note20',
            '/\bSM-N960[0-9]*\b/i' => 'Galaxy Note9',
            '/\bSM-F916[B0-9]*\b/i' => 'Galaxy Z Fold3',
            '/\bSM-F936[B0-9]*\b/i' => 'Galaxy Z Fold4',
            '/\bSM-F946[B0-9]*\b/i' => 'Galaxy Z Fold5',
            '/\bSM-F956[B0-9]*\b/i' => 'Galaxy Z Fold6',
            '/\bSM-F711[B0-9]*\b/i' => 'Galaxy Z Flip3',
            '/\bSM-F721[B0-9]*\b/i' => 'Galaxy Z Flip4',
            '/\bSM-F731[B0-9]*\b/i' => 'Galaxy Z Flip5',
            '/\bSM-F741[B0-9]*\b/i' => 'Galaxy Z Flip6',
            '/\bSM-A516[0-9]*\b/i' => 'Galaxy A51 5G',
            '/\bSM-A525[0-9]*\b/i' => 'Galaxy A52 5G',
            '/\bSM-A536[0-9]*\b/i' => 'Galaxy A53 5G',
            '/\bSM-A546[0-9]*\b/i' => 'Galaxy A54 5G',
            '/\bSM-A556[0-9]*\b/i' => 'Galaxy A55 5G',
            '/\bSM-A715[0-9]*\b/i' => 'Galaxy A71',
            '/\bSM-A725[0-9]*\b/i' => 'Galaxy A72',
            '/\bSM-A736[0-9]*\b/i' => 'Galaxy A73 5G',
            '/\bSM-A145[0-9]*\b/i' => 'Galaxy A14',
            '/\bSM-A155[0-9]*\b/i' => 'Galaxy A15',
            '/\bSM-A325[0-9]*\b/i' => 'Galaxy A32',
            '/\bSM-A346[0-9]*\b/i' => 'Galaxy A34 5G',
            '/\bSM-A356[0-9]*\b/i' => 'Galaxy A35 5G',
            '/\bSM-M315[0-9]*\b/i' => 'Galaxy M31',
            '/\bSM-M325[0-9]*\b/i' => 'Galaxy M32',
            '/\bSM-M336[0-9]*\b/i' => 'Galaxy M33',
            '/\bSM-M515[0-9]*\b/i' => 'Galaxy M51',
            '/\bSM-M526[0-9]*\b/i' => 'Galaxy M52',
            '/\bSM-M536[0-9]*\b/i' => 'Galaxy M53',
            // 华为
            '/\bHMA-[A0-9]+\b/i' => 'Mate 20',
            '/\bLYA-[A0-9]+\b/i' => 'Mate 20 Pro',
            '/\bTAS-[A0-9]+\b/i' => 'Mate 30',
            '/\bANA-[A0-9]+\b/i' => 'P40',
            '/\bELS-[A0-9]+\b/i' => 'P40 Pro',
            '/\bNOH-[A0-9]+\b/i' => 'Mate 40 Pro',
            '/\bOCE-[A0-9]+\b/i' => 'Mate 40E',
            '/\bTET-[A0-9]+\b/i' => 'Mate X2',
            '/\bALN-[A0-9]+\b/i' => 'Mate 50',
            '/\bDCO-[A0-9]+\b/i' => 'Mate 50 Pro',
            '/\bCET-[A0-9]+\b/i' => 'P60',
            '/\bMNA-[A0-9]+\b/i' => 'nova 2 Plus',
            '/\bBAC-[A0-9]+\b/i' => 'nova 2',
            '/\bANE-[A0-9]+\b/i' => 'P20',
            '/\bCLT-[A0-9]+\b/i' => 'P20 Pro',
            '/\bHW-[A-Z0-9]+\b/i' => '华为手机',
            // 小米/红米 (build 编号)
            '/\b220112[0-9]*[GC]\b/i' => 'Xiaomi 12',
            '/\b221013[0-9]*[GC]\b/i' => 'Xiaomi 12T',
            '/\b221113[0-9]*[GC]\b/i' => 'Xiaomi 13',
            '/\b23127PN0CC\b/i' => 'Xiaomi 14',
            '/\b24129PN0CC\b/i' => 'Xiaomi 15',
            '/\b23127PN0DC\b/i' => 'Xiaomi 14 Pro',
            '/\b23013RK[0-9]*C\b/i' => 'Redmi K60 Pro',
            '/\b23090RA98C\b/i' => 'Redmi K60 Ultra',
            '/\b23113RKC[0-9]*C\b/i' => 'Redmi K70',
            '/\b23117RK[0-9]*C\b/i' => 'Redmi K70 Pro',
            '/\b23090RA98G\b/i' => 'Redmi Note 12 Pro',
            '/\b23021RAA2[GY]\b/i' => 'Redmi Note 12',
            '/\b23106RN0[BD]C\b/i' => 'Redmi Note 13',
            '/\b23129RAA[0-9]*[GC]\b/i' => 'Redmi Note 13 Pro',
            '/\b24015RK[0-9]*[GC]\b/i' => 'Redmi Note 13 Pro+',
            // OPPO (CPH-XXXX 或 PNZ110 格式)
            '/\bCPH2127\b/i' => 'Reno3',
            '/\bCPH2145\b/i' => 'Reno3 Pro',
            '/\bCPH2207\b/i' => 'Reno4',
            '/\bCPH2109\b/i' => 'Reno4 Pro',
            '/\bCPH2249\b/i' => 'Reno5',
            '/\bCPH2269\b/i' => 'Reno6',
            '/\bCPH2331\b/i' => 'Reno7',
            '/\bCPH2399\b/i' => 'Reno8',
            '/\bCPH2423\b/i' => 'Reno8 Pro',
            '/\bCPH2601\b/i' => 'Reno10 Pro',
            '/\bPJZ110\b/i' => 'Find X6 Pro',
            '/\bPHB110\b/i' => 'Find X6',
            '/\bPHQ001\b/i' => 'Find X5 Pro',
            '/\bPGT210\b/i' => 'Find X3 Pro',
            '/\bCPH2581\b/i' => 'Find X7 Ultra',
            '/\bPHM110\b/i' => 'Find X7',
            '/\bPERM00\b/i' => 'OPPO Ace2',
            '/\bPCKM00\b/i' => 'OPPO K9 Pro',
            '/\bPJC[0-9]+/i' => 'OPPO K系列',
            // vivo (VXXXXA 格式)
            '/\bV2130A\b/i' => 'iQOO 8',
            '/\bV2227A\b/i' => 'iQOO 11',
            '/\bV2241A\b/i' => 'iQOO Neo8',
            '/\bV2254A\b/i' => 'vivo S17',
            '/\bV2284A\b/i' => 'vivo X90',
            '/\bV2301A\b/i' => 'vivo X100',
            '/\bV2309A\b/i' => 'vivo X100 Pro',
            '/\bV2324A\b/i' => 'vivo S18',
            '/\bV2366DA\b/i' => 'vivo X100 Ultra',
            '/\bV2403A\b/i' => 'vivo X200',
            '/\bV2302A\b/i' => 'iQOO Neo9',
            '/\bV2356A\b/i' => 'vivo S19',
            '/\bV2055A\b/i' => 'vivo S12',
            '/\bV2105A\b/i' => 'vivo X80',
            '/\bV2171A\b/i' => 'vivo X80 Pro',
            '/\bV2011A\b/i' => 'iQOO 7',
            '/\bV2072A\b/i' => 'iQOO Neo5',
            '/\bV2113A\b/i' => 'iQOO Neo6',
            // 一加 (IN2020、CPH2423 等)
            '/\bIN2020\b/i' => 'OnePlus 8',
            '/\bIN2023\b/i' => 'OnePlus 8 Pro',
            '/\bLE2110\b/i' => 'OnePlus 9',
            '/\bLE2123\b/i' => 'OnePlus 9 Pro',
            '/\bNE2210\b/i' => 'OnePlus 10 Pro',
            '/\bCPH2447\b/i' => 'OnePlus 11',
            '/\bCPH2581\b/i' => 'OnePlus 12',
            '/\bPHB110\b/i' => 'OnePlus 11',
            // 谷歌 Pixel
            '/\bPixel\s*([0-9]+[A-Za-z]*)/i' => 'Pixel $1',
            '/\bPixel\s*Fold/i' => 'Pixel Fold',
            '/\bNexus\s*([0-9]+)/i' => 'Nexus $1',
            // 魅族 (需要完整型号，避免误匹配版本号)
            '/\bMeizu\s+16th\b/i' => '魅族 16th',
            '/\bMeizu\s+16s\b/i' => '魅族 16s',
            '/\bMeizu\s+16T\b/i' => '魅族 16T',
            '/\bMeizu\s+17\b/i' => '魅族 17',
            '/\bMeizu\s+18s\b/i' => '魅族 18s',
            '/\bMeizu\s+20\b/i' => '魅族 20',
            '/\bMeizu\s+21\b/i' => '魅族 21',
            '/\bM1852\b/i' => '魅族 18',
            '/\bM2182\b/i' => '魅族 20',
            '/\bM381Q\b/i' => '魅族 21',
            // 索尼
            '/\bXperia\s*1\s*(IV|V|VI)?\b/i' => 'Xperia 1 $1',
            '/\bXperia\s*5\s*(IV|V|VI)?\b/i' => 'Xperia 5 $1',
            '/\bXperia\s*10\s*(IV|V|VI)?\b/i' => 'Xperia 10 $1',
            // 摩托罗拉
            '/\bmoto\s*g\s*(\d+)/i' => 'Moto G$1',
            '/\bmoto\s*edge\b/i' => 'Moto Edge',
            // iPad
            '/\biPad8,[0-9]+\b/i' => 'iPad 8',
            '/\biPad9,[0-9]+\b/i' => 'iPad 9',
            '/\biPad10,[0-9]+\b/i' => 'iPad 10',
            '/\biPad13,[0-9]+\b/i' => 'iPad Air 5',
            '/\biPad14,[0-9]+\b/i' => 'iPad Pro 11',
            '/\biPadPro/i' => 'iPad Pro',
            '/\biPadMini/i' => 'iPad mini',
            // iPhone (从 UA 里识别)
            '/\biPhone15,[0-9]+\b/i' => 'iPhone 15',
            '/\biPhone16,[0-9]+\b/i' => 'iPhone 16',
            '/\biPhone14,[0-9]+\b/i' => 'iPhone 14',
            '/\biPhone13,[0-9]+\b/i' => 'iPhone 13',
            '/\biPhone12,[0-9]+\b/i' => 'iPhone 12',
            '/\biPhone11,[0-9]+\b/i' => 'iPhone 11',
            '/\biPhone10,[0-9]+\b/i' => 'iPhone X',
            '/\biPhone9,[0-9]+\b/i' => 'iPhone 8',
            '/\biPhone7,[0-9]+\b/i' => 'iPhone 6',
        ];
        foreach ($modelMap as $pattern => $name) {
            if (preg_match($pattern, $ua, $m)) {
                $model = preg_replace('/\$\d+/', isset($m[1]) ? $m[1] : '', $name);
                // 通过代号也能确定品牌
                if (!$brand) {
                    if (stripos($pattern, 'SM-') !== false || stripos($pattern, 'Galaxy') !== false) $brand = '三星';
                    elseif (stripos($pattern, 'HW-') !== false || preg_match('/^[A-Z]{3}-/', $m[0] ?? '')) $brand = '华为';
                    elseif (stripos($pattern, 'CPH') !== false || stripos($pattern, 'PJZ') !== false || stripos($pattern, 'PHB') !== false || stripos($pattern, 'PHQ') !== false || stripos($pattern, 'PGT') !== false) $brand = 'OPPO';
                    elseif (stripos($pattern, 'V2') !== false && preg_match('/^V\d/', $m[0] ?? '')) $brand = 'vivo';
                    elseif (stripos($pattern, 'IN20') !== false || stripos($pattern, 'LE21') !== false || stripos($pattern, 'NE22') !== false) $brand = '一加';
                    elseif (stripos($pattern, 'Pixel') !== false || stripos($pattern, 'Nexus') !== false) $brand = '谷歌';
                    elseif (stripos($pattern, 'iPad') !== false || stripos($pattern, 'iPhone') !== false) $brand = '苹果';
                    elseif (stripos($pattern, '魅族') !== false) $brand = '魅族';
                }
                break;
            }
        }
    }

    $result['brand'] = $brand;
    $result['model'] = $model;

    // ===== 4. 浏览器检测 =====
    if (preg_match('/MicroMessenger\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = '微信'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/MQQBrowser\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'QQ浏览器'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/UCBrowser\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'UC浏览器'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/Quark\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = '夸克'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/BaiduBrowser\/([\d.]+)/i', $ua, $m) || preg_match('/BIDUBrowser\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = '百度浏览器'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/MiuiBrowser\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = '小米浏览器'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/HuaweiBrowser\/([\d.]+)/i', $ua, $m) || preg_match('/HUAWEI\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = '华为浏览器'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/OPPOBrowser\/([\d.]+)/i', $ua, $m) || preg_match('/OppoBrowser\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'OPPO浏览器'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/VivoBrowser\/([\d.]+)/i', $ua, $m) || preg_match('/VivoBrowser\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'vivo浏览器'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/Sogou[\s\/]([\d.]+)/i', $ua, $m) || preg_match('/SogouMobileBrowser/i', $ua)) {
        $result['browser'] = '搜狗浏览器'; $result['browserVer'] = $m[1] ?? '';
    } elseif (preg_match('/360browser/i', $ua) || preg_match('/QHBrowser/i', $ua) || preg_match('/QihooBrowser/i', $ua)) {
        $result['browser'] = '360浏览器';
        if (preg_match('/(?:360browser|QHBrowser|QihooBrowser)[\/\s]([\d.]+)/i', $ua, $m)) $result['browserVer'] = $m[1];
    } elseif (preg_match('/Maxthon\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = '傲游浏览器'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/Edg\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'Edge'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/OPR\/([\d.]+)/i', $ua, $m) || preg_match('/Opera\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'Opera'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/Vivaldi\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'Vivaldi'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m) || preg_match('/FxiOS\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'Firefox'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/CriOS\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'Chrome'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/Version\/([\d.]+).*Safari/i', $ua, $m)) {
        $result['browser'] = 'Safari'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'Chrome'; $result['browserVer'] = $m[1];
    } elseif (preg_match('/MSIE ([\d.]+)/i', $ua, $m) || preg_match('/Trident.*rv:([\d.]+)/i', $ua, $m)) {
        $result['browser'] = 'IE'; $result['browserVer'] = $m[1];
    }

    // ===== 5. 操作系统检测 =====
    if (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m)) {
        $result['os'] = 'iOS'; $result['osVer'] = str_replace('_', '.', $m[1]);
    } elseif (preg_match('/iPad.*OS ([\d_]+)/i', $ua, $m)) {
        $result['os'] = 'iPadOS'; $result['osVer'] = str_replace('_', '.', $m[1]);
    } elseif (preg_match('/iOS[\/\s]([\d_]+)/i', $ua, $m)) {
        $result['os'] = 'iOS'; $result['osVer'] = str_replace('_', '.', $m[1]);
    } elseif (preg_match('/HarmonyOS[\/\s]([\d.]+)/i', $ua, $m)) {
        $result['os'] = '鸿蒙'; $result['osVer'] = $m[1];
    } elseif (preg_match('/Android ([\d.]+)/i', $ua, $m)) {
        $result['os'] = 'Android'; $result['osVer'] = $m[1];
    } elseif ($isMobile && $brand && !$result['osVer']) {
        $result['os'] = 'Android';
    } elseif (preg_match('/Windows NT ([\d.]+)/i', $ua, $m)) {
        $ver = $m[1]; $result['os'] = 'Windows';
        if ($ver == '10.0') { 
            $result['osVer'] = '10/11';
        } elseif ($ver == '6.3') { $result['osVer'] = '8.1'; }
        elseif ($ver == '6.2') { $result['osVer'] = '8'; }
        elseif ($ver == '6.1') { $result['osVer'] = '7'; }
        elseif ($ver == '6.0') { $result['osVer'] = 'Vista'; }
        elseif ($ver == '5.1') { $result['osVer'] = 'XP'; }
        else { $result['osVer'] = $ver; }
    } elseif (preg_match('/Mac OS X ([\d_]+)/i', $ua, $m)) {
        $result['os'] = 'macOS'; $result['osVer'] = str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Macintosh/i', $ua)) {
        $result['os'] = 'macOS';
    } elseif (preg_match('/Linux/i', $ua)) {
        $result['os'] = 'Linux';
    } elseif (preg_match('/CrOS/i', $ua)) {
        $result['os'] = 'ChromeOS';
    } elseif (preg_match('/Windows Phone/i', $ua)) {
        $result['os'] = 'Windows Phone';
    }

    // ===== 6. 设备类型检测 =====
    if (preg_match('/iPhone/i', $ua)) {
        $result['device'] = 'iPhone';
    } elseif (preg_match('/iPad/i', $ua)) {
        $result['device'] = 'iPad';
    } elseif (preg_match('/iPod/i', $ua)) {
        $result['device'] = 'iPod';
    } elseif (preg_match('/Windows Phone/i', $ua)) {
        $result['device'] = 'Windows Phone';
    } elseif ($isMobile && (preg_match('/Tablet/i', $ua) || preg_match('/iPad/i', $ua))) {
        $result['device'] = '平板';
    } elseif ($isMobile && preg_match('/Android/i', $ua) && preg_match('/Mobile/i', $ua)) {
        $result['device'] = '安卓手机';
    } elseif ($isMobile && preg_match('/Android/i', $ua)) {
        $result['device'] = '安卓平板';
    } elseif ($isMobile && $brand) {
        if (preg_match('/Pad|Tab|Tablet/i', $ua)) {
            $result['device'] = '安卓平板';
        } else {
            $result['device'] = '安卓手机';
        }
    } elseif ($isMobile) {
        $result['device'] = '移动设备';
    } elseif ($result['isBot']) {
        $result['device'] = '爬虫';
    } else {
        $result['device'] = '桌面电脑';
    }

    return $result;
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
            $ll = getIPLatLng($item['ip'] ?? '');
            if ($ll) {
                $item['lat'] = $ll['lat'];
                $item['lng'] = $ll['lng'];
            }
            $item['uaInfo'] = parseUA($item['ua'] ?? '');
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
            $ll = getIPLatLng($v['ip'] ?? '');
            if ($ll) {
                $v['lat'] = $ll['lat'];
                $v['lng'] = $ll['lng'];
            }
            $v['uaInfo'] = parseUA($v['ua'] ?? '');
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
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'clean_visitors') {
        $mode = $input['mode'] ?? 'all';
        $count = intval($input['count'] ?? 0);

        if ($mode === 'count' && $count > 0) {
            // 清理最早的N条
            $visitors = array_slice($visitors, $count);
        } else {
            // 清理全部
            $visitors = [];
        }
        file_put_contents($file, json_encode($visitors, JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => '清理成功', 'remaining' => count($visitors)]);
    } elseif ($action === 'clean_location_cache') {
        // 清理IP地址缓存
        $cacheFile = __DIR__ . '/../data/ip_location_cache.json';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
        $ipCacheFile = __DIR__ . '/../data/ip_cache.json';
        if (file_exists($ipCacheFile)) {
            @unlink($ipCacheFile);
        }
        echo json_encode(['success' => true, 'message' => '缓存已清理']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
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