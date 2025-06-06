<?php
// webhook.php - Webhook転送処理

$forwardUrl = getConfig('FORWARD_URL');
if (empty($forwardUrl)) {
    logMessage('FORWARD_URLが設定されていません', 'error');
    sendServerErrorResponse('Forward URL is not configured');
}

// リクエストボディを取得
$requestBody = getRequestBody();

if (empty($requestBody)) {
    logMessage('リクエストボディが空です', 'error');
    sendBadRequestResponse('Request body is empty');
}

// Authorizationヘッダーを取得
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

// 追加: 全HTTPヘッダーをログ出力
logMessage('受信HTTPヘッダー: ' . json_encode(getallheaders()), 'debug');

// 署名を検証
if (!verifyTeamsSignature($requestBody, $authHeader)) {
    logMessage('署名検証に失敗しました', 'error');
    sendUnauthorizedResponse('Invalid signature');
}

// JSONとしてパース
$data = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage('JSONパースに失敗しました: ' . json_last_error_msg(), 'error');
    sendBadRequestResponse('Invalid JSON: ' . json_last_error_msg());
}

// 転送先URLにWebhookを送信
$result = sendTeamsWebhook($forwardUrl, $data);

if (!$result['success']) {
    logMessage('Webhook転送に失敗しました', 'error');
    sendServerErrorResponse('Failed to forward webhook: ' . ($result['message'] ?? 'Unknown error'));
}

// 成功レスポンス
sendJsonResponse([
    'type' => 'message',
    'text' => 'Webhook受信・転送に成功しました'
]);
