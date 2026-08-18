<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$mcpDir = realpath(__DIR__ . '/../MCP');
$winDir = $mcpDir . '/MCP_Windows';
$aglmDir = $mcpDir . '/MCP_AutoGLM';
$dataDir = $winDir . '/数据';
$presetDir = $winDir . '/预设';
$envFile = $winDir . '/.ENV';
$progPresetFile = $presetDir . '/程序预设.txt';
$cmdPresetFile = $presetDir . '/命令预设.txt';

$switchMap = [
    'cmd' => '允许使用CMD命令工具.INI',
    'ppt' => '使用PPT操控系列工具.INI',
    'ai' => '启用操控第三方 Ai工具.INI',
    'music' => '启用音乐播放器控制_网易云音乐控制.INI',
    'wechat' => '允许使用微信发消息工具.DLL',
    'map' => '允许使用高德地图工具.DLL',
    'api' => '启用调用API工具.INI',
    'qclaw' => '启用小龙虾_QClaw.INI',
    'task' => '启用创建定时任务工具.INI'
];

$toolNames = [
    '使用PPT操控系列工具' => ['上/下/指定页', '播放/退出', '插入页', '查看备注', '全屏', '超链接', '激光笔', '背景设置', '保存', '格式设置', '插入媒体', '图表', '形状/文本框', '动画', '字体/大小/颜色', '位置/缩放', '导出图片'],
    '允许使用CMD命令工具' => ['执行命令', '运行代码(临时)', '运行代码文件'],
    '启用操控第三方 Ai工具' => ['AI对话', '生成内容', '图像生成'],
    '启用音乐播放器控制_网易云音乐控制' => ['播放/暂停', '切歌', '歌词', '喜欢/收藏', '音量', '收藏到歌单', '播放指定歌曲/歌单'],
    '允许使用微信发消息工具' => ['查看微信', '发送微信消息', '图片转文字'],
    '允许使用高德地图工具' => ['位置查询', '路线规划', '地点搜索', '天气/周边服务', '附近搜索', '坐标转换', 'IP定位'],
    '启用调用API工具' => ['翻译', '搜索新闻/天气/音乐', 'AI对话', '查询信息', '联网搜索'],
    '启用小龙虾_QClaw' => ['搜索', '商品筛选', '评论', '图片搜索'],
    '启用创建定时任务工具' => ['创建任务', '查看/删除', '一次性/周期任务']
];

function sendResult($success, $data = null, $message = '') {
    echo json_encode(['success' => $success, 'config' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function readEnvEndpoint() {
    global $envFile;
    if (!file_exists($envFile)) return '';
    $content = @file_get_contents($envFile);
    if (!$content) return '';
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, 'MCP_ENDPOINT') !== false && strpos($line, '=') !== false) {
            $parts = explode('=', $line, 2);
            return trim($parts[1]);
        }
    }
    return '';
}

function readPresets($file) {
    $result = [];
    if (!file_exists($file)) return $result;
    $lines = @file_get_contents($file);
    if (!$lines) return $result;
    foreach (explode("\n", $lines) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos !== false) {
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if ($key !== '') $result[$key] = $val;
        }
    }
    return $result;
}

function writePresets($file, $data) {
    $lines = [];
    foreach ($data as $key => $val) {
        $lines[] = $key . '=' . $val;
    }
    @file_put_contents($file, implode("\n", $lines) . "\n");
}

function getSwitches() {
    global $dataDir, $switchMap;
    $result = [];
    foreach ($switchMap as $key => $filename) {
        $result[$key] = file_exists($dataDir . '/启用的相关工具/' . $filename);
    }
    return $result;
}

function getAvailableTools() {
    global $dataDir, $toolNames;
    $tools = [];
    $enabledDir = $dataDir . '/启用的相关工具';
    if (!is_dir($enabledDir)) return $tools;
    foreach ($toolNames as $marker => $funcs) {
        $found = false;
        foreach (scandir($enabledDir) as $f) {
            if (strpos($f, $marker) !== false) {
                $found = true;
                break;
            }
        }
        if ($found) {
            foreach ($funcs as $func) {
                $tools[] = $func;
            }
        }
    }
    return $tools;
}

