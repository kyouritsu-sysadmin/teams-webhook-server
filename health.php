<?php
// health.php - ヘルスチェック処理

// GET以外のメソッドを拒否
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo 'Method Not Allowed';
    exit;
}

// 設定ファイルの読み込み
$config = loadConfig();

// 各種設定の状態を確認
$configStatus = [
    'teams_workflow_url' => !empty(getConfig('TEAMS_WORKFLOW_URL')),
    'teams_outgoing_token' => !empty(getConfig('TEAMS_OUTGOING_TOKEN')),
    'forward_url' => !empty(getConfig('FORWARD_URL')),
    'skip_verification' => (getConfig('SKIP_VERIFICATION') == 1)
];

// PHPバージョンやサーバー情報
$serverInfo = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'config_file_exists' => file_exists(__DIR__ . '/config.txt')
];

// レスポンスを作成
$response = [
    'status' => 'ok',
    'message' => 'Service is running',
    'config_status' => $configStatus,
    'server_info' => $serverInfo
];

// レスポンスを返す
sendJsonResponse($response);
