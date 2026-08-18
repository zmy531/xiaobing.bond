<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
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
        if (!$payload || ($payload['exp'] ?? 0) < time()) return false;
        return $payload;
    } catch (Exception $e) {
        return false;
    }
}

function authenticate() {
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
    if (!$auth && isset($_SERVER['HTTP_AUTHENTICATION'])) {
        $auth = $_SERVER['HTTP_AUTHENTICATION'];
    }
    if (!$auth) {
        foreach ($_SERVER as $k => $v) {
            if (is_string($v) && (strpos($k, 'AUTHORIZ') !== false || strpos($k, 'AUTHENTIC') !== false)) { $auth = $v; break; }
        }
    }
    if (strpos($auth, 'Bearer ') !== 0) return false;
    $token = substr($auth, 7);
    return verifyToken($token);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';
$profileFile = __DIR__ . '/../data/profile.json';
$bgDir = __DIR__ . '/../uploads/background/';
$bgUrlBase = './uploads/background/';

$defaultProfile = [
    'avatar' => './static/img/logo.png',
    'registerDate' => '2025-03',
    'notice' => '欢迎光临小冰岛卡，所有商品自动发卡，安全可靠。保证24h之内发货',
    'contact' => [
        'qq' => '',
        'wechat' => '',
        'email' => '',
        'telegram' => ''
    ],
    'background' => '',
    'backgrounds' => [],
    'notice_box' => [
        'enabled' => true,
        'title' => '有话对你说',
        'content' => '欢迎来到我的小窝~ 祝你开心每一天！'
    ],
    'contact_block' => [
        'card_title' => '联系作者',
        'card_subtitle' => '获取联系方式',
        'card_image' => './static/img/i4.png',
        'modal_html' => ''
    ],
    'ai_config' => [
        'mode' => 'local',
        'base_url' => 'https://openai.good.hidns.vip/v1',
        'api_key' => 'https://github.com/smanx/free-api',
        'model' => 'qwen3.6-plus',
        'system_prompt' => '你是 xiaobing-bot，一个亲切友好的个人助手，请用简短自然的中文回答用户的问题。',
        'fallback_local' => true
    ]
];

function loadProfile() {
    global $profileFile, $defaultProfile;
    if (!file_exists($profileFile)) return $defaultProfile;
    $data = json_decode(@file_get_contents($profileFile), true);
    if (!is_array($data)) return $defaultProfile;
    return array_replace_recursive($defaultProfile, $data);
}

function saveProfile($data) {
    global $profileFile;
    @mkdir(dirname($profileFile), 0755, true);
    file_put_contents($profileFile, json_encode($data, JSON_UNESCAPED_UNICODE));
}

if ($action === 'get') {
    echo json_encode(['success' => true, 'profile' => loadProfile()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 管理接口 ==========
$user = authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        exit;
    }
    $profile = loadProfile();
    if (isset($input['avatar'])) $profile['avatar'] = (string)$input['avatar'];
    if (isset($input['registerDate'])) $profile['registerDate'] = (string)$input['registerDate'];
    if (isset($input['notice'])) $profile['notice'] = (string)$input['notice'];
    if (isset($input['contact']) && is_array($input['contact'])) {
        $profile['contact'] = array_merge($profile['contact'] ?? [], [
            'qq' => (string)($input['contact']['qq'] ?? ''),
            'wechat' => (string)($input['contact']['wechat'] ?? ''),
            'email' => (string)($input['contact']['email'] ?? ''),
            'telegram' => (string)($input['contact']['telegram'] ?? '')
        ]);
    }
    if (isset($input['background'])) $profile['background'] = (string)$input['background'];
    if (isset($input['backgrounds']) && is_array($input['backgrounds'])) {
        $profile['backgrounds'] = array_values($input['backgrounds']);
    }
    if (isset($input['notice_box']) && is_array($input['notice_box'])) {
        $profile['notice_box'] = [
            'enabled' => !empty($input['notice_box']['enabled']),
            'title' => (string)($input['notice_box']['title'] ?? '有话对你说'),
            'content' => (string)($input['notice_box']['content'] ?? '')
        ];
    }
    if (isset($input['contact_block']) && is_array($input['contact_block'])) {
        $profile['contact_block'] = [
            'card_title' => (string)($input['contact_block']['card_title'] ?? '联系作者'),
            'card_subtitle' => (string)($input['contact_block']['card_subtitle'] ?? '获取联系方式'),
            'card_image' => (string)($input['contact_block']['card_image'] ?? './static/img/i4.png'),
            'modal_html' => (string)($input['contact_block']['modal_html'] ?? '')
        ];
    }
    // 保留收款码字段（防止意外覆盖）
    if (isset($input['pay_wx_qr'])) $profile['pay_wx_qr'] = (string)$input['pay_wx_qr'];
    if (isset($input['pay_zfb_qr'])) $profile['pay_zfb_qr'] = (string)$input['pay_zfb_qr'];
    if (isset($input['ai_config']) && is_array($input['ai_config'])) {
        $profile['ai_config'] = [
            'mode' => in_array(($input['ai_config']['mode'] ?? 'local'), ['local', 'external']) ? (string)$input['ai_config']['mode'] : 'local',
            'base_url' => rtrim((string)($input['ai_config']['base_url'] ?? ''), '/'),
            'api_key' => (string)($input['ai_config']['api_key'] ?? ''),
            'model' => (string)($input['ai_config']['model'] ?? 'qwen3.6-plus'),
            'system_prompt' => (string)($input['ai_config']['system_prompt'] ?? ''),
            'fallback_local' => !empty($input['ai_config']['fallback_local'])
        ];
    }
    saveProfile($profile);
    echo json_encode(['success' => true, 'profile' => $profile], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'upload_bg' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    @mkdir($bgDir, 0755, true);
    $profile = loadProfile();
    if (!isset($profile['backgrounds']) || !is_array($profile['backgrounds'])) {
        $profile['backgrounds'] = [];
    }
    $uploaded = [];

    $processFile = function ($file) use ($bgDir, $bgUrlBase, &$profile, &$uploaded) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        if (!in_array($ext, $allowed)) return;
        $filename = 'bg_' . uniqid() . '_' . time() . '.' . $ext;
        $path = $bgDir . $filename;
        if (@move_uploaded_file($file['tmp_name'], $path)) {
            $item = [
                'id' => 'bg' . uniqid(),
                'url' => $bgUrlBase . $filename,
                'filename' => $filename,
                'time' => date('Y-m-d H:i:s')
            ];
            array_unshift($profile['backgrounds'], $item);
            $uploaded[] = $item;
            if (empty($profile['background'])) {
                $profile['background'] = $item['url'];
            }
        }
    };

    if (isset($_FILES['background'])) {
        $f = $_FILES['background'];
        if (is_array($f['name'])) {
            for ($i = 0; $i < count($f['name']); $i++) {
                $processFile([
                    'name' => $f['name'][$i],
                    'type' => $f['type'][$i] ?? '',
                    'tmp_name' => $f['tmp_name'][$i],
                    'error' => $f['error'][$i],
                    'size' => $f['size'][$i] ?? 0
                ]);
            }
        } else {
            $processFile($f);
        }
    }

    saveProfile($profile);
    echo json_encode(['success' => true, 'uploaded' => count($uploaded), 'backgrounds' => $profile['backgrounds'], 'current' => $profile['background']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'delete_bg' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => '缺少ID']);
        exit;
    }
    $profile = loadProfile();
    $bgList = $profile['backgrounds'] ?? [];
    $found = null;
    foreach ($bgList as $k => $b) {
        if (($b['id'] ?? '') === $id) { $found = $b; unset($bgList[$k]); break; }
    }
    if ($found) {
        @unlink($bgDir . ($found['filename'] ?? ''));
        $bgList = array_values($bgList);
        $profile['backgrounds'] = $bgList;
        if (($profile['background'] ?? '') === ($found['url'] ?? '')) {
            $profile['background'] = $bgList[0]['url'] ?? '';
        }
        saveProfile($profile);
        echo json_encode(['success' => true, 'backgrounds' => $bgList, 'current' => $profile['background']], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['error' => '背景图不存在']);
    }
    exit;
}

if ($action === 'set_bg' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$input = json_decode(file_get_contents('php://input'), true);
	$url = $input['url'] ?? '';
	$profile = loadProfile();
	$profile['background'] = (string)$url;
	saveProfile($profile);
	echo json_encode(['success' => true, 'background' => $profile['background']], JSON_UNESCAPED_UNICODE);
	exit;
}

if ($action === 'upload_avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$avatarDir = __DIR__ . '/../uploads/avatar/';
	$avatarUrlBase = './uploads/avatar/';
	@mkdir($avatarDir, 0755, true);
	if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
		http_response_code(400);
		echo json_encode(['error' => '没有上传文件']);
		exit;
	}
	$f = $_FILES['avatar'];
	$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
	$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'];
	if (!in_array($ext, $allowed)) {
		http_response_code(400);
		echo json_encode(['error' => '不支持的图片格式']);
		exit;
	}
	$filename = 'avatar_' . uniqid() . '_' . time() . '.' . $ext;
	$path = $avatarDir . $filename;
	if (!@move_uploaded_file($f['tmp_name'], $path)) {
		http_response_code(500);
		echo json_encode(['error' => '保存失败']);
		exit;
	}
	$url = $avatarUrlBase . $filename;
	$profile = loadProfile();
	$oldAvatar = $profile['avatar'] ?? '';
	$profile['avatar'] = $url;
	saveProfile($profile);
	// 删除旧的自定义头像文件（保留默认 static/img/logo.png）
	if ($oldAvatar && strpos($oldAvatar, 'uploads/avatar/') !== false && $oldAvatar !== $url) {
		$oldFile = $avatarDir . basename($oldAvatar);
		if (file_exists($oldFile)) @unlink($oldFile);
	}
	echo json_encode(['success' => true, 'avatar' => $url], JSON_UNESCAPED_UNICODE);
	exit;
}

