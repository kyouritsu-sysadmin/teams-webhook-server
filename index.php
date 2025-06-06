<?php
// index.php - ルーティング処理

require_once __DIR__ . '/utils.php';

file_put_contents(__DIR__ . '/teams_webhook.log', "[".date('Y-m-d H:i:s')."] [debug] index.php reached\n", FILE_APPEND);

// URLパスに基づいて処理を分岐
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// パスの末尾の/を削除
$path = rtrim($path, '/');

// HTTP メソッドを取得
$method = $_SERVER['REQUEST_METHOD'];

// POSTメソッド以外は許可しない（ヘルスチェック以外）
if ($method !== 'POST' && $path !== '/teams/health') {
    sendBadRequestResponse('Only POST method is allowed for this endpoint');
}

// config.txtから値を読み込む
$configPath = __DIR__ . '/config.json';
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
} else {
    $config = [];
}
file_put_contents(__DIR__ . '/teams_webhook.log', "[".date('Y-m-d H:i:s')."] [debug] \$config=" . print_r($config, true) . "\n", FILE_APPEND);

// シークレット値（base64文字列）
$secret_b64 = isset($config['TEAMS_OUTGOING_TOKEN_ECHO']) ? $config['TEAMS_OUTGOING_TOKEN_ECHO'] : '';
$secret = base64_decode($secret_b64);

try {
    switch ($path) {
        case '/teams/webhook':
            require_once __DIR__ . '/webhook.php';
            break;
            
        case '/teams/echo':
            // /teams/echo へのリクエストのみ処理
            if (preg_match('#/teams/echo$#', $_SERVER['REQUEST_URI'])) {
                // 生のPOSTデータ取得
                $rawPostData = file_get_contents('php://input');

                // Authorizationヘッダー取得（大文字・小文字両対応）
                $headers = [];
                if (function_exists('getallheaders')) {
                    foreach (getallheaders() as $k => $v) {
                        $headers[strtolower($k)] = $v;
                    }
                } else {
                    // Fallback for environments without getallheaders
                    foreach ($_SERVER as $name => $value) {
                        if (substr($name, 0, 5) == 'HTTP_') {
                            $key = strtolower(str_replace('_', '-', substr($name, 5)));
                            $headers[$key] = $value;
                        }
                    }
                }
                $authHeader = isset($headers['authorization']) ? $headers['authorization'] : '';

                // HMAC-SHA256署名生成
                $hmac = hash_hmac('sha256', $rawPostData, $secret, true);
                $hmac_b64 = base64_encode($hmac);
                $expectedAuth = 'HMAC ' . $hmac_b64;

                // デバッグ用追加ログ
                file_put_contents(__DIR__ . '/teams_webhook.log', "[".date('Y-m-d H:i:s')."] [debug] rawPostData=" . $rawPostData . "\n", FILE_APPEND);
                file_put_contents(__DIR__ . '/teams_webhook.log', "[".date('Y-m-d H:i:s')."] [debug] secret_b64=" . $secret_b64 . "\n", FILE_APPEND);
                file_put_contents(__DIR__ . '/teams_webhook.log', "[".date('Y-m-d H:i:s')."] [debug] secret(bin)=" . base64_encode($secret) . "\n", FILE_APPEND);

                // ログ出力（デバッグ用）
                file_put_contents(__DIR__ . '/teams_webhook.log', "[".date('Y-m-d H:i:s')."] [debug] authHeader={$authHeader} expectedAuth={$expectedAuth}\n", FILE_APPEND);

                // HMAC認証判定
                if (hash_equals($expectedAuth, $authHeader)) {
                    // JSONデータ取得
                    $json = json_decode($rawPostData, true);
                    $text = isset($json['text']) ? $json['text'] : '';

                    // エコー応答
                    header('Content-Type: application/json');
                    echo json_encode([
                        'type' => 'message',
                        'text' => $text
                    ]);
                } else {
                    // 認証失敗
                    file_put_contents(__DIR__ . '/teams_webhook.log', "[".date('Y-m-d H:i:s')."] [error] HMAC認証失敗\n", FILE_APPEND);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'type' => 'message',
                        'text' => 'HMAC認証に失敗しました'
                    ]);
                }
                exit;
            }
            break;
            
        case '/teams/health':
            require_once __DIR__ . '/health.php';
            break;
            
        default:
            // 404 Not Found
            http_response_code(404);
            echo 'Not Found';
            exit;
    }
} catch (Exception $e) {
    logMessage('エラーが発生しました: ' . $e->getMessage(), 'error');
    sendServerErrorResponse('Internal Server Error: ' . $e->getMessage());
}