function getServiceStatus() {
    global $winDir, $aglmDir;
    $status = ['windows' => false, 'autoglm' => false];

    // 检查 Windows MCP 进程
    $winPidFile = $winDir . '/mcp.pid';
    if (file_exists($winPidFile)) {
        $pid = trim(@file_get_contents($winPidFile));
        if ($pid) {
            $output = [];
            exec('tasklist /FI "PID eq ' . $pid . '" 2>nul', $output);
            foreach ($output as $line) {
                if (stripos($line, 'python') !== false) {
                    $status['windows'] = true;
                    break;
                }
            }
        }
    }

    // 检查 AutoGLM MCP 进程
    $aglmPidFile = $aglmDir . '/mcp.pid';
    if (file_exists($aglmPidFile)) {
        $pid = trim(@file_get_contents($aglmPidFile));
        if ($pid) {
            $output = [];
            exec('tasklist /FI "PID eq ' . $pid . '" 2>nul', $output);
            foreach ($output as $line) {
                if (stripos($line, 'python') !== false) {
                    $status['autoglm'] = true;
                    break;
                }
            }
        }
    }

    return $status;
}

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $config = [
        'endpoint' => readEnvEndpoint(),
        'switches' => getSwitches(),
        'presets' => [
            'programs' => readPresets($progPresetFile),
            'commands' => readPresets($cmdPresetFile)
        ],
        'tools' => getAvailableTools(),
        'status' => getServiceStatus()
    ];
    sendResult(true, $config);
}

if ($action === 'save_endpoint') {
    $input = json_decode(file_get_contents('php://input'), true);
    $endpoint = isset($input['endpoint']) ? $input['endpoint'] : '';
    $content = "MCP_ENDPOINT=" . $endpoint . "\n";
    $ok = @file_put_contents($envFile, $content) !== false;
    sendResult($ok, null, $ok ? '端点已保存' : '保存失败');
}

if ($action === 'toggle_switch') {
    $input = json_decode(file_get_contents('php://input'), true);
    $key = $input['key'] ?? '';
    $enabled = $input['enabled'] ?? false;
    if (!isset($switchMap[$key])) {
        sendResult(false, null, '未知的开关');
    }
    $filePath = $dataDir . '/启用的相关工具/' . $switchMap[$key];
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if ($enabled) {
        $ok = @file_put_contents($filePath, '') !== false;
        sendResult($ok, null, $ok ? '已启用' : '启用失败');
    } else {
        $ok = @unlink($filePath);
        sendResult($ok, null, $ok ? '已禁用' : '禁用失败');
    }
}

if ($action === 'add_preset') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $name = trim($input['name'] ?? '');
    $value = trim($input['value'] ?? '');
    if (!$name || !$value) {
        sendResult(false, null, '名称和值不能为空');
    }
    if (!is_dir($presetDir)) {
        @mkdir($presetDir, 0777, true);
    }
    if ($type === 'program') {
        $data = readPresets($progPresetFile);
        $data[$name] = $value;
        writePresets($progPresetFile, $data);
        sendResult(true, null, '程序预设已添加');
    } elseif ($type === 'command') {
        $data = readPresets($cmdPresetFile);
        $data[$name] = $value;
        writePresets($cmdPresetFile, $data);
        sendResult(true, null, '命令预设已添加');
    } else {
        sendResult(false, null, '未知的预设类型');
    }
}

if ($action === 'remove_preset') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $name = $input['name'] ?? '';
    if ($type === 'program') {
        $data = readPresets($progPresetFile);
        unset($data[$name]);
        writePresets($progPresetFile, $data);
        sendResult(true, null, '程序预设已删除');
    } elseif ($type === 'command') {
        $data = readPresets($cmdPresetFile);
        unset($data[$name]);
        writePresets($cmdPresetFile, $data);
        sendResult(true, null, '命令预设已删除');
    } else {
        sendResult(false, null, '未知的预设类型');
    }
}

sendResult(false, null, '未知的操作');