// 上传收款码（支付宝/微信）
if ($action === 'upload_pay_qr' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_GET['type'] ?? '';
    if (!in_array($type, ['wx', 'zfb'])) {
        http_response_code(400);
        echo json_encode(['error' => '类型参数无效']);
        exit;
    }
    $qrDir = __DIR__ . '/../uploads/pay_qr/';
    $qrUrlBase = './uploads/pay_qr/';
    @mkdir($qrDir, 0755, true);
    if (!isset($_FILES['qr']) || $_FILES['qr']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => '没有上传文件']);
        exit;
    }
    $f = $_FILES['qr'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => '不支持的图片格式']);
        exit;
    }
    $filename = 'pay_' . $type . '_' . uniqid() . '.' . $ext;
    $path = $qrDir . $filename;
    if (!@move_uploaded_file($f['tmp_name'], $path)) {
        http_response_code(500);
        echo json_encode(['error' => '保存失败']);
        exit;
    }
    $url = $qrUrlBase . $filename;
    $profile = loadProfile();
    $field = 'pay_' . $type . '_qr';
    $oldUrl = $profile[$field] ?? '';
    $profile[$field] = $url;
    saveProfile($profile);
    // 删除旧文件
    if ($oldUrl && strpos($oldUrl, 'uploads/pay_qr/') !== false && $oldUrl !== $url) {
        $oldFile = $qrDir . basename($oldUrl);
        if (file_exists($oldFile)) @unlink($oldFile);
    }
    echo json_encode(['success' => true, 'url' => $url], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 联系页面自定义 HTML ==========
$contactHtmlFile = __DIR__ . '/../uploads/contact_custom.html';
$contactHtmlUrl = './uploads/contact_custom.html';

// 获取联系页面HTML状态（是否已上传+大小）
if ($action === 'get_contact_html') {
    $exists = file_exists($contactHtmlFile);
    $size = $exists ? (int)filesize($contactHtmlFile) : 0;
    // 同时检查旧的 contact_block.modal_html 数据（向后兼容）
    $profile = loadProfile();
    $legacyHtml = !empty($profile['contact_block']['modal_html']) ? true : false;
    echo json_encode([
        'success' => true,
        'exists' => $exists || $legacyHtml,
        'size' => $size,
        'url' => $contactHtmlUrl,
        'legacy' => $legacyHtml
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 上传联系页面HTML文件
if ($action === 'upload_contact_html' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 需要鉴权
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $dir = dirname($contactHtmlFile);
    @mkdir($dir, 0755, true);
    if (!isset($_FILES['html']) || $_FILES['html']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => '没有上传文件']);
        exit;
    }
    $f = $_FILES['html'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['html', 'htm', 'txt'])) {
        http_response_code(400);
        echo json_encode(['error' => '仅支持 .html / .htm / .txt 文件']);
        exit;
    }
    // 限制大小：2MB
    if ($f['size'] > 2 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => '文件过大（最大2MB）']);
        exit;
    }
    if (!@move_uploaded_file($f['tmp_name'], $contactHtmlFile)) {
        http_response_code(500);
        echo json_encode(['error' => '保存失败']);
        exit;
    }
    // 上传成功后，清除旧的 contact_block.modal_html（避免两份数据）
    $profile = loadProfile();
    if (!empty($profile['contact_block']['modal_html'])) {
        $profile['contact_block']['modal_html'] = '';
        saveProfile($profile);
    }
    echo json_encode([
        'success' => true,
        'size' => filesize($contactHtmlFile),
        'url' => $contactHtmlUrl
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 重置联系页面（删除自定义HTML，回退默认）
if ($action === 'reset_contact_html' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $deletedFile = false;
    if (file_exists($contactHtmlFile)) {
        $deletedFile = @unlink($contactHtmlFile);
    }
    // 同时清除旧的 contact_block.modal_html
    $profile = loadProfile();
    $clearedLegacy = false;
    if (!empty($profile['contact_block']['modal_html'])) {
        $profile['contact_block']['modal_html'] = '';
        saveProfile($profile);
        $clearedLegacy = true;
    }
    echo json_encode([
        'success' => true,
        'deleted_file' => $deletedFile,
        'cleared_legacy' => $clearedLegacy
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 读取联系页面HTML内容（供 contact.html 直接调用，无需鉴权）
if ($action === 'get_contact_html_content') {
    $result = ['success' => true, 'has_custom' => false, 'html' => ''];
    // 优先读取上传的文件
    if (file_exists($contactHtmlFile)) {
        $result['has_custom'] = true;
        $result['html'] = file_get_contents($contactHtmlFile);
        $result['source'] = 'file';
    } else {
        // 向后兼容：读取旧的 contact_block.modal_html
        $profile = loadProfile();
        if (!empty($profile['contact_block']['modal_html'])) {
            $result['has_custom'] = true;
            $result['html'] = $profile['contact_block']['modal_html'];
            $result['source'] = 'legacy';
        }
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Invalid request']);
