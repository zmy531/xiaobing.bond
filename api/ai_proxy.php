<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$profileFile = __DIR__ . '/../data/profile.json';
$defaultAI = [
    'mode' => 'local',
    'base_url' => 'https://openai.good.hidns.vip/v1',
    'api_key' => 'https://github.com/smanx/free-api',
    'model' => 'qwen3.6-plus',
    'system_prompt' => '你是 xiaobing-bot，一个亲切友好的个人助手，请用简短自然的中文回答用户的问题。',
    'fallback_local' => true
];

function loadAIConfig() {
    global $profileFile, $defaultAI;
    if (!file_exists($profileFile)) return $defaultAI;
    $data = json_decode(@file_get_contents($profileFile), true);
    if (!is_array($data) || !isset($data['ai_config']) || !is_array($data['ai_config'])) return $defaultAI;
    return array_merge($defaultAI, $data['ai_config']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

$question = trim((string)($input['question'] ?? ''));
$history = isset($input['history']) && is_array($input['history']) ? $input['history'] : [];

if ($question === '') {
    http_response_code(400);
    echo json_encode(['error' => '问题不能为空'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ai = loadAIConfig();

// local 模式：直接打标志，交给前端走本地 ai_qa.php
if (($ai['mode'] ?? 'local') !== 'external') {
    echo json_encode([
        'mode' => 'local',
        'fallback' => true,
        'message' => '当前配置为本地问答库模式'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$baseUrl = rtrim((string)($ai['base_url'] ?? ''), '/');
$apiKey = (string)($ai['api_key'] ?? '');
$model = (string)($ai['model'] ?? 'qwen3.6-plus');
$systemPrompt = (string)($ai['system_prompt'] ?? '');
$fallback = !empty($ai['fallback_local']);

if (!$baseUrl || !$apiKey) {
    echo json_encode([
        'mode' => 'external',
        'fallback' => $fallback,
        'error' => 'AI 接口未配置完整'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$endpoint = $baseUrl . (strpos($baseUrl, '/chat/completions') !== false ? '' : (strpos($baseUrl, '/v1') !== false ? '/chat/completions' : '/v1/chat/completions'));

$messages = [];
if ($systemPrompt !== '') {
    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
}
if (!empty($history)) {
    $recent = array_slice($history, -10);
    foreach ($recent as $h) {
        if (!is_array($h)) continue;
        $role = in_array(($h['role'] ?? ''), ['user', 'assistant', 'system']) ? $h['role'] : 'user';
        $c = (string)($h['content'] ?? '');
        if ($c !== '') $messages[] = ['role' => $role, 'content' => $c];
    }
}
$messages[] = ['role' => 'user', 'content' => $question];

$payload = [
    'model' => $model,
    'messages' => $messages,
    'temperature' => 0.8,
    'max_tokens' => 2048,
    'stream' => false
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 40);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey,
    'Accept: application/json'
]);

$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err || !$resp) {
    echo json_encode([
        'mode' => 'external',
        'fallback' => $fallback,
        'error' => '请求失败' . ($err ? ': ' . $err : '')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($resp, true);
if (!is_array($data)) {
    echo json_encode([
        'mode' => 'external',
        'fallback' => $fallback,
        'error' => '响应格式错误',
        'raw' => substr($resp, 0, 500)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    $errMsg = $data['error']['message'] ?? ($data['message'] ?? ('HTTP ' . $httpCode));
    echo json_encode([
        'mode' => 'external',
        'fallback' => $fallback,
        'error' => (string)$errMsg,
        'http_code' => $httpCode
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$content = '';
if (isset($data['choices']) && is_array($data['choices']) && !empty($data['choices'])) {
    $first = $data['choices'][0] ?? [];
    if (isset($first['message']['content'])) {
        $content = $first['message']['content'];
    } elseif (isset($first['delta']['content'])) {
        $content = $first['delta']['content'];
    } elseif (isset($first['text'])) {
        $content = $first['text'];
    }
}
if (is_array($content)) {
    $texts = [];
    foreach ($content as $c) {
        if (is_string($c)) $texts[] = $c;
        elseif (is_array($c) && isset($c['text'])) $texts[] = $c['text'];
    }
    $content = implode('', $texts);
}

if ($content === '' && isset($data['content'])) {
    if (is_string($data['content'])) $content = $data['content'];
    elseif (is_array($data['content'])) {
        $texts = [];
        foreach ($data['content'] as $c) {
            if (is_array($c) && isset($c['text'])) $texts[] = $c['text'];
        }
        $content = implode('', $texts);
    }
}

if ($content === '') {
    echo json_encode([
        'mode' => 'external',
        'fallback' => $fallback,
        'error' => '返回内容为空',
        'raw' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'mode' => 'external',
    'fallback' => false,
    'content' => (string)$content
], JSON_UNESCAPED_UNICODE);
