<?php
@ini_set('display_errors', '0');
@error_reporting(0);
@ob_start();

// 允许跨域
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    @ob_end_clean();
    http_response_code(200);
    exit;
}

$apiUrl = 'https://uapis.cn/api/v1/random/image';

// 透传 GET 参数（可选的分类/类型参数）
$qs = $_SERVER['QUERY_STRING'] ?? '';
$fullUrl = $qs ? ($apiUrl . '?' . $qs) : $apiUrl;

$ch = curl_init($fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HEADER, true); // 拿到响应头用于透传 Content-Type

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

@ob_end_clean();

if (!empty($curlError)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => '请求错误：' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode < 200 || $httpCode >= 400) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => '上游错误码：' . $httpCode], JSON_UNESCAPED_UNICODE);
    exit;
}

$headerBlock = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

// 解析并透传 Content-Type
$contentType = 'image/jpeg';
foreach (explode("\r\n", $headerBlock) as $h) {
    if (stripos($h, 'Content-Type:') === 0) {
        $contentType = trim(substr($h, 13));
        break;
    }
}

header('Content-Type: ' . $contentType);
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $body;
?>