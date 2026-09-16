<?php

require_once __DIR__ . '/db/bootstrap.php';

// db/bootstrap.php loads function.php only when it is reached through the
// installer; table.php is also run standalone (crons, browser, CLI), where
// ensureWebhookSecret()/telegram() would still be undefined.
require_once __DIR__ . '/function.php';

global $domainhosts;

$webhookSecret = ensureWebhookSecret();

telegram('setWebhook', [
    'url' => "https://$domainhosts/index.php?secret={$webhookSecret['secret']}",
]);
