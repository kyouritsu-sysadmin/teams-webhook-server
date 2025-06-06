<?php
// utils.php - 共通ユーティリティ関数

// 設定値のキャッシュ
$CONFIG_CACHE = [];

/**
 * config.jsonファイルから設定値を読み込む
 * 
 * @return array 設定値の配列
 */
function loadConfig() {
    global $CONFIG_CACHE;
    
    // 既にキャッシュされている場合はそれを返す
    if (!empty($CONFIG_CACHE)) {
        return $CONFIG_CACHE;
    }
    
    $configPath = __DIR__ . '/config.json';
    
    if (!file_exists($configPath)) {
        error_log('設定ファイルが見つかりません: ' . $configPath);
        return [];
    }
    
    $configContent = file_get_contents($configPath);
    $config = json_decode($configContent, true);
    if (!is_array($config)) {
        error_log('設定ファイルのJSONパースに失敗しました: ' . $configPath);
        return [];
    }
    // キャッシュに保存
    $CONFIG_CACHE = $config;
    
    return $config;
}

/**
 * 設定値を取得する
 * 
 * @param string $key 設定キー
 * @param mixed $default デフォルト値
 * @return mixed 設定値
 */
function getConfig($key, $default = null) {
    $config = loadConfig();
    return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * ログを記録する
 * 
 * @param string $message ログメッセージ
 * @param string $level ログレベル (info, error, debug)
 * @return void
 */
function logMessage($message, $level = 'info') {
    $logEnabled = getConfig('LOG_ENABLED', true);
    $logFile = getConfig('LOG_FILE', __DIR__ . '/webhook.log');
    
    if (!$logEnabled) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * TeamsのOutgoing Webhookの署名を検証する
 * 
 * @param string $messageContent リクエストボディ（生のJSON）
 * @param string $authHeader Authorization ヘッダー
 * @param string $tokenKey トークンキー
 * @return bool 署名が有効な場合はtrue
 */
function verifyTeamsSignature($messageContent, $authHeader, $tokenKey = 'TEAMS_OUTGOING_TOKEN') {
    // デバッグモードで検証をスキップ
    if (getConfig('SKIP_VERIFICATION') == 1) {
        logMessage('署名検証をスキップしました（デバッグモード）', 'debug');
        return true;
    }
    
    // トークンが設定されていない場合
    $teamsToken = getConfig($tokenKey);
    if (empty($teamsToken)) {
        logMessage($tokenKey . 'が設定されていません', 'error');
        return false;
    }
    
    // Authorizationヘッダーチェック
    if (empty($authHeader)) {
        logMessage('Authorizationヘッダーがありません', 'error');
        return false;
    }
    
    // Bearer形式のトークンを抽出
    if (preg_match('/^Bearer (.+)$/', $authHeader, $matches)) {
        $providedToken = $matches[1];
        $teamsTokenBin = base64_decode($teamsToken);
        $calculatedToken = base64_encode(hash_hmac('sha256', $messageContent, $teamsTokenBin, true));
        if (hash_equals($calculatedToken, $providedToken)) {
            logMessage('署名検証に成功しました (Bearer)', 'debug');
            return true;
        } else {
            logMessage('署名検証に失敗しました (Bearer)', 'error');
            return false;
        }
    }
    
    // HMAC形式のトークンを抽出（Teams Outgoing Webhook 標準）
    if (preg_match('/^HMAC (.+)$/', $authHeader, $matches)) {
        $providedToken = $matches[1];
        $teamsTokenBin = base64_decode($teamsToken);
        $calculatedToken = base64_encode(hash_hmac('sha256', $messageContent, $teamsTokenBin, true));
        // デバッグ用: 受信ボディ・計算値・受信値をログ出力
        logMessage('HMAC検証デバッグ: body=' . base64_encode($messageContent) . ' calculated=' . $calculatedToken . ' provided=' . $providedToken, 'debug');
        if (hash_equals($calculatedToken, $providedToken)) {
            logMessage('署名検証に成功しました (HMAC)', 'debug');
            return true;
        } else {
            logMessage('署名検証に失敗しました (HMAC)', 'error');
            return false;
        }
    }
    
    logMessage('無効なAuthorizationヘッダー形式です', 'error');
    return false;
}

/**
 * TeamsにWebhookを送信する
 * 
 * @param string $url 送信先URL
 * @param array $data 送信データ
 * @return array レスポンス情報
 */
function sendTeamsWebhook($url, $data) {
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    
    logMessage("Webhookを送信中: " . substr(json_encode($data), 0, 100) . "...", 'debug');
    
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        logMessage("Webhook送信に失敗しました: " . error_get_last()['message'], 'error');
        return [
            'success' => false,
            'message' => 'Failed to send webhook: ' . error_get_last()['message']
        ];
    }
    
    logMessage("Webhook送信に成功しました", 'debug');
    
    return [
        'success' => true,
        'response' => $result
    ];
}

/**
 * リクエストボディを取得する
 * 
 * @return string リクエストボディ（生のJSONデータ）
 */
function getRequestBody() {
    return file_get_contents('php://input');
}

/**
 * JSONレスポンスを送信する
 * 
 * @param array $data レスポンスデータ
 * @param int $statusCode HTTPステータスコード
 * @return void
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * HTTP 400 Bad Request レスポンスを送信する
 * 
 * @param string $message エラーメッセージ
 * @return void
 */
function sendBadRequestResponse($message) {
    sendJsonResponse(['error' => $message], 400);
}

/**
 * HTTP 401 Unauthorized レスポンスを送信する
 * 
 * @param string $message エラーメッセージ
 * @return void
 */
function sendUnauthorizedResponse($message) {
    sendJsonResponse(['error' => $message], 401);
}

/**
 * HTTP 500 Internal Server Error レスポンスを送信する
 * 
 * @param string $message エラーメッセージ
 * @return void
 */
function sendServerErrorResponse($message) {
    sendJsonResponse(['error' => $message], 500);
}
