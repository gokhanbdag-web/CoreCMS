<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use RuntimeException;

final class SmtpTestEmail
{
    public static function validate(string $recipient, array $settings): void
    {
        if (($settings['smtp_enabled'] ?? '0') !== '1') {
            throw new RuntimeException('SMTP must be enabled before sending a test email.');
        }
        $recipient = trim($recipient);
        if ($recipient === '' || strlen($recipient) > 254 || preg_match('/[\r\n]/', $recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid test recipient email address is required.');
        }
        SmtpTransport::validateSettings($settings);
    }

    public function send(string $recipient, array $settings): void
    {
        self::validate($recipient, $settings);
        $siteName = trim((string)($settings['site_name'] ?? 'CoreCMS'));
        if ($siteName === '') $siteName = 'CoreCMS';
        $safeName = htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = '<!doctype html><html><body><h1>' . $safeName . '</h1><p>SMTP test email sent successfully.</p></body></html>';
        (new SmtpTransport())->send(trim($recipient), 'CoreCMS SMTP test', $body, $settings);
    }
}
