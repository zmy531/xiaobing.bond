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
$apiUrl = 'https://zipapi.cn/API/qc.php';

$douyinId = '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $douyinId = trim($input['douyin_id'] ?? $input['id'] ?? '');
    } else {
        $douyinId = trim($_POST['douyin_id'] ?? $_POST['id'] ?? '');
    }
} else {
    $douyinId = trim($_GET['douyin_id'] ?? $_GET['id'] ?? '');
}

if (empty($douyinId)) {
    echo json_encode(['success' => false, 'msg' => '请输入抖音ID或链接'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 如果输入的是URL，尝试提取ID
if (filter_var($douyinId, FILTER_VALIDATE_URL)) {
    $douyinId = preg_replace('/.*douyin\.com\/user\//', '', $douyinId);
    $douyinId = preg_replace('/\/.*/', '', $douyinId);
}

// 先构造一个默认的抖音链接，即使API失败也能返回跳转
$defaultLink = 'https://www.douyin.com/user/' . urlencode($douyinId);

$params = http_build_query([
    'apikey' => $apiKey,
    'id' => $douyinId
]);
$fullUrl = $apiUrl . '?' . $params;

$ch = curl_init($fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 即使API请求失败，也返回基本的跳转链接
if (!empty($curlError)) {
    echo json_encode([
        'success' => true,
        'msg' => '外部API不可用，已生成基础链接',
        'link' => $defaultLink,
        'data' => ['抖音ID' => $douyinId, '链接' => $defaultLink, '提示' => '外部API请求失败，您仍可点击下方链接跳转'],
        'raw' => ['error' => $curlError]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        'success' => true,
        'msg' => '外部API响应异常，已生成基础链接',
        'link' => $defaultLink,
        'data' => ['抖音ID' => $douyinId, '链接' => $defaultLink, '提示' => 'API返回非200状态码(' . $httpCode . ')，您仍可点击下方链接跳转'],
        'raw' => ['http_code' => $httpCode]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => true,
        'msg' => 'API返回格式异常，已生成基础链接',
        'link' => $defaultLink,
        'data' => ['抖音ID' => $douyinId, '链接' => $defaultLink, '提示' => 'API返回格式错误，您仍可点击下方链接跳转'],
        'raw' => $response
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$code = $result['code'] ?? $result['Code'] ?? -1;
$msg = $result['msg'] ?? $result['Msg'] ?? '';

if ($code == 200) {
    $data = $result['data'] ?? $result['Data'] ?? $result;

    $link = '';
    if (is_array($data)) {
        $link = $data['link'] ?? $data['Link'] ?? $data['url'] ?? $data['Url'] ?? $data['home_url'] ?? '';
    }
    if (empty($link)) {
        $link = $defaultLink;
    }

    $formattedData = [];
    if (is_array($data)) {
        $mappings = [
            'uid' => 'UID', 'id' => 'ID', 'sec_uid' => 'SEC_UID',
            'nickname' => '昵称', 'nick_name' => '昵称', 'name' => '名称',
            'avatar' => '头像', 'avatar_url' => '头像', 'head_img' => '头像',
            'signature' => '简介', 'desc' => '简介',
            'gender' => '性别', 'sex' => '性别',
            'birthday' => '生日', 'age' => '年龄',
            'region' => '地区', 'area' => '地区', 'city' => '城市',
            'follower_count' => '粉丝数', 'fans' => '粉丝数',
            'following_count' => '关注数', 'following' => '关注数',
            'aweme_count' => '作品数', 'works' => '作品数',
            'link' => '链接', 'url' => '链接', 'home_url' => '主页',
            'unique_id' => '唯一ID', 'short_id' => '短ID',
        ];
        foreach ($data as $key => $value) {
            if (is_array($value)) continue;
            $label = $mappings[$key] ?? $key;
            $formattedData[$label] = $value;
        }
    }

    // 添加基础信息
    if (!isset($formattedData['抖音ID'])) {
        $formattedData['抖音ID'] = $douyinId;
    }

    echo json_encode([
        'success' => true,
        'msg' => $msg ?: '查询成功',
        'link' => $link,
        'data' => $formattedData,
        'raw' => $result
    ], JSON_UNESCAPED_UNICODE);
} else {
    // 即使API返回错误代码，也返回链接让用户可以跳转
    $link = $defaultLink;
    echo json_encode([
        'success' => true,
        'msg' => $msg ?: '第三方API查询失败',
        'link' => $link,
        'data' => [
            '抖音ID' => $douyinId,
            '链接' => $link,
            '提示' => 'API未查到详细信息，您可以点击下方链接直接跳转抖音主页查看'
        ],
        'raw' => $result
    ], JSON_UNESCAPED_UNICODE);
}
?>