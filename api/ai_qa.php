<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$qaFile = __DIR__ . '/../data/ai_qa.json';

function loadQA() {
    global $qaFile;
    if (file_exists($qaFile)) {
        $data = json_decode(file_get_contents($qaFile), true);
        if (is_array($data)) return $data;
    }
    return [];
}

function saveQA($data) {
    global $qaFile;
    @mkdir(dirname($qaFile), 0755, true);
    file_put_contents($qaFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function generateId() {
    return date('YmdHis') . substr(md5(uniqid(mt_rand(), true)), 0, 8);
}

function searchBestMatch($question, $qaList) {
    $q = mb_strtolower(trim($question));
    if (empty($q)) return null;
    
    $bestMatch = null;
    $bestScore = 0;
    
    foreach ($qaList as $qa) {
        $keywords = $qa['keywords'] ?? [];
        $answer = $qa['answer'] ?? '';
        if (empty($answer)) continue;
        
        $score = 0;
        $qaKeywords = array_map('mb_strtolower', $keywords);
        
        foreach ($qaKeywords as $kw) {
            if (empty($kw)) continue;
            if (strpos($q, $kw) !== false) {
                $score += strlen($kw);
            }
        }
        
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $qa;
        }
    }
    
    return $bestMatch;
}

$action = $_GET['action'] ?? 'chat';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'chat') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['message'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $message = trim($input['message']);
    $qaList = loadQA();
    
    $match = searchBestMatch($message, $qaList);
    
    if ($match) {
        echo json_encode([
            'success' => true,
            'reply' => $match['answer'],
            'matched' => true
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'reply' => '您发送的"' . $message . '"暂时没有收录，可联系管理员添加',
            'matched' => false
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function authenticate() {
    // 兼容 CGI/FastCGI 环境下 HTTP_AUTHORIZATION 缺失的问题
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
    // 从 Apache 风格的环境变量中提取
    if (!$auth && isset($_SERVER['HTTP_AUTHENTICATION'])) {
        $auth = $_SERVER['HTTP_AUTHENTICATION'];
    }
    // 一些 PHP 配置中 Authorization 被重写为其他名字
    if (!$auth) {
        foreach ($_SERVER as $k => $v) {
            if ((strpos($k, 'AUTHORIZ') !== false || strpos($k, 'AUTHENTIC') !== false) && is_string($v)) { $auth = $v; break; }
        }
    }
    if ($auth && strpos($auth, 'Bearer ') === 0) {
        $token = substr($auth, 7);
        $result = verifyToken($token);
        if ($result) return $result;
    }
    $token = $_GET['token'] ?? $_POST['token'] ?? '';
    if ($token) {
        $result = verifyToken($token);
        if ($result) return $result;
    }
    return false;
}

function verifyToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    $secret = 'zmy_admin_secret_key_2026';
    $headerB64 = $parts[0];
    $payloadB64 = $parts[1];
    $sig = $parts[2];
    $expectedSig = base64_encode(hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true));
    $expectedSig = rtrim(strtr($expectedSig, '+/', '-_'), '=');
    if ($sig !== $expectedSig) return false;
    $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) return false;
    return ['username' => $payload['username'] ?? 'admin'];
}

if ($action === 'admin/list') {
    $user = authenticate();
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $qaList = loadQA();
    echo json_encode(['success' => true, 'list' => $qaList], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'admin/add') {
    $user = authenticate();
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['answer'])) {
        http_response_code(400);
        echo json_encode(['error' => '答案不能为空']);
        exit;
    }
    
    $qaList = loadQA();
    $newItem = [
        'id' => generateId(),
        'keywords' => $input['keywords'] ?? [],
        'answer' => $input['answer'],
        'created_at' => date('Y-m-d H:i:s')
    ];
    $qaList[] = $newItem;
    saveQA($qaList);
    
    echo json_encode(['success' => true, 'item' => $newItem], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'admin/update') {
    $user = authenticate();
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID不能为空']);
        exit;
    }
    
    $qaList = loadQA();
    foreach ($qaList as &$item) {
        if ($item['id'] === $input['id']) {
            $item['keywords'] = $input['keywords'] ?? $item['keywords'];
            $item['answer'] = $input['answer'] ?? $item['answer'];
            $item['updated_at'] = date('Y-m-d H:i:s');
            saveQA($qaList);
            echo json_encode(['success' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    echo json_encode(['error' => '未找到该条问答']);
    exit;
}

if ($action === 'admin/delete') {
    $user = authenticate();
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID不能为空']);
        exit;
    }
    
    $qaList = loadQA();
    $newList = [];
    foreach ($qaList as $item) {
        if ($item['id'] !== $input['id']) {
            $newList[] = $item;
        }
    }
    saveQA($newList);
    
    echo json_encode(['success' => true, 'message' => '删除成功'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'admin/batch_import') {
    $user = authenticate();
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['items']) || !is_array($input['items'])) {
        http_response_code(400);
        echo json_encode(['error' => '导入数据不能为空']);
        exit;
    }
    
    $qaList = loadQA();
    $added = 0;
    foreach ($input['items'] as $item) {
        if (empty($item['answer']) || empty($item['keywords']) || !is_array($item['keywords'])) continue;
        $qaList[] = [
            'id' => generateId(),
            'keywords' => $item['keywords'],
            'answer' => $item['answer'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        $added++;
    }
    saveQA($qaList);
    
    echo json_encode(['success' => true, 'added' => $added], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
