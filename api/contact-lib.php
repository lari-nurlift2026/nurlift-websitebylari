<?php
// No HTTP side effects: independently testable without credentials or SMTP.
declare(strict_types=1);
namespace Nurlift\Contact;

final class Rejected extends \RuntimeException
{
    public function __construct(public int $status, public string $reason, public string $language = 'pt')
    {
        parent::__construct($reason);
    }
}

function failure(int $status, string $code, string $language): array
{
    $messages = [
        'pt' => ['VALIDATION_ERROR' => 'Confira os dados informados e tente novamente.',
            'RATE_LIMITED' => 'Aguarde alguns minutos antes de tentar novamente.',
            'SERVER_ERROR' => 'Não foi possível concluir o envio. Tente mais tarde ou escreva para contato@nurlift.com.'],
        'en' => ['VALIDATION_ERROR' => 'Please check your information and try again.',
            'RATE_LIMITED' => 'Please wait a few minutes before trying again.',
            'SERVER_ERROR' => 'We could not complete your submission. Please try later or email contato@nurlift.com.'],
    ];
    return [$status, ['ok' => false, 'code' => $code,
        'message' => $messages[$language === 'en' ? 'en' : 'pt'][$code] ?? $messages[$language === 'en' ? 'en' : 'pt']['VALIDATION_ERROR']]];
}

function length(string $value): int
{
    $n = preg_match_all('/./us', $value);
    return $n === false ? -1 : $n;
}

function language(string $subject, string $fallback): string
{
    // Unicode-aware case matching without requiring mbstring. Each indicator counts once.
    $pt = ['quero', 'gostaria', 'preciso', 'conhecer', 'solução', 'soluções', 'orçamento', 'conversa', 'minha', 'empresa', 'contato', 'informações', 'sobre'];
    $en = ['would', 'like', 'please', 'need', 'learn', 'solution', 'solutions', 'quote', 'conversation', 'company', 'information', 'about', 'request'];
    $score = static function (array $terms) use ($subject): int {
        $count = 0;
        foreach ($terms as $term) {
            if (preg_match('/(?<!\\p{L})' . preg_quote($term, '/') . '(?!\\p{L})/iu', $subject) === 1) $count++;
        }
        return $count;
    };
    $p = $score($pt); $e = $score($en);
    if ($p >= 2 && $p >= $e + 2) return 'pt';
    if ($e >= 2 && $e >= $p + 2) return 'en';
    return $fallback;
}

