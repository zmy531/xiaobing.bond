<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? '';
$configPath = '../MCP/MCP_Windows/music_api_config.json';

function sendResult($success, $message = '', $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

function loadConfig() {
    global $configPath;
    if (!file_exists($configPath)) {
        return ['apis' => [], 'fallback_apis' => [], 'timeout' => 10, 'max_retries' => 3];
    }
    return json_decode(file_get_contents($configPath), true) ?: [];
}

function saveConfig($config) {
    global $configPath;
    return file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function searchMusic($query) {
    $config = loadConfig();
    $results = [];
    
    foreach ($config['apis'] as $key => $api) {
        if (!$api['enabled']) continue;
        
        try {
            $apiResults = callMusicApi($api, $query);
            foreach ($apiResults as $song) {
                $song['platform'] = $api['name'];
                $results[] = $song;
            }
        } catch (Exception $e) {
            error_log('API error ' . $key . ': ' . $e->getMessage());
        }
    }
    
    foreach ($config['fallback_apis'] as $apiUrl) {
        if (count($results) >= 10) break;
        try {
            $url = str_replace('%QUERY%', urlencode($query), $apiUrl);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['data'])) {
                    foreach ($data['data'] as $item) {
                        if (count($results) >= 10) break;
                        $results[] = [
                            'id' => $item['songid'] ?? $item['id'] ?? '',
                            'name' => $item['songname'] ?? $item['name'] ?? '',
                            'artist' => $item['singername'] ?? $item['artist'] ?? '',
                            'album' => $item['albumname'] ?? $item['album'] ?? '',
                            'duration' => $item['duration'] ?? 0,
                            'platform' => '备用API'
                        ];
                    }
                }
            }
        } catch (Exception $e) {}
    }
    
    return array_slice($results, 0, 10);
}

function callMusicApi($api, $query) {
    $url = $api['search_url'];
    
    if (strpos($url, 'music.163.com') !== false) {
        $url .= '?s=' . urlencode($query) . '&type=1&limit=10';
        $headers = ['Cookie: appver=1.5.0.75771'];
    } elseif (strpos($url, 'c.y.qq.com') !== false) {
        $url .= '?w=' . urlencode($query) . '&format=json&p=1&n=10';
        $headers = [];
    } elseif (strpos($url, 'kugou.com') !== false) {
        $url .= '?keyword=' . urlencode($query) . '&page=1&pagesize=10&platform=WebFilter';
        $headers = [];
    } else {
        $url = str_replace('%QUERY%', urlencode($query), $url);
        $headers = [];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return [];
    
    return parseMusicResponse($response, $api['name']);
}

function parseMusicResponse($response, $platform) {
    $results = [];
    $data = json_decode($response, true);
    
    if ($platform === '网易云音乐') {
        if (isset($data['result']['songs'])) {
            foreach ($data['result']['songs'] as $song) {
                $results[] = [
                    'id' => $song['id'],
                    'name' => $song['name'],
                    'artist' => implode('/', array_column($song['artists'], 'name')),
                    'album' => $song['album']['name'],
                    'duration' => $song['duration']
                ];
            }
        }
    } elseif ($platform === 'QQ音乐') {
        if (isset($data['data']['song']['list'])) {
            foreach ($data['data']['song']['list'] as $song) {
                $results[] = [
                    'id' => $song['songid'],
                    'name' => $song['songname'],
                    'artist' => $song['singername'],
                    'album' => $song['albumname'],
                    'duration' => $song['interval'] * 1000
                ];
            }
        }
    } elseif ($platform === '酷狗音乐') {
        if (isset($data['data']['lists'])) {
            foreach ($data['data']['lists'] as $song) {
                $results[] = [
                    'id' => $song['Audioid'],
                    'name' => $song['SongName'],
                    'artist' => $song['SingerName'],
                    'album' => $song['AlbumName'],
                    'duration' => $song['Duration']
                ];
            }
        }
    }
    
    return $results;
}

switch ($action) {
    case 'search':
        $query = $_GET['query'] ?? '';
        if (!$query) sendResult(false, '请输入搜索关键词');
        $results = searchMusic($query);
        sendResult(true, '搜索完成', ['results' => $results]);
        break;
        
    case 'get_api_list':
        $config = loadConfig();
        sendResult(true, '', ['apis' => $config['apis']]);
        break;
        
    case 'add_api':
        $body = json_decode(file_get_contents('php://input'), true);
        $url = $body['url'] ?? '';
        if (!$url) sendResult(false, '请输入API地址');
        
        $config = loadConfig();
        $config['fallback_apis'][] = $url;
        if (saveConfig($config)) {
            sendResult(true, 'API添加成功');
        } else {
            sendResult(false, '保存失败');
        }
        break;
        
    case 'control':
        $body = json_decode(file_get_contents('php://input'), true);
        $controlAction = $body['action'] ?? '';
        sendResult(true, '控制命令已发送', ['action' => $controlAction]);
        break;
        
    default:
        sendResult(false, '未知操作');
}
?>
