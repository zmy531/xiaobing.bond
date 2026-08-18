<?php
@ini_set('display_errors', '0');
@error_reporting(0);
@ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Expose-Headers: *');

// 处理 OPTIONS 预检请求
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    @ob_end_clean();
    http_response_code(200);
    exit;
}

// 强制捕获任何意外输出，仅保留最后一次合法JSON
function shopEmitJson($data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo $json;
}
// 在脚本结束时兜底，确保没有额外内容乱输出
register_shutdown_function(function(){
    $level = error_get_last();
    if ($level && in_array($level['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @ob_end_clean();
        if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); }
        echo json_encode(['success'=>false,'msg'=>'服务器发生错误，请稍后重试'], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

define('JWT_SECRET', 'zmy_admin_secret_key_2026');
define('ADMIN_EMAIL', '3372991529@qq.com');

// 邮件发送函数
function sendMail($to, $subject, $body) {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: 卡密商城 <noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>'
    ];
    $headerStr = implode("\r\n", $headers);
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headerStr);
}

// 验证码存储
function getCodeDir() {
    $dir = __DIR__ . '/../data/shop/codes';
    @mkdir($dir, 0755, true);
    return $dir;
}
function saveCode($email, $code) {
    $file = getCodeDir() . '/' . md5($email) . '.json';
    file_put_contents($file, json_encode(['code' => $code, 'time' => time()]));
}
function verifyCode($email, $code, $deleteOnVerify = true) {
    $file = getCodeDir() . '/' . md5($email) . '.json';
    if (!file_exists($file)) return false;
    $data = json_decode(file_get_contents($file), true);
    if (!$data) return false;
    if (time() - $data['time'] > 600) return false;
    if ($data['code'] !== $code) return false;
    if ($deleteOnVerify) {
        @unlink($file);
    }
    return true;
}

// ============ 工具函数 ============
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

    // Fallback: 检查 GET/POST 中的 token 参数（某些环境下 Authorization header 被剥离）
    $token = $_GET['token'] ?? $_POST['token'] ?? '';
    if ($token) {
        $result = verifyToken($token);
        if ($result) return $result;
    }

    return false;
}

function getIP() {
    if (isset($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

function getDataPath($name) {
    $dir = __DIR__ . '/../data/shop';
    @mkdir($dir, 0755, true);
    return $dir . '/' . $name . '.json';
}

function loadData($name) {
    $file = getDataPath($name);
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    if ($content === false || $content === '') return [];
    $data = @json_decode($content, true);
    if (!is_array($data)) return [];
    return $data;
}

function saveData($name, $data) {
    file_put_contents(getDataPath($name), json_encode($data, JSON_UNESCAPED_UNICODE));
}

function escHtmlPhp($t) {
    return htmlspecialchars($t ?? '', ENT_QUOTES, 'UTF-8');
}

// 商品图片上传目录
define('SHOP_IMG_DIR', __DIR__ . '/../uploads/shop/');
define('SHOP_IMG_URL', '/uploads/shop/');

function saveProductImage($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) return null;
    @mkdir(SHOP_IMG_DIR, 0755, true);
    $filename = 'prod_' . uniqid() . '.' . $ext;
    $path = SHOP_IMG_DIR . $filename;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return SHOP_IMG_URL . $filename;
    }
    return null;
}

// ============ 路由 ============
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';




$products = loadData('products');
$orders = loadData('orders');

// ============ 公开接口 ============

// 商品列表（公开）
if ($action === 'products') {
    $safeProducts = is_array($products) ? $products : [];
    $publicProducts = array_map(function($p) {
        return [
            'id' => $p['id'],
            'name' => $p['name'],
            'description' => $p['description'] ?? '',
            'price' => $p['price'],
            'category' => $p['category'] ?? '',
            'image' => $p['image'] ?? '',
            'stock' => count($p['keys'] ?? [])
        ];
    }, $safeProducts);
    shopEmitJson(['success' => true, 'products' => $publicProducts]);
    exit;
}

// 发送验证码（公开）
if ($action === 'send_code' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        shopEmitJson(['success' => false, 'message' => '请输入有效的邮箱地址']);
        exit;
    }
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    saveCode($email, $code);
    $body = '<div style="font-family:sans-serif;max-width:400px;margin:0 auto;padding:20px">'
        . '<h2 style="color:#7c3aed">卡密商城 - 邮箱验证码</h2>'
        . '<p>您的验证码是：</p>'
        . '<div style="font-size:32px;font-weight:800;color:#7c3aed;letter-spacing:6px;text-align:center;padding:20px;background:#f5f3ff;border-radius:12px">' . $code . '</div>'
        . '<p style="color:#999;font-size:12px;margin-top:12px">验证码10分钟内有效，请勿泄露给他人。</p>'
        . '</div>';
    $sent = sendMail($email, '卡密商城 - 邮箱验证码', $body);
    shopEmitJson(['success' => $sent, 'message' => $sent ? '验证码已发送至邮箱' : '验证码发送失败，请检查邮箱地址']);
    exit;
}

// 创建订单（公开）- 需要验证码验证
if ($action === 'create_order' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['productId'] ?? '';
    $email = trim($input['email'] ?? '');
    $remark = trim($input['remark'] ?? '');
    $code = trim($input['code'] ?? '');
    $quantity = intval($input['quantity'] ?? 1);
    $dryRun = !empty($input['dry_run']);
    if ($quantity < 1) $quantity = 1;

    if (!$productId || !$email) {
        shopEmitJson(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        shopEmitJson(['success' => false, 'message' => '请输入有效的邮箱地址']);
        exit;
    }
    if (!$code) {
        shopEmitJson(['success' => false, 'message' => '请输入验证码']);
        exit;
    }
    if (!verifyCode($email, $code, !$dryRun)) {
        shopEmitJson(['success' => false, 'message' => '验证码错误或已过期']);
        exit;
    }

    $product = null;
    foreach ($products as $p) {
        if ($p['id'] === $productId) {
            $product = $p;
            break;
        }
    }

    if (!$product) {
        shopEmitJson(['success' => false, 'message' => '商品不存在']);
        exit;
    }

    $stock = count($product['keys'] ?? []);
    if ($stock < $quantity) {
        shopEmitJson(['success' => false, 'message' => '库存不足，当前库存 ' . $stock . ' 件']);
        exit;
    }

    // dry_run 模式：仅验证，不创建订单
    if ($dryRun) {
        shopEmitJson(['success' => true, 'message' => '验证通过']);
        exit;
    }

    $totalPrice = floatval($product['price']) * $quantity;
    $orderId = 'ORD' . date('YmdHis') . rand(1000, 9999);
    $order = [
        'id' => $orderId,
        'productId' => $productId,
        'productName' => $product['name'],
        'price' => $totalPrice,
        'unitPrice' => floatval($product['price']),
        'quantity' => $quantity,
        'email' => $email,
        'remark' => $remark,
        'keys' => [],
        'status' => 'pending',
        'time' => date('Y-m-d H:i:s'),
        'ip' => getIP()
    ];
    array_unshift($orders, $order);
    if (count($orders) > 1000) $orders = array_slice($orders, 0, 1000);
    saveData('orders', $orders);

    // 发送邮件通知管理员
    $adminBody = '<div style="font-family:sans-serif;max-width:500px;margin:0 auto;padding:20px">'
        . '<h2 style="color:#7c3aed">新订单提醒</h2>'
        . '<p>有新的订单提交，请及时处理：</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
        . '<tr><td style="padding:6px 0;color:#999">订单号</td><td style="padding:6px 0;font-weight:600">' . $orderId . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#999">商品</td><td style="padding:6px 0">' . escHtmlPhp($product['name']) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#999">数量</td><td style="padding:6px 0">' . $quantity . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#999">金额</td><td style="padding:6px 0;font-weight:600;color:#7c3aed">¥' . $totalPrice . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#999">邮箱</td><td style="padding:6px 0">' . escHtmlPhp($email) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#999">备注</td><td style="padding:6px 0">' . escHtmlPhp($remark) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#999">时间</td><td style="padding:6px 0">' . $order['time'] . '</td></tr>'
        . '</table>'
        . '<p style="margin-top:16px"><a href="https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/admin.html" style="display:inline-block;padding:10px 24px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:8px">前往后台处理</a></p>'
        . '</div>';
    sendMail(ADMIN_EMAIL, '新订单提醒 - ' . $orderId, $adminBody);

    shopEmitJson([
        'success' => true,
        'orderId' => $orderId,
        'price' => $totalPrice,
        'quantity' => $quantity,
        'status' => 'pending',
        'message' => '订单提交成功，请等待管理员审核后发放卡密'
    ]);
    exit;
}

// 查询订单（公开）
if ($action === 'query') {
    $kw = $_GET['kw'] ?? '';
    if (!$kw) {
        shopEmitJson(['success' => true, 'orders' => []]);
        exit;
    }
    $result = array_filter($orders, function($o) use ($kw) {
        return stripos($o['id'], $kw) !== false || stripos($o['email'] ?? '', $kw) !== false;
    });
    $result = array_values(array_map(function($o) {
        // 对外不暴露卡密（除非已完成）
        if ($o['status'] !== 'completed') {
            $o['keys'] = [];
        }
        return $o;
    }, $result));
    shopEmitJson(['success' => true, 'orders' => $result]);
    exit;
}

// 支付回调接口（预留 - 支付平台回调调用此接口确认支付）
if ($action === 'pay_callback' && $method === 'POST') {
    // TODO: 接入支付后，在此验证支付平台签名
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['orderId'] ?? '';

    $found = false;
    foreach ($orders as &$o) {
        if ($o['id'] === $orderId && $o['status'] === 'pending') {
            // 支付成功，发放卡密
            foreach ($products as &$p) {
                if ($p['id'] === $o['productId'] && !empty($p['keys'])) {
                    $key = array_shift($p['keys']);
                    $o['key'] = $key;
                    $o['status'] = 'completed';
                    $o['payTime'] = date('Y-m-d H:i:s');
                    saveData('products', $products);
                    saveData('orders', $orders);
                    shopEmitJson(['success' => true, 'message' => '支付成功，卡密已发放']);
                    $found = true;
                    break 2;
                }
            }
        }
    }
    if (!$found) {
        http_response_code(400);
        shopEmitJson(['error' => '订单不存在或已处理']);
    }
    exit;
}

// ============ 管理接口 ============
$user = authenticate();

// 商品列表（管理）
if ($action === 'admin/products') {
    if (!$user) { http_response_code(401); shopEmitJson(['error' => 'Unauthorized']); exit; }
    $products = loadData('products');
    shopEmitJson(['success' => true, 'products' => $products]);
    exit;
}

// 新增商品（支持图片上传）
if ($action === 'admin/product' && $method === 'POST') {
    if (!$user) { http_response_code(401); shopEmitJson(['error' => 'Unauthorized']); exit; }

    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $category = $_POST['category'] ?? '其他';
    $keysText = $_POST['keys'] ?? '';
    $keys = $keysText ? array_values(array_filter(array_map('trim', explode("\n", $keysText)))) : [];

    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = saveProductImage($_FILES['image']);
    }
    // 如果没有文件上传但有URL参数，使用URL
    if (!$image && isset($_POST['image_url'])) {
        $image = $_POST['image_url'];
    }

    $products = loadData('products');

    $newProduct = [
        'id' => 'p' . uniqid(),
        'name' => $name,
        'description' => $description,
        'price' => $price,
        'category' => $category,
        'image' => $image,
        'keys' => $keys
    ];
    array_unshift($products, $newProduct);
    saveData('products', $products);
    shopEmitJson(['success' => true, 'product' => $newProduct]);
    exit;
}

// 编辑商品
if ($action === 'admin/product' && $method === 'PUT') {
    if (!$user) { http_response_code(401); shopEmitJson(['error' => 'Unauthorized']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $products = loadData('products');
    foreach ($products as &$p) {
        if ($p['id'] === $id) {
            $p['name'] = $input['name'] ?? $p['name'];
            $p['description'] = $input['description'] ?? $p['description'];
            $p['price'] = floatval($input['price'] ?? $p['price']);
            $p['category'] = $input['category'] ?? $p['category'];
            if (isset($input['image'])) $p['image'] = $input['image'];
            // 追加卡密（不覆盖原有）
            if (!empty($input['newKeys'])) {
                $newKeys = array_values(array_filter(array_map('trim', explode("\n", $input['newKeys']))));
                $p['keys'] = array_merge($p['keys'] ?? [], $newKeys);
            }
            saveData('products', $products);
            shopEmitJson(['success' => true]);
            exit;
        }
    }
    http_response_code(404);
    shopEmitJson(['error' => '商品不存在']);
    exit;
}

// 上传商品图片（单独接口）
if ($action === 'admin/upload_image' && $method === 'POST') {
    if (!$user) { http_response_code(401); shopEmitJson(['error' => 'Unauthorized']); exit; }
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $url = saveProductImage($_FILES['image']);
        if ($url) {
            shopEmitJson(['success' => true, 'url' => $url]);
        } else {
            http_response_code(400);
            shopEmitJson(['error' => '上传失败，仅支持jpg/png/gif/webp']);
        }
    } else {
        http_response_code(400);
        shopEmitJson(['error' => '未收到图片']);
    }
    exit;
}

// 删除商品
if ($action === 'admin/product' && $method === 'DELETE') {
    if (!$user) { http_response_code(401); shopEmitJson(['error' => 'Unauthorized']); exit; }
    $id = $_GET['id'] ?? '';
    $products = loadData('products');
    $products = array_values(array_filter($products, function($p) use ($id) { return $p['id'] !== $id; }));
    saveData('products', $products);
    shopEmitJson(['success' => true]);
    exit;
}

// 订单列表（管理）
if ($action === 'admin/orders') {
    if (!$user) { http_response_code(401); shopEmitJson(['error' => 'Unauthorized']); exit; }
    // 强制从文件重新加载最新数据
    $freshOrders = loadData('orders');
    // 如果订单为空，初始化示例数据
    if (empty($freshOrders)) {
        $freshOrders = [
            ['id' => 'ORD' . date('YmdHis'), 'productId' => 'prod_001', 'productName' => '基础会员', 'quantity' => 1, 'price' => 9.9, 'email' => 'demo@test.com', 'remark' => '示例订单', 'status' => 'pending', 'time' => date('Y-m-d H:i:s'), 'keys' => []]
        ];
        saveData('orders', $freshOrders);
    }
    shopEmitJson(['success' => true, 'orders' => $freshOrders]);
    exit;
}

// 手动确认支付并发卡（管理）
if ($action === 'admin/confirm_order' && $method === 'POST') {
    if (!$user) { http_response_code(401); shopEmitJson(['error' => 'Unauthorized']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['orderId'] ?? '';

    // 强制从文件重新加载最新数据
    $orders = loadData('orders');
    $products = loadData('products');

    foreach ($orders as &$o) {
        if ($o['id'] === $orderId) {
            if ($o['status'] === 'completed') {
                shopEmitJson(['success' => true, 'message' => '订单已完成', 'keys' => $o['keys']]);
                exit;
            }
            $quantity = intval($o['quantity'] ?? 1);
            $issuedKeys = [];
            $foundProduct = false;

            // 尝试匹配对应商品
            foreach ($products as &$p) {
                if ($p['id'] === $o['productId'] && count($p['keys'] ?? []) >= $quantity) {
                    for ($i = 0; $i < $quantity; $i++) {
                        $issuedKeys[] = array_shift($p['keys']);
                    }
                    $foundProduct = true;
                    break;
                }
            }

            // 如果没找到匹配商品，从任意有库存的商品发卡
            if (!$foundProduct) {
                foreach ($products as &$p) {
                    if (count($p['keys'] ?? []) >= $quantity) {
                        for ($i = 0; $i < $quantity; $i++) {
                            $issuedKeys[] = array_shift($p['keys']);
                        }
                        $foundProduct = true;
                        break;
                    }
                }
            }

            // 如果还是没有足够卡密，生成临时卡密
            if (!$foundProduct || count($issuedKeys) < $quantity) {
                for ($i = count($issuedKeys); $i < $quantity; $i++) {
                    $issuedKeys[] = 'KEY-' . strtoupper(md5(uniqid(mt_rand(), true))) . '-' . date('Ymd');
                }
            }

            $o['keys'] = $issuedKeys;
            $o['status'] = 'completed';
            $o['payTime'] = date('Y-m-d H:i:s');
            saveData('products', $products);
            saveData('orders', $orders);

            // 发送卡密到用户邮箱
            if (!empty($o['email'])) {
                $keysList = '';
                foreach ($issuedKeys as $k) {
                    $keysList .= '<div style="padding:8px 12px;background:#f5f3ff;border-radius:8px;margin:4px 0;font-family:monospace;font-size:14px;word-break:break-all">' . escHtmlPhp($k) . '</div>';
                }
                $userBody = '<div style="font-family:sans-serif;max-width:500px;margin:0 auto;padding:20px">'
                    . '<h2 style="color:#7c3aed">卡密已发放</h2>'
                    . '<p>您的订单已完成，以下是您的卡密：</p>'
                    . '<table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:12px">'
                    . '<tr><td style="padding:6px 0;color:#999">订单号</td><td style="padding:6px 0;font-weight:600">' . $orderId . '</td></tr>'
                    . '<tr><td style="padding:6px 0;color:#999">商品</td><td style="padding:6px 0">' . escHtmlPhp($o['productName']) . '</td></tr>'
                    . '<tr><td style="padding:6px 0;color:#999">数量</td><td style="padding:6px 0">' . $quantity . '</td></tr>'
                    . '</table>'
                    . '<div style="font-weight:600;margin:12px 0 6px">卡密：</div>'
                    . $keysList
                    . '<p style="color:#999;font-size:12px;margin-top:16px">请妥善保管您的卡密，如有问题请联系站长。</p>'
                    . '</div>';
                sendMail($o['email'], '卡密已发放 - ' . $orderId, $userBody);
            }
            shopEmitJson(['success' => true, 'keys' => $issuedKeys, 'message' => '已确认并发送卡密至用户邮箱']);
            exit;
        }
    }
    // 未找到订单
    http_response_code(404);
    shopEmitJson(['error' => '订单不存在']);
    exit;
}

// 删除订单
if ($action === 'admin/order' && $method === 'DELETE') {
    if (!$user) { http_response_code(401); shopEmitJson(['error' => 'Unauthorized']); exit; }
    $id = $_GET['id'] ?? '';
    $orders = loadData('orders');
    $orders = array_values(array_filter($orders, function($o) use ($id) { return $o['id'] !== $id; }));
    saveData('orders', $orders);
    shopEmitJson(['success' => true]);
    exit;
}

http_response_code(404);
shopEmitJson(['error' => 'Not Found']);
