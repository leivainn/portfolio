<?php
// ============================================================
//  db.php — Supabase + Admin Config
// ============================================================

define('INBOX_PASSWORD',   'LAURENCE09');
define('INBOX_FROM_EMAIL', 'namocivan1@gmail.com');
define('INBOX_FROM_NAME',  'LIN');

// ── Supabase ─────────────────────────────────────────────────
define('SUPABASE_URL', 'https://vkfkldnlnfhwicobwynk.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZrZmtsZG5sbmZod2ljb2J3eW5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Nzg3MzY2MjUsImV4cCI6MjA5NDMxMjYyNX0.4VLrYDoRWw19leA3tsLp0_rb5cDN9_tzKPougN4sv9g');

// ── Gmail SMTP / IMAP ────────────────────────────────────────
define('GMAIL_USER', 'namocivan1@gmail.com');
define('GMAIL_PASS', 'orderznhllfdsktl');

// ============================================================
//  Supabase REST helper
// ============================================================

function supabase(string $method, string $endpoint, array $params = []): array {
    $url = SUPABASE_URL . '/rest/v1' . $endpoint;

    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Prefer: return=representation',
    ];

    $ch = curl_init();

    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 20,
    ]);

    if (in_array($method, ['POST', 'PATCH', 'DELETE'], true) && !empty($params)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    }

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => false,
            'data'    => [],
            'error'   => $error,
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode >= 400) {
        return [
            'success'   => false,
            'data'      => [],
            'http_code' => $httpCode,
            'error'     => $data ?: $response,
        ];
    }

    return [
        'success' => true,
        'data'    => $data ?? [],
    ];
}

// ============================================================
//  Message helpers
// ============================================================

function getMessages(string $filter = 'all'): array {
    $params = [
        'order'  => 'created_at.desc',
        'select' => '*',
    ];

    if ($filter === 'unread') {
        $params['is_read'] = 'eq.false';
    }

    if ($filter === 'read') {
        $params['is_read'] = 'eq.true';
    }

    $result = supabase('GET', '/messages', $params);

    return $result['data'] ?? [];
}

function getMessage(int $id): ?array {
    $result = supabase('GET', '/messages', [
        'id'     => 'eq.' . $id,
        'select' => '*',
        'limit'  => '1',
    ]);

    return $result['data'][0] ?? null;
}

function countMessages(): int {
    $result = supabase('GET', '/messages', [
        'select' => 'id',
    ]);

    return count($result['data'] ?? []);
}

function countUnread(): int {
    $result = supabase('GET', '/messages', [
        'select'  => 'id',
        'is_read' => 'eq.false',
    ]);

    return count($result['data'] ?? []);
}

function insertMessage(string $name, string $email, string $service, string $message, string $ip): bool {
    $result = supabase('POST', '/messages', [
        'name'    => $name,
        'email'   => $email,
        'service' => $service,
        'message' => $message,
        'ip'      => $ip,
        'is_read' => false,
        'source'  => 'contact_form',
    ]);

    return $result['success'] === true && !empty($result['data']);
}

function markRead(int $id): void {
    supabase('PATCH', '/messages?id=eq.' . $id, [
        'is_read' => true,
    ]);
}

function markUnread(int $id): void {
    supabase('PATCH', '/messages?id=eq.' . $id, [
        'is_read' => false,
    ]);
}

function markAllRead(): void {
    supabase('PATCH', '/messages?is_read=eq.false', [
        'is_read' => true,
    ]);
}

function deleteMessage(int $id): void {
    supabase('DELETE', '/messages?id=eq.' . $id, []);
}

function deleteReadMessages(): void {
    supabase('DELETE', '/messages?is_read=eq.true', []);
}

// ============================================================
//  Email — send reply via Gmail SMTP
// ============================================================

