<?php
// echo.php - Webhookオウム返し処理

$teamsWorkflowUrl = getConfig('TEAMS_WORKFLOW_URL');
if (empty($teamsWorkflowUrl)) {
    logMessage('TEAMS_WORKFLOW_URLが設定されていません', 'error');
    sendServerErrorResponse('Teams Workflow URL is not configured');
}

// リクエストボディを取得
$requestBody = getRequestBody();

if (empty($requestBody)) {
    logMessage('リクエストボディが空です', 'error');
    sendBadRequestResponse('Request body is empty');
}

// Authorizationヘッダーを取得
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

// 署名を検証
if (!verifyTeamsSignature($requestBody, $authHeader, 'TEAMS_OUTGOING_TOKEN_ECHO')) {
    logMessage('署名検証に失敗しました (echo)', 'error');
    sendUnauthorizedResponse('Invalid signature');
}

// JSONとしてパース
$data = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage('JSONパースに失敗しました: ' . json_last_error_msg(), 'error');
    sendBadRequestResponse('Invalid JSON: ' . json_last_error_msg());
}

// Teams Workflow URLにそのままデータを送信
$result = sendTeamsWebhook($teamsWorkflowUrl, $data);

if (!$result['success']) {
    logMessage('オウム返し送信に失敗しました', 'error');
    sendServerErrorResponse('Failed to echo webhook: ' . ($result['message'] ?? 'Unknown error'));
}

// 成功レスポンス
sendJsonResponse([
    'status' => 'success',
    'message' => 'Webhook echoed successfully'
]);
