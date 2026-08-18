<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResult($success, $message = '') {
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function findPython() {
    $possiblePythons = ['python', 'python3', 'py'];
    foreach ($possiblePythons as $py) {
        $output = [];
        exec('where ' . escapeshellarg($py) . ' 2>nul', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            return $py;
        }
    }
    return null;
}

function isProcessRunning($pidFile) {
    if (!file_exists($pidFile)) return false;
    $pid = trim(@file_get_contents($pidFile));
    if (!$pid) return false;
    $output = [];
    exec('tasklist /FI "PID eq ' . $pid . '" /NH 2>nul', $output);
    foreach ($output as $line) {
        if (stripos($line, 'python') !== false || stripos($line, (string)$pid) !== false) {
            return true;
        }
    }
    return false;
}

function killProcess($pidFile) {
    if (!file_exists($pidFile)) return false;
    $pid = trim(@file_get_contents($pidFile));
    if (!$pid) return false;
    exec('taskkill /F /PID ' . $pid . ' 2>nul');
    @unlink($pidFile);
    return true;
}

$action = $_GET['action'] ?? '';
$mcpDir = realpath(__DIR__ . '/../MCP');
$winDir = $mcpDir . '/MCP_Windows';
$winPidFile = $winDir . '/mcp.pid';

$stopFlagFile = $mcpDir . '/停止服务指令！.END';

if ($action === 'start_windows') {
    if (isProcessRunning($winPidFile)) {
        sendResult(true, 'MCP 已在运行中');
    }
    $python = findPython();
    if (!$python) {
        sendResult(false, '未找到 Python 解释器');
    }

    $pipeScript = $winDir . '/mcp_pipe.py';
    $mcpScript = 'Windows.py';
    if (!file_exists($pipeScript)) {
        sendResult(false, '未找到 mcp_pipe.py');
    }

    if (file_exists($stopFlagFile)) {
        @unlink($stopFlagFile);
    }

    $psCmd = sprintf(
        'powershell -Command "Start-Process -FilePath %s -ArgumentList %s,%s -WindowStyle Hidden -WorkingDirectory %s -PassThru | Select-Object -ExpandProperty Id | Out-File -Encoding utf8 %s"',
        escapeshellarg($python),
        escapeshellarg($pipeScript),
        escapeshellarg($mcpScript),
        escapeshellarg($winDir),
        escapeshellarg($winPidFile)
    );

    exec($psCmd);
    sleep(3);

    if (isProcessRunning($winPidFile)) {
        sendResult(true, 'MCP 服务已启动，正在连接小智...');
    } else {
        $output = [];
        exec('wmic process where "name=\'python.exe\'" get ProcessId 2>nul', $output);
        $pids = [];
        foreach ($output as $line) {
            $line = trim($line);
            if (is_numeric($line)) {
                $pids[] = $line;
            }
        }
        if (!empty($pids)) {
            @file_put_contents($winPidFile, end($pids));
            sendResult(true, 'MCP 服务已启动（PID: ' . end($pids) . '）');
        } else {
            sendResult(false, '启动失败！请检查：1. Python已安装 2. 安装依赖: pip install websockets mcp pydantic');
        }
    }
}

if ($action === 'stop_windows') {
    @file_put_contents($stopFlagFile, '');
    sleep(1);
    if (isProcessRunning($winPidFile)) {
        killProcess($winPidFile);
    }
    if (file_exists($stopFlagFile)) {
        @unlink($stopFlagFile);
    }
    sendResult(true, 'MCP 服务已停止');
}

if ($action === 'status') {
    $status = isProcessRunning($winPidFile);
    $statusFile = $winDir . '/数据/成功连接MCP.exe';
    $connected = file_exists($statusFile);
    sendResult(true, json_encode([
        'running' => $status,
        'connected' => $connected
    ]));
}

sendResult(false, '未知的操作');