function payload(string $body): array
{
    if (strlen($body) > 8192) throw new Rejected(413, 'VALIDATION_ERROR');
    try { $object = json_decode($body, false, 16, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new Rejected(422, 'VALIDATION_ERROR'); }
    if (!$object instanceof \stdClass) throw new Rejected(422, 'VALIDATION_ERROR');
    $data = (array) $object;
    $lang = ($data['page_language'] ?? null) === 'en' ? 'en' : 'pt';
    $fields = ['name', 'email', 'phone', 'subject', 'source', 'page_language', 'page', 'idempotency_key', 'website'];
    if (count($data) !== count($fields) || array_diff($fields, array_keys($data))) throw new Rejected(422, 'VALIDATION_ERROR', $lang);
    foreach ($fields as $field) {
        if (!is_string($data[$field]) || preg_match('/[\p{Cc}\p{Cf}]/u', $data[$field]) !== 0) {
            throw new Rejected(422, 'VALIDATION_ERROR', $lang);
        }
    }
    // Reject controls BEFORE trimming. Do not repair invalid line breaks.
    foreach (['name', 'email', 'phone', 'subject'] as $field) {
        $data[$field] = preg_replace('/^\s+|\s+$/u', '', $data[$field]);
    }
    foreach (['name' => 100, 'subject' => 200] as $field => $max) {
        $n = length($data[$field]);
        if ($n < 2 || $n > $max) throw new Rejected(422, 'VALIDATION_ERROR', $lang);
    }
    if (strlen($data['email']) > 254 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new Rejected(422, 'VALIDATION_ERROR', $lang);
    $at = strrpos($data['email'], '@');
    $data['email'] = substr($data['email'], 0, $at + 1) . strtolower(substr($data['email'], $at + 1));
    $digits = preg_replace('/[^0-9]/', '', $data['phone']);
    if (strlen($data['phone']) > 32 || !preg_match('/^[0-9 +().-]+$/D', $data['phone']) || strlen($digits) < 7 || strlen($digits) > 15) throw new Rejected(422, 'VALIDATION_ERROR', $lang);
    $sources = ['Nurlift PT' => ['pt', '/'], 'Nurlift EN' => ['en', '/en/'], 'Dropper PT' => ['pt', '/dropper/'], 'Dropper EN' => ['en', '/en/dropper/']];
    if (($sources[$data['source']] ?? null) !== [$data['page_language'], $data['page']]) throw new Rejected(422, 'VALIDATION_ERROR', $lang);
    if (!preg_match('/^[a-zA-Z0-9_-]{22,80}$/D', $data['idempotency_key'])) throw new Rejected(422, 'VALIDATION_ERROR', $lang);
    if ($data['website'] !== '') throw new Rejected(422, 'VALIDATION_ERROR', $lang);
    // Stable field order is essential for the payload digest.
    return array_replace(array_fill_keys($fields, ''), $data);
}

function requestGate(array $server, string $origin): void
{
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST') throw new Rejected(405, 'METHOD_NOT_ALLOWED');
    $type = strtolower(trim(explode(';', $server['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') throw new Rejected(415, 'UNSUPPORTED_MEDIA_TYPE');
    if (($server['HTTP_ORIGIN'] ?? '') !== $origin) throw new Rejected(403, 'FORBIDDEN');
    if (isset($server['HTTP_SEC_FETCH_SITE']) && $server['HTTP_SEC_FETCH_SITE'] !== 'same-origin') throw new Rejected(403, 'FORBIDDEN');
    if (isset($server['CONTENT_LENGTH']) && (!ctype_digit((string)$server['CONTENT_LENGTH']) || (int)$server['CONTENT_LENGTH'] > 8192)) throw new Rejected(413, 'VALIDATION_ERROR');
}

final class State
{
    public function __construct(private string $root, private string $secret)
    {
        if (!is_dir($root) || !is_writable($root) || strlen($secret) < 32) throw new \RuntimeException('state_unavailable');
        foreach (['rate-limit', 'idempotency', 'logs'] as $dir) {
            if (!is_dir("$root/$dir") && !mkdir("$root/$dir", 0700)) throw new \RuntimeException('state_unavailable');
        }
    }
    private function transaction(callable $action): mixed
    {
        // One stable lock inode. Cleanup never deletes this lock; no unlink/flock race.
        $handle = fopen($this->root . '/.state.lock', 'c');
        if (!$handle) throw new \RuntimeException('lock_unavailable');
        try {
            if (!flock($handle, LOCK_EX)) throw new \RuntimeException('lock_unavailable');
            return $action();
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }
    private function read(string $file): ?array
    {
        if (!is_file($file)) return null;
        $data = json_decode(file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \RuntimeException('state_corrupt');
        return $data;
    }
    private function write(string $file, array $data): void
    {
        $temporary = tempnam(dirname($file), '.write-');
        if ($temporary === false) throw new \RuntimeException('state_write');
        try {
            $encoded = json_encode($data, JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $encoded) !== strlen($encoded) || !rename($temporary, $file)) throw new \RuntimeException('state_write');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }
    public function attempt(string $ip, int $now): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) throw new \RuntimeException('client_unavailable');
        $key = hash_hmac('sha256', inet_pton($ip), $this->secret);
        $this->transaction(function () use ($key, $now) {
            $file = $this->root . '/rate-limit/' . $key . '.json';
            $state = $this->read($file) ?? ['attempts' => []];
            $attempts = array_values(array_filter($state['attempts'], fn ($t) => $t > $now - 600));
            if (count($attempts) >= 5) throw new Rejected(429, 'RATE_LIMITED');
            $attempts[] = $now;
            $this->write($file, ['attempts' => $attempts, 'expires' => $now + 600]);
        });
    }
    public function deliver(array $data, int $now, callable $send): array
    {
        $key = hash_hmac('sha256', $data['idempotency_key'], $this->secret);
        $hashData = $data; unset($hashData['idempotency_key'], $hashData['website']);
        $digest = hash_hmac('sha256', json_encode($hashData, JSON_THROW_ON_ERROR), $this->secret);
        $file = $this->root . '/idempotency/' . $key . '.json';
        $lang = language($data['subject'], $data['page_language']);
        $result = $this->transaction(function () use ($file, $digest, $now, $lang) {
            $old = $this->read($file);
            if ($old && $old['expires'] > $now) {
                if (!hash_equals($old['digest'], $digest)) throw new Rejected(422, 'VALIDATION_ERROR', $lang);
                if ($old['status'] === 'completed') return ['cached' => $old['result']];
                // Pending/uncertain SMTP attempts must never be retried blindly.
                throw new Rejected(503, 'SERVER_ERROR', $lang);
            }
            $id = bin2hex(random_bytes(16));
            $state = ['digest' => $digest, 'expires' => $now + 86400, 'status' => 'pending', 'request_id' => $id];
            $this->write($file, $state);
            return ['state' => $state];
        });
        if (isset($result['cached'])) return $result['cached'];
        $state = $result['state'];
        $lead = $hashData + ['timestamp' => gmdate('c', $now), 'request_id' => $state['request_id']];
        // No lock held over SMTP. Pending state prevents concurrent delivery.
        try { $send($lead); } catch (\Throwable) {
            try { $this->log($state['request_id'], 'delivery_uncertain', $now); } catch (\Throwable) {}
            throw new Rejected(503, 'SERVER_ERROR', $lang);
        }
        $response = ['ok' => true, 'language' => $lang,
            'message' => $lang === 'en' ? 'Thank you for reaching out! We received your message and our team will be in touch shortly.' : 'Obrigada pelo contato! Recebemos sua mensagem e nosso time falará com você em breve.',
            'request_id' => $state['request_id']];
        $state['status'] = 'completed'; $state['result'] = $response;
        $this->transaction(fn () => $this->write($file, $state));
        return $response;
    }
    public function cleanup(int $now): void
    {
        $this->transaction(function () use ($now) {
            foreach (['rate-limit', 'idempotency'] as $directory) {
                foreach (glob($this->root . '/' . $directory . '/*.json') ?: [] as $file) {
                    $state = $this->read($file);
                    if (($state['expires'] ?? PHP_INT_MAX) <= $now) unlink($file);
                }
                foreach (glob($this->root . '/' . $directory . '/.write-*') ?: [] as $file) {
                    if (filemtime($file) < $now - 86400) unlink($file);
                }
            }
            foreach (glob($this->root . '/logs/*.jsonl') ?: [] as $file) {
                if (filemtime($file) < $now - 7 * 86400) unlink($file);
            }
        });
    }
    public function log(string $requestId, string $category, int $now): void
    {
        $this->transaction(function () use ($requestId, $category, $now) {
            $entry = json_encode(['request_id' => $requestId, 'timestamp' => gmdate('c', $now), 'category' => $category], JSON_THROW_ON_ERROR) . "\n";
            if (file_put_contents($this->root . '/logs/' . gmdate('Y-m-d', $now) . '.jsonl', $entry, FILE_APPEND) !== strlen($entry)) throw new \RuntimeException('log_write');
        });
    }
}

function config(array $c, string $documentRoot): array
{
    foreach (['smtp_host','smtp_username','smtp_password','smtp_encryption','mail_from','mail_to','allowed_origin','hmac_secret','state_dir'] as $key) {
        if (!isset($c[$key]) || !is_string($c[$key]) || $c[$key] === '' || str_contains($c[$key], 'SET_') || preg_match('/[\r\n\x00]/', $c[$key])) throw new \RuntimeException('configuration');
    }
    $origin = parse_url($c['allowed_origin']);
    if (!$origin || ($origin['scheme'] ?? '') !== 'https' || empty($origin['host']) || isset($origin['user']) || isset($origin['pass']) || isset($origin['query']) || isset($origin['fragment']) || isset($origin['path'])) throw new \RuntimeException('configuration');
    if (($c['smtp_port'] ?? null) !== 465 || $c['smtp_encryption'] !== 'ssl' || strlen($c['hmac_secret']) < 32) throw new \RuntimeException('configuration');
    foreach (['mail_from','mail_to','smtp_username'] as $key) if (!filter_var($c[$key], FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('configuration');
    $root = realpath($c['state_dir']); $public = realpath($documentRoot);
    if (!$root || !$public || $root === $public || str_starts_with($root, $public . DIRECTORY_SEPARATOR)) throw new \RuntimeException('configuration');
    $c['state_dir'] = $root;
    return $c;
}
