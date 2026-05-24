<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;
use Throwable;

class MailService
{
    private array $config;
    private array $smtp;

    public function __construct(array $config, private AppLogger $logger)
    {
        $this->config = $config;
        $this->smtp = $this->normalizeSmtpConfig($config);
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }

    public function sendPasswordReset(string $toEmail, string $resetUrl, array $context = []): bool
    {
        $toEmail = strtolower(trim($toEmail));
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Password reset email skipped for invalid recipient.', $this->safeContext($context + [
                'to_domain' => $this->emailDomain($toEmail),
            ]));
            return false;
        }

        $subject = 'Reset your GemData password';
        $plainText = $this->passwordResetText($resetUrl);
        $html = $this->passwordResetHtml($resetUrl);

        try {
            $driver = strtolower((string) ($this->config['driver'] ?? 'log'));
            if ($driver === 'smtp') {
                if ($this->sendWithPhpMailer($toEmail, $subject, $plainText, $html)) {
                    return true;
                }

                $this->sendWithSmtp($toEmail, $subject, $plainText, $html);
                return true;
            }

            if ($driver === 'log') {
                $this->logger->info('Password reset email prepared in log mode.', $this->safeContext($context + [
                    'driver' => 'log',
                    'to_domain' => $this->emailDomain($toEmail),
                    'reset_url_generated' => $resetUrl !== '',
                ]));
                return true;
            }

            throw new RuntimeException('Unsupported mail driver configured.');
        } catch (Throwable $throwable) {
            $this->logger->error('Password reset email delivery failed.', $this->safeContext($context + [
                'driver' => (string) ($this->config['driver'] ?? 'log'),
                'to_domain' => $this->emailDomain($toEmail),
                'error' => $throwable->getMessage(),
            ]));
            return false;
        }
    }

    private function sendWithPhpMailer(string $toEmail, string $subject, string $plainText, string $html): bool
    {
        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return false;
        }

        $class = '\\PHPMailer\\PHPMailer\\PHPMailer';
        $mail = new $class(true);
        $mail->isSMTP();
        $mail->Host = $this->requiredSmtpValue('host');
        $mail->Port = (int) $this->requiredSmtpValue('port');
        $mail->SMTPAuth = $this->smtp['username'] !== '';
        $mail->Username = $this->smtp['username'];
        $mail->Password = $this->smtp['password'];
        if ($this->smtp['encryption'] === 'ssl') {
            $mail->SMTPSecure = defined('\\PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS')
                ? constant('\\PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS')
                : 'ssl';
        } elseif ($this->smtp['encryption'] === 'tls') {
            $mail->SMTPSecure = defined('\\PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS')
                ? constant('\\PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS')
                : 'tls';
        }
        $mail->Timeout = (int) ($this->smtp['timeout'] ?: 20);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($this->fromEmail(), $this->fromName());
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $plainText;
        $mail->send();

        return true;
    }

    private function sendWithSmtp(string $toEmail, string $subject, string $plainText, string $html): void
    {
        $host = $this->requiredSmtpValue('host');
        $port = (int) $this->requiredSmtpValue('port');
        $encryption = $this->smtp['encryption'];
        $timeout = (int) ($this->smtp['timeout'] ?: 20);
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed.');
        }

        try {
            stream_set_timeout($socket, $timeout);
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO ' . $this->smtpClientName(), [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP TLS negotiation failed.');
                }
                $this->command($socket, 'EHLO ' . $this->smtpClientName(), [250]);
            }

            if ($this->smtp['username'] !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($this->smtp['username']), [334]);
                $this->command($socket, base64_encode($this->smtp['password']), [235]);
            }

            $from = $this->fromEmail();
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            fwrite($socket, $this->buildMessage($toEmail, $subject, $plainText, $html) . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function buildMessage(string $toEmail, string $subject, string $plainText, string $html): string
    {
        $boundary = '=_GemData_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->formatAddress($this->fromEmail(), $this->fromName()),
            'To: ' . $this->formatAddress($toEmail),
            'Subject: ' . $this->sanitizeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@gemdata.com.ng>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($plainText),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($html),
            '--' . $boundary . '--',
        ];

        return $this->dotStuff(implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts));
    }

    private function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCodes);
    }

    private function expect($socket, array $expectedCodes): string
    {
        $response = '';
        do {
            $line = fgets($socket, 2048);
            if ($line === false) {
                throw new RuntimeException('SMTP server closed the connection.');
            }
            $response .= $line;
            $code = (int) substr($line, 0, 3);
            $continued = substr($line, 3, 1) === '-';
        } while ($continued);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP server rejected the mail command.');
        }

        return $response;
    }

    private function normalizeSmtpConfig(array $config): array
    {
        $nested = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];

        return [
            'host' => trim((string) ($nested['host'] ?? $config['host'] ?? '')),
            'port' => (int) ($nested['port'] ?? $config['port'] ?? 465),
            'username' => trim((string) ($nested['username'] ?? $config['username'] ?? '')),
            'password' => (string) ($nested['password'] ?? $config['password'] ?? ''),
            'encryption' => strtolower(trim((string) ($nested['encryption'] ?? $config['encryption'] ?? 'ssl'))),
            'timeout' => (int) ($nested['timeout'] ?? $config['timeout'] ?? 20),
        ];
    }

    private function requiredSmtpValue(string $key): string
    {
        $value = (string) ($this->smtp[$key] ?? '');
        if ($value === '') {
            throw new RuntimeException('SMTP configuration is incomplete.');
        }

        return $value;
    }

    private function fromEmail(): string
    {
        $email = strtolower(trim((string) ($this->config['from_email'] ?? $this->smtp['username'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Mail from address is invalid.');
        }

        return $email;
    }

    private function fromName(): string
    {
        return $this->sanitizeHeader((string) ($this->config['from_name'] ?? 'GemData'));
    }

    private function smtpClientName(): string
    {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'gemdata.com.ng');
        return preg_replace('/[^A-Za-z0-9.-]/', '', $host) ?: 'gemdata.com.ng';
    }

    private function passwordResetText(string $resetUrl): string
    {
        return "Hello,\n\nWe received a request to reset your GemData password. Open this secure link to choose a new password:\n\n"
            . $resetUrl
            . "\n\nThis link is time-limited and can be used once. If you did not request this reset, you can ignore this email.\n\nGemData";
    }

    private function passwordResetHtml(string $resetUrl): string
    {
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#0f172a;">'
            . '<div style="max-width:560px;margin:0 auto;padding:32px 20px;">'
            . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;">'
            . '<h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;">Reset your GemData password</h1>'
            . '<p style="margin:0 0 20px;color:#475569;line-height:1.6;">We received a request to reset your password. Use the secure link below to choose a new one.</p>'
            . '<p style="margin:0 0 24px;"><a href="' . $safeUrl . '" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:bold;border-radius:10px;padding:12px 18px;">Reset password</a></p>'
            . '<p style="margin:0 0 12px;color:#475569;line-height:1.6;">If the button does not work, copy and paste this link into your browser:</p>'
            . '<p style="word-break:break-all;margin:0 0 20px;color:#1d4ed8;font-size:13px;">' . $safeUrl . '</p>'
            . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">This link is time-limited and can be used once. If you did not request this reset, you can ignore this email.</p>'
            . '</div></div></body></html>';
    }

    private function formatAddress(string $email, string $name = ''): string
    {
        $email = strtolower(trim($email));
        $name = trim($this->sanitizeHeader($name));
        if ($name === '') {
            return '<' . $email . '>';
        }

        return '"' . addcslashes($name, '\\"') . '" <' . $email . '>';
    }

    private function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private function dotStuff(string $message): string
    {
        return preg_replace('/^\./m', '..', $message) ?? $message;
    }

    private function safeContext(array $context): array
    {
        unset($context['token'], $context['reset_token'], $context['reset_url'], $context['password']);
        return $context;
    }

    private function emailDomain(string $email): string
    {
        $parts = explode('@', $email);
        return strtolower((string) end($parts));
    }
}
