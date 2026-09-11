<?php
declare(strict_types=1);

// All deployment configuration and Composer dependencies live outside public/.
ini_set('display_errors', '0');
umask(0077);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/contact-lib.php';
require_once __DIR__ . '/contact-mail.php';

$state = null;
$language = 'pt';
$requestId = bin2hex(random_bytes(16));
$now = time();
try {
    // Method/type errors remain deterministic even before private config is installed.
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new \Nurlift\Contact\Rejected(405, 'METHOD_NOT_ALLOWED');
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') throw new \Nurlift\Contact\Rejected(415, 'UNSUPPORTED_MEDIA_TYPE');
    $private = '/var/www/vhosts/idigital.net.br/private/nurlift-contact';
    $config = \Nurlift\Contact\config(require $private . '/config.php', dirname(__DIR__));
    \Nurlift\Contact\requestGate($_SERVER, $config['allowed_origin']);
    $state = new \Nurlift\Contact\State($config['state_dir'], $config['hmac_secret']);
    $state->cleanup($now);
    $state->attempt($_SERVER['REMOTE_ADDR'] ?? '', $now);
    // Bounded read, including bodies without Content-Length.
    $body = file_get_contents('php://input', false, null, 0, 8193);
    if ($body === false) throw new \RuntimeException('body_read');
    $data = \Nurlift\Contact\payload($body);
    $language = \Nurlift\Contact\language($data['subject'], $data['page_language']);
    require_once $private . '/vendor/autoload.php';
    $result = $state->deliver($data, $now, static fn (array $lead) => \Nurlift\Contact\sendMail($config, $lead));
    $status = 200;
    $requestId = $result['request_id'];
    try { $state->log($requestId, 'accepted', $now); } catch (\Throwable) { error_log('Nurlift contact: technical log unavailable'); }
} catch (\Nurlift\Contact\Rejected $error) {
    [$status, $result] = \Nurlift\Contact\failure($error->status, $error->reason, $error->language);
} catch (\Throwable) {
    [$status, $result] = \Nurlift\Contact\failure(503, 'SERVER_ERROR', $language);
    // Never log exception messages: mail-library failures may contain visitor data.
    error_log('Nurlift contact: failure request_id=' . $requestId);
}
if ($status !== 200 && $state !== null) {
    try { $state->log($requestId, $result['code'], $now); } catch (\Throwable) { error_log('Nurlift contact: technical log unavailable'); }
}
if ($status === 405) header('Allow: POST');
if ($status === 429) header('Retry-After: 600');
http_response_code($status);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