function sendReply(string $toEmail, string $toName, string $subject, string $body): bool {
    if (empty(GMAIL_PASS) || GMAIL_PASS === 'YOUR_NEW_GMAIL_APP_PASSWORD_WITHOUT_SPACES') {
        return false;
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $date      = date('r');
    $fromName  = '=?UTF-8?B?' . base64_encode(INBOX_FROM_NAME) . '?=';
    $toNameEnc = '=?UTF-8?B?' . base64_encode($toName ?: $toEmail) . '?=';

    $rawEmail =
        "Date: {$date}\r\n" .
        "To: {$toNameEnc} <{$toEmail}>\r\n" .
        "From: {$fromName} <" . GMAIL_USER . ">\r\n" .
        "Reply-To: " . GMAIL_USER . "\r\n" .
        "Subject: =?UTF-8?B?" . base64_encode($subject ?: 'Re: Your message') . "?=\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: base64\r\n" .
        "\r\n" .
        chunk_split(base64_encode($body));

    $fp = fopen('php://temp', 'r+');

    if (!$fp) {
        return false;
    }

    fwrite($fp, $rawEmail);
    rewind($fp);

    $ch = curl_init('smtps://smtp.gmail.com:465');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAIL_FROM      => '<' . GMAIL_USER . '>',
        CURLOPT_MAIL_RCPT      => ['<' . $toEmail . '>'],
        CURLOPT_READDATA       => $fp,
        CURLOPT_UPLOAD         => true,
        CURLOPT_USE_SSL        => CURLUSESSL_ALL,
        CURLOPT_USERNAME       => GMAIL_USER,
        CURLOPT_PASSWORD       => GMAIL_PASS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 30,
    ]);

    curl_exec($ch);

    $err = curl_errno($ch);

    curl_close($ch);
    fclose($fp);

    return $err === 0;
}

// ============================================================
//  Gmail IMAP — import client replies into Supabase
// ============================================================

function insertGmailReply(string $name, string $email, string $subject, string $message, int $gmailUid): bool {
    $result = supabase('POST', '/messages', [
        'name'      => $name,
        'email'     => $email,
        'service'   => 'Email Reply',
        'subject'   => $subject,
        'message'   => $message,
        'ip'        => 'gmail',
        'is_read'   => false,
        'source'    => 'gmail_reply',
        'gmail_uid' => $gmailUid,
    ]);

    return $result['success'] === true;
}

function decodeMimeText(string $text): string {
    if (!function_exists('imap_mime_header_decode')) {
        return $text;
    }

    $elements = imap_mime_header_decode($text);
    $decoded = '';

    foreach ($elements as $element) {
        $decoded .= $element->text;
    }

    return trim($decoded);
}

function getPlainEmailBody($imap, int $emailNumber): string {
    $body = imap_fetchbody($imap, $emailNumber, 1.1);

    if (!$body) {
        $body = imap_fetchbody($imap, $emailNumber, 1);
    }

    if (!$body) {
        $body = imap_body($imap, $emailNumber);
    }

    $body = quoted_printable_decode($body);
    $body = strip_tags($body);

    return trim($body);
}

function syncGmailReplies(): int {
    if (!function_exists('imap_open')) {
        error_log('PHP IMAP extension is not enabled.');
        return 0;
    }

    $mailbox = '{imap.gmail.com:993/imap/ssl}INBOX';

    $imap = @imap_open($mailbox, GMAIL_USER, GMAIL_PASS);

    if (!$imap) {
        error_log('Gmail IMAP error: ' . imap_last_error());
        return 0;
    }

    $emails = imap_search($imap, 'UNSEEN');
    $savedCount = 0;

    if ($emails) {
        rsort($emails);

        foreach ($emails as $emailNumber) {
            $uid = imap_uid($imap, $emailNumber);
            $header = imap_headerinfo($imap, $emailNumber);

            $fromName = '';
            $fromEmail = '';

            if (!empty($header->from[0])) {
                $from = $header->from[0];

                $fromName = isset($from->personal)
                    ? decodeMimeText($from->personal)
                    : ($from->mailbox ?? 'Unknown');

                $fromEmail = ($from->mailbox ?? '') . '@' . ($from->host ?? '');
            }

            $subject = isset($header->subject)
                ? decodeMimeText($header->subject)
                : 'Email Reply';

            $body = getPlainEmailBody($imap, $emailNumber);

            if ($fromEmail && $body) {
                $saved = insertGmailReply(
                    $fromName ?: $fromEmail,
                    $fromEmail,
                    $subject,
                    $body,
                    $uid
                );

                if ($saved) {
                    $savedCount++;
                    imap_setflag_full($imap, (string)$emailNumber, "\\Seen");
                }
            }
        }
    }

    imap_close($imap);

    return $savedCount;
    function deleteReadMessages() {
    $url = SUPABASE_URL . '/rest/v1/messages?is_read=eq.true';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ],
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log('Supabase deleteReadMessages error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status >= 200 && $status < 300;
}
}