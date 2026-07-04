<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

define('JWT_SECRET', 'zmy_admin_secret_key_2026');

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
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer ') !== 0) return false;
    $token = substr($auth, 7);
    return verifyToken($token);
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
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveData($name, $data) {
    file_put_contents(getDataPath($name), json_encode($data, JSON_UNESCAPED_UNICODE));
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

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$products = loadData('products');
$orders = loadData('orders');

// ============ 公开接口 ============

// 商品列表（公开）
if ($action === 'products') {
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
    }, $products);
    echo json_encode(['success' => true, 'products' => $publicProducts]);
    exit;
}

// 创建订单（公开）- 先创建待支付订单，不直接发卡
if ($action === 'create_order' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['productId'] ?? '';
    $contact = $input['contact'] ?? '';
    $remark = $input['remark'] ?? '';

    if (!$productId || !$contact) {
        http_response_code(400);
        echo json_encode(['error' => '参数不完整']);
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
        http_response_code(404);
        echo json_encode(['error' => '商品不存在']);
        exit;
    }

    if (count($product['keys'] ?? []) === 0) {
        http_response_code(400);
        echo json_encode(['error' => '商品已售罄']);
        exit;
    }

    $orderId = 'ORD' . date('YmdHis') . rand(1000, 9999);
    $order = [
        'id' => $orderId,
        'productId' => $productId,
        'productName' => $product['name'],
        'price' => $product['price'],
        'contact' => $contact,
        'remark' => $remark,
        'key' => null,
        'status' => 'pending',  // pending=待支付, paid=已支付待发货, completed=已完成
        'time' => date('Y-m-d H:i:s'),
        'ip' => getIP()
    ];
    array_unshift($orders, $order);
    if (count($orders) > 1000) $orders = array_slice($orders, 0, 1000);
    saveData('orders', $orders);

    // TODO: 支付接口预留 - 接入支付后返回支付链接/二维码
    // 示例: $payUrl = createPayment($orderId, $product['price']);
    echo json_encode([
        'success' => true,
        'orderId' => $orderId,
        'price' => $product['price'],
        'status' => 'pending',
        'message' => '订单已创建，请完成支付后获取卡密'
    ]);
    exit;
}

// 查询订单（公开）
if ($action === 'query') {
    $kw = $_GET['kw'] ?? '';
    if (!$kw) {
        echo json_encode(['success' => true, 'orders' => []]);
        exit;
    }
    $result = array_filter($orders, function($o) use ($kw) {
        return stripos($o['id'], $kw) !== false || stripos($o['contact'], $kw) !== false;
    });
    $result = array_values(array_map(function($o) {
        // 对外不暴露卡密（除非已完成）
        if ($o['status'] !== 'completed') {
            $o['key'] = null;
        }
        return $o;
    }, $result));
    echo json_encode(['success' => true, 'orders' => $result]);
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
                    echo json_encode(['success' => true, 'message' => '支付成功，卡密已发放']);
                    $found = true;
                    break 2;
                }
            }
        }
    }
    if (!$found) {
        http_response_code(400);
        echo json_encode(['error' => '订单不存在或已处理']);
    }
    exit;
}

// ============ 管理接口 ============
$user = authenticate();

// 商品列表（管理）
if ($action === 'admin/products') {
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

// 新增商品（支持图片上传）
if ($action === 'admin/product' && $method === 'POST') {
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

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
    echo json_encode(['success' => true, 'product' => $newProduct]);
    exit;
}

// 编辑商品
if ($action === 'admin/product' && $method === 'PUT') {
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
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
            echo json_encode(['success' => true]);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => '商品不存在']);
    exit;
}

// 上传商品图片（单独接口）
if ($action === 'admin/upload_image' && $method === 'POST') {
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $url = saveProductImage($_FILES['image']);
        if ($url) {
            echo json_encode(['success' => true, 'url' => $url]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => '上传失败，仅支持jpg/png/gif/webp']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => '未收到图片']);
    }
    exit;
}

// 删除商品
if ($action === 'admin/product' && $method === 'DELETE') {
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $id = $_GET['id'] ?? '';
    $products = array_values(array_filter($products, fn($p) => $p['id'] !== $id));
    saveData('products', $products);
    echo json_encode(['success' => true]);
    exit;
}

// 订单列表（管理）
if ($action === 'admin/orders') {
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

// 手动确认支付并发卡（管理）
if ($action === 'admin/confirm_order' && $method === 'POST') {
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['orderId'] ?? '';
    foreach ($orders as &$o) {
        if ($o['id'] === $orderId) {
            if ($o['status'] === 'completed') {
                echo json_encode(['success' => true, 'message' => '订单已完成', 'key' => $o['key']]);
                exit;
            }
            foreach ($products as &$p) {
                if ($p['id'] === $o['productId'] && !empty($p['keys'])) {
                    $key = array_shift($p['keys']);
                    $o['key'] = $key;
                    $o['status'] = 'completed';
                    $o['payTime'] = date('Y-m-d H:i:s');
                    saveData('products', $products);
                    saveData('orders', $orders);
                    echo json_encode(['success' => true, 'key' => $key, 'message' => '已确认支付并发送卡密']);
                    exit;
                }
            }
            http_response_code(400);
            echo json_encode(['error' => '卡密库存不足']);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => '订单不存在']);
    exit;
}

// 删除订单
if ($action === 'admin/order' && $method === 'DELETE') {
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    $id = $_GET['id'] ?? '';
    $orders = array_values(array_filter($orders, fn($o) => $o['id'] !== $id));
    saveData('orders', $orders);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
