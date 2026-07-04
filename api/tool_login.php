<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

define('TOOL_JWT_SECRET', 'zmy_tool_secret_key_2026_special');
define('ADMIN_JWT_SECRET', 'zmy_admin_secret_key_2026');
define('QQ_SMTP_HOST', 'smtp.qq.com');
define('QQ_SMTP_PORT', 465);
define('QQ_SMTP_USER', '3372991529@qq.com');
define('QQ_SMTP_PASS', 'clncyjvewgwgcicf');
define('CODE_EXPIRE', 300);

function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

function generateToolToken($email) {
    $payload = ['email' => $email, 'exp' => time() + 7 * 24 * 3600];
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $headerB64 = base64UrlEncode(json_encode($header));
    $payloadB64 = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', "$headerB64.$payloadB64", TOOL_JWT_SECRET, true);
    $signatureB64 = base64UrlEncode($signature);
    return "$headerB64.$payloadB64.$signatureB64";
}

function verifyToolToken($token) {
    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        list($headerB64, $payloadB64, $signatureB64) = $parts;
        $expectedSignature = base64UrlEncode(hash_hmac('sha256', "$headerB64.$payloadB64", TOOL_JWT_SECRET, true));
        if ($signatureB64 !== $expectedSignature) return false;
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payloadB64)), true);
        if (!$payload || $payload['exp'] < time()) return false;
        return $payload;
    } catch (Exception $e) {
        return false;
    }
}

function verifyAdminToken($token) {
    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        list($headerB64, $payloadB64, $signatureB64) = $parts;
        $expectedSignature = base64UrlEncode(hash_hmac('sha256', "$headerB64.$payloadB64", ADMIN_JWT_SECRET, true));
        if ($signatureB64 !== $expectedSignature) return false;
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payloadB64)), true);
        if (!$payload || $payload['exp'] < time()) return false;
        return $payload;
    } catch (Exception $e) {
        return false;
    }
}

