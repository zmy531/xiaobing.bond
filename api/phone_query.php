<?php
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$apiKey = 'd958b4c24d13b92743ffc96cb24398da2ea6ef0ebc8339504f589cf1d40ba536';
$apiUrl = 'https://zipapi.cn/API/dxhz.php';

$phone = '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $phone = trim($input['phone'] ?? '');
    } else {
        $phone = trim($_POST['phone'] ?? '');
    }
} else {
    $phone = trim($_GET['phone'] ?? '');
}

if (empty($phone)) {
    echo json_encode(['success' => false, 'msg' => '请输入手机号码'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = $apiUrl . '?' . http_build_query([
    'apikey' => $apiKey,
    'phone'  => $phone,
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!empty($curlError)) {
    echo json_encode(['success' => false, 'msg' => '请求异常：' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'msg' => '接口返回错误，HTTP状态码：' . $httpCode], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'msg' => '接口返回非JSON数据', 'raw' => $response], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'msg' => '查询成功', 'data' => $result], JSON_UNESCAPED_UNICODE);
?>