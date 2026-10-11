<?php
declare(strict_types=1);

/*
 * CFO Partners contact endpoint. Version v23.
 *
 * Upload this file next to index.html. SMTP password is configured in this file.
 * The endpoint returns JSON and sends mail through authenticated SMTP.
 *
 * If kontakt@cfo-partners.com.pl or contact@cfo-partners.com.pl are aliases,
 * SMTP_USERNAME must be the real mailbox login, not the alias.
 */

const SMTP_HOST = 'smtp.jdm.pl';
const SMTP_PORT = 587;
const SMTP_SECURE = 'tls'; // tls, ssl, none
const SMTP_USERNAME = 'marcin.leszczylowski@cfo-partners.com.pl';
const SMTP_PASSWORD = 'Leszczu1483!ML!';
const SMTP_VERIFY_TLS = true;

const MAIL_TO = 'marcin.leszczylowski@cfo-partners.com.pl';
const MAIL_FROM = 'marcin.leszczylowski@cfo-partners.com.pl';
const MAIL_FROM_NAME = 'CFO Partners';

const MAX_FIELD_LENGTH = 300;
const MAX_MESSAGE_LENGTH = 5000;
const DEBUG_ERRORS = false;

function respond(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function field(string $name, int $maxLength = MAX_FIELD_LENGTH): string
{
    $value = isset($_POST[$name]) ? (string) $_POST[$name] : '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function header_text(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

function encode_header_value(string $value): string
{
    return '=?UTF-8?B?' . base64_encode(header_text($value)) . '?=';
}

function email_address(string $email, string $name = ''): string
{
    $email = header_text($email);
    if ($name === '') {
        return '<' . $email . '>';
    }

    return encode_header_value($name) . ' <' . $email . '>';
}

function smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $codes, string $context): string
{
    $response = smtp_read($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException($context . ' failed: ' . trim($response));
    }

    return $response;
}

function smtp_command($socket, string $command, array $codes, string $context): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $codes, $context);
}

function send_smtp_mail(string $subject, string $body, string $replyToEmail, string $replyToName): void
{
    if (SMTP_PASSWORD === 'WSTAW_HASLO_DO_SKRZYNKI_MARCIN_TUTAJ') {
        throw new RuntimeException('SMTP password is not configured in contact_v23.php.');
    }

    if (!filter_var(MAIL_TO, FILTER_VALIDATE_EMAIL) || !filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('MAIL_TO or MAIL_FROM is not a valid email address.');
    }

    $scheme = SMTP_SECURE === 'ssl' ? 'ssl://' : '';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => SMTP_VERIFY_TLS,
            'verify_peer_name' => SMTP_VERIFY_TLS,
            'allow_self_signed' => false,
        ],
    ]);

    $socket = @stream_socket_client(
        $scheme . SMTP_HOST . ':' . SMTP_PORT,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
    }

    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, [220], 'SMTP greeting');
        $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        smtp_command($socket, 'EHLO ' . $ehloHost, [250], 'EHLO');

        if (SMTP_SECURE === 'tls') {
            smtp_command($socket, 'STARTTLS', [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed.');
            }
            smtp_command($socket, 'EHLO ' . $ehloHost, [250], 'EHLO after STARTTLS');
        }

        smtp_command($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN');
        smtp_command($socket, base64_encode(SMTP_USERNAME), [334], 'SMTP username');
        smtp_command($socket, base64_encode(SMTP_PASSWORD), [235], 'SMTP password');

        smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM . '>', [250], 'MAIL FROM');
        smtp_command($socket, 'RCPT TO:<' . MAIL_TO . '>', [250, 251], 'RCPT TO');
        smtp_command($socket, 'DATA', [354], 'DATA');

        $headers = [
            'From: ' . email_address(MAIL_FROM, MAIL_FROM_NAME),
            'To: ' . email_address(MAIL_TO),
            'Reply-To: ' . email_address($replyToEmail, $replyToName),
            'Subject: ' . encode_header_value($subject),
            'Date: ' . date(DATE_RFC2822),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
        fwrite($socket, $message . "\r\n.\r\n");
        smtp_expect($socket, [250], 'Message body');
        @fwrite($socket, "QUIT\r\n");
    } finally {
        fclose($socket);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(true, 'Contact endpoint v23 is online. The website form must send a POST request.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed. The website form must send a POST request.', 405);
}

if (field('website') !== '') {
    respond(true, 'OK');
}

$name = field('name');
$company = field('company');
$email = field('email');
$phone = field('phone');
$message = field('message', MAX_MESSAGE_LENGTH);

if ($name === '' || $email === '' || $message === '') {
    respond(false, 'Uzupełnij imię i nazwisko, e-mail oraz temat konsultacji.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Podaj poprawny adres e-mail.', 422);
}

$subject = field('_subject');
if ($subject === '') {
    $subject = 'Zapytanie ze strony CFO Partners';
}

$body = implode("\n", [
    'Nowe zapytanie ze strony CFO Partners',
    '',
    'Imię i nazwisko: ' . $name,
    'Fundusz / firma: ' . ($company !== '' ? $company : '-'),
    'E-mail: ' . $email,
    'Telefon: ' . ($phone !== '' ? $phone : '-'),
    '',
    'Temat konsultacji:',
    $message,
    '',
    'Data: ' . date('Y-m-d H:i:s T'),
    'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-'),
]);

try {
    send_smtp_mail($subject, $body, $email, $name);
    respond(true, 'Wiadomość wysłana.');
} catch (Throwable $error) {
    $message = DEBUG_ERRORS
        ? $error->getMessage()
        : 'Nie udało się wysłać wiadomości. Sprawdź konfigurację poczty w contact_v23.php.';
    respond(false, $message, 500);
}
