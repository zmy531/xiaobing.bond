<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$apiProviders = [
    [
        'name' => 'pekpik',
        'base_url' => 'https://aiapiv2.pekpik.com/v1',
        'keys' => [
            'sk-eikypSuAZqfi5t51vFAonA5Pq1aohzsdw3zpnqlnUodFPqnP',
            'sk-psErZzNPjXZl1j9DWNq3Yy6clhS76m8eyoDIgCYiwyY2bF7u',
            'sk-Gi6RgAitKTWbQXp2ZXnoaavIgdCxvMjmXfIyTCCg7cBy3B8f',
            'sk-KN5hStAnLMbPAqcoJ7IiupulH8gJgS0r7ziM0GDDIrV3GJTL',
            'sk-w2Ql7CZkuo1bgDAEEAZXRd15MzH7vqT8cc4Nna7z4rKJ3yjC',
            'sk-6zOdel8p7R15I5KH4yNXMRoYxDWmzRxGTCCkhhgj87dq4bWv',
            'sk-Em5LrhWxqFMwzPRVnn3vm2HaZ8ONYaOSHGtobMSA2mjuWQzp',
            'sk-AncmOhO1lhNArL6w54fv4jziav8hPMOXcGNQCPj10jToaMAn',
            'sk-MR60tokIdDDY407RMYwNycHNFi7Frw4lnRoh3vSj25uRnOF5',
            'sk-S0zSgXq5tXEk9niUCv9uQ28cY6kIRos2MKG02UvdyLQulvY3',
            'sk-mAK09imKKz1vzHmJSQctsWag4ytdj7wOJzOSKyZ5TyKr6Mtw',
            'sk-UGCIuNlH3Q3EV4gE69mTZPK6rqKinYnWBp8AmGbHy011RRhd',
            'sk-DDPgCvJ9Qh76PgY9eEPaydR4SFVQ49QbdArKOLWxtFAWkBoB',
            'sk-KGQb3Mi8f08h3g4Uknra8eBFscSxZoYGit6M2immCKabtgtT',
            'sk-eMyLhIifdX6Zj2z8maOEaa7I6JBFaxyuQYczNE3vcThzt29J',
            'sk-n8l2wBYcvHJSstwTpouwpAKpSEQYsajzwNp2YN350AUzjdnj',
            'sk-doHGC6NszhCARramLS4YsXS2jE0cbwTMTa2o1mxb221HnNrN',
            'sk-Jux1XyS8F9VzfIDpE2wuasQEclONjyCUiM7vlTzj9GzRxqlD'
        ],
        'default_model' => 'smart-chat'
    ],
    [
        'name' => 'chatanywhere',
        'base_url' => 'https://api.chatanywhere.tech/v1',
        'keys' => [
            'sk-7Q5bE5bE0f9a4a4f9f8b9d7c6e5a4b3c2d1e0f9a8b7c6d5e'
        ],
        'default_model' => 'gpt-4o-mini'
    ],
    [
        'name' => 'freegpt35',
        'base_url' => 'https://api.freegpt35.ru/v1',
        'keys' => [
            'sk-free'
        ],
        'default_model' => 'gpt-3.5-turbo'
    ]
];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$messages = $input['messages'];
$model = $input['model'] ?? null;
$stream = $input['stream'] ?? false;

function callApi($baseUrl, $apiKey, $messages, $model, $stream) {
    $url = $baseUrl . '/chat/completions';
    
    $data = [
        'model' => $model,
        'messages' => $messages,
        'stream' => $stream,
        'temperature' => 0.8,
        'max_tokens' => 2048
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return ['response' => $response, 'httpCode' => $httpCode, 'error' => $error];
}

function isValidResponse($response, $httpCode) {
    if ($httpCode !== 200 || empty($response)) return false;
    $data = json_decode($response, true);
    if (!$data) return false;
    if (isset($data['choices']) && is_array($data['choices']) && count($data['choices']) > 0) {
        return true;
    }
    return false;
}

$lastProvider = 0;
$lastKey = 0;
$indexFile = __DIR__ . '/../data/ai_rotate_index.json';
if (file_exists($indexFile)) {
    $idx = json_decode(file_get_contents($indexFile), true);
    $lastProvider = $idx['provider'] ?? 0;
    $lastKey = $idx['key'] ?? 0;
}

$totalProviders = count($apiProviders);
$allResults = [];

for ($p = 0; $p < $totalProviders; $p++) {
    $providerIdx = ($lastProvider + $p) % $totalProviders;
    $provider = $apiProviders[$providerIdx];
    $useModel = $model ?? $provider['default_model'];
    
    $keyCount = count($provider['keys']);
    for ($k = 0; $k < $keyCount; $k++) {
        $keyIdx = ($p === 0) ? (($lastKey + $k) % $keyCount) : $k;
        $apiKey = $provider['keys'][$keyIdx];
        
        $result = callApi($provider['base_url'], $apiKey, $messages, $useModel, $stream);
        
        if (isValidResponse($result['response'], $result['httpCode'])) {
            @file_put_contents($indexFile, json_encode(['provider' => $providerIdx, 'key' => $keyIdx]));
            echo $result['response'];
            exit;
        }
        
        $allResults[] = [
            'provider' => $provider['name'],
            'key_index' => $keyIdx,
            'code' => $result['httpCode'],
            'error' => $result['error'] ?: substr($result['response'], 0, 200)
        ];
    }
}

http_response_code(503);
echo json_encode([
    'error' => '所有AI接口暂时不可用，请稍后重试',
    'details' => $allResults
]);
?>