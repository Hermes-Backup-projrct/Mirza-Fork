<?php
// Webhook diagnostic — delete after use.
header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('Asia/Tehran');
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "=== config ===\n";
require_once 'config.php';
global $domainhosts, $APIKEY;
echo "domainhosts: '$domainhosts'\n";
echo "APIKEY: " . substr($APIKEY, 0, 12) . "...\n";
echo "this request host: " . ($_SERVER['HTTP_HOST'] ?? '?') . "\n";
echo "scheme: " . (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . "\n";
echo "remote_addr: " . ($_SERVER['REMOTE_ADDR'] ?? '?') . "\n\n";

echo "=== db / setting ===\n";
require_once 'function.php';
require_once 'botapi.php';
$row = select("setting", "*");
echo "webhook_secret in db: " . (isset($row['webhook_secret']) ? ("'" . $row['webhook_secret'] . "'") : 'COLUMN MISSING') . "\n\n";

echo "=== getWebhookInfo ===\n";
$info = telegram('getWebhookInfo');
echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== setWebhook attempt ===\n";
$secret = ensureWebhookSecret();
$url = "https://$domainhosts/index.php?secret=" . $secret['secret'];
echo "target url: $url\n";
$set = telegram('setWebhook', ['url' => $url, 'max_connections' => 40]);
echo "result: " . json_encode($set, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== verify ===\n";
$after = telegram('getWebhookInfo');
echo json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