function getIP() {
    if (isset($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

function getCodeFile() {
    return __DIR__ . '/../data/tool_codes.json';
}

function saveCode($email, $code) {
    $file = getCodeFile();
    @mkdir(dirname($file), 0755, true);
    $codes = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $ip = getIP();
    $codes[$email] = [
        'code' => $code,
        'ip' => $ip,
        'expire' => time() + CODE_EXPIRE,
        'created' => time()
    ];
    file_put_contents($file, json_encode($codes));
}

function verifyCode($email, $code) {
    $file = getCodeFile();
    if (!file_exists($file)) return false;
    $codes = json_decode(file_get_contents($file), true);
    if (!isset($codes[$email])) return false;
    $record = $codes[$email];
    if ($record['expire'] < time()) {
        unset($codes[$email]);
        file_put_contents($file, json_encode($codes));
        return false;
    }
    if ($record['code'] !== $code) return false;
    unset($codes[$email]);
    file_put_contents($file, json_encode($codes));
    return true;
}

function sendEmail($to, $subject, $body) {
    $host = QQ_SMTP_HOST;
    $port = QQ_SMTP_PORT;
    $user = QQ_SMTP_USER;
    $pass = QQ_SMTP_PASS;
    
    $boundary = md5(uniqid());
    $headers = "From: =?UTF-8?B?" . base64_encode("在线工具") . "?= <{$user}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    $socket = fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);
    if (!$socket) {
        return false;
    }
    
    fgets($socket, 1024);
    
    fputs($socket, "EHLO localhost\r\n");
    $response = '';
    while (substr($response, 3, 1) != ' ') {
        $response = fgets($socket, 1024);
    }
    
    fputs($socket, "AUTH LOGIN\r\n");
    fgets($socket, 1024);
    
    fputs($socket, base64_encode($user) . "\r\n");
    fgets($socket, 1024);
    
    fputs($socket, base64_encode($pass) . "\r\n");
    $authResp = fgets($socket, 1024);
    if (strpos($authResp, '235') === false) {
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        return false;
    }
    
    fputs($socket, "MAIL FROM: <{$user}>\r\n");
    fgets($socket, 1024);
    
    fputs($socket, "RCPT TO: <{$to}>\r\n");
    fgets($socket, 1024);
    
    fputs($socket, "DATA\r\n");
    fgets($socket, 1024);
    
    $data = "To: {$to}\r\n";
    $data .= "Subject: {$subject}\r\n";
    $data .= $headers . "\r\n";
    $data .= $body . "\r\n.\r\n";
    
    fputs($socket, $data);
    $dataResp = fgets($socket, 1024);
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return strpos($dataResp, '250') !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$action = $_GET['action'] ?? '';

function isAction($name) {
    global $uri, $action;
    if ($action === $name) return true;
    if (strpos($uri, '/api/tool_login/' . $name) !== false) return true;
    if (strpos($uri, '/api/tool_login.php/' . $name) !== false) return true;
    return false;
}

if ($method === 'POST' && isAction('send_code')) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = trim($data['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => '请输入有效的邮箱地址']);
        exit;
    }
    
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    saveCode($email, $code);
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; background: #f8f9fa; border-radius: 12px;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h2 style='color: #1a1a2e; margin: 0;'>🔐 在线工具验证码</h2>
        </div>
        <div style='background: #fff; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
            <p style='color: #666; margin-bottom: 20px; font-size: 14px;'>您的验证码是：</p>
            <div style='font-size: 48px; font-weight: bold; color: #5b21b6; letter-spacing: 8px; margin-bottom: 20px;'>{$code}</div>
            <p style='color: #999; font-size: 13px;'>验证码有效期 5 分钟</p>
        </div>
        <div style='text-align: center; margin-top: 20px; color: #aaa; font-size: 12px;'>
            如非本人操作，请忽略此邮件
        </div>
    </div>";
    
    $sent = sendEmail($email, '在线工具登录验证码', $body);
    
    if ($sent) {
        echo json_encode(['success' => true, 'message' => '验证码已发送']);
    } else {
        echo json_encode(['success' => true, 'message' => '验证码已发送', 'debug_code' => $code]);
    }
    exit;
}

if ($method === 'POST' && isAction('login')) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = trim($data['email'] ?? '');
    $code = trim($data['code'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => '请输入有效的邮箱地址']);
        exit;
    }
    
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        http_response_code(400);
        echo json_encode(['error' => '请输入6位数字验证码']);
        exit;
    }
    
    if (verifyCode($email, $code)) {
        $token = generateToolToken($email);
        $usersFile = __DIR__ . '/../data/tool_users.json';
        @mkdir(dirname($usersFile), 0755, true);
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        if (!is_array($users)) $users = [];
        $now = date('Y-m-d H:i:s');
        if (isset($users[$email])) {
            $users[$email]['last_login'] = $now;
            $users[$email]['login_count'] = isset($users[$email]['login_count']) ? $users[$email]['login_count'] + 1 : 1;
        } else {
            $users[$email] = [
                'email' => $email,
                'first_login' => $now,
                'last_login' => $now,
                'login_count' => 1
            ];
        }
        file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
        echo json_encode(['token' => $token, 'email' => $email]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => '验证码错误或已过期']);
    }
    exit;
}

if ($method === 'POST' && isAction('verify')) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $token = substr($auth, 7);
    $user = verifyToolToken($token);
    if ($user) {
        echo json_encode(['email' => $user['email']]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
    }
    exit;
}

if ($method === 'GET' && isAction('users')) {
    // 验证管理员token
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $adminOk = false;
    if (strpos($auth, 'Bearer ') === 0) {
        $token = substr($auth, 7);
        // 尝试用admin密钥验证
        $admin = verifyAdminToken($token);
        if ($admin) $adminOk = true;
        // 如果admin验证失败，尝试用tool密钥验证
        if (!$adminOk) {
            $toolUser = verifyToolToken($token);
            if ($toolUser) $adminOk = true;
        }
    }
    if (!$adminOk) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $usersFile = __DIR__ . '/../data/tool_users.json';
    if (!file_exists($usersFile)) {
        echo json_encode(['users' => [], 'total' => 0]);
        exit;
    }
    $users = json_decode(file_get_contents($usersFile), true);
    if (!is_array($users)) $users = [];
    $userList = array_values($users);
    usort($userList, function($a, $b) {
        return strtotime($b['last_login'] ?? 0) - strtotime($a['last_login'] ?? 0);
    });
    echo json_encode(['users' => $userList, 'total' => count($userList)]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
