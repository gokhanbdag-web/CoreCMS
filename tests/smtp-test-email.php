<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/SmtpTransport.php';
require dirname(__DIR__) . '/app/Core/SmtpTestEmail.php';

use CoreCMS\Core\SmtpTestEmail;

$settings = [
    'smtp_enabled' => '1',
    'smtp_host' => 'smtp.example.test',
    'smtp_port' => '587',
    'smtp_security' => 'starttls',
    'smtp_username' => 'mailer@example.test',
    'smtp_password' => 'secret',
    'smtp_timeout' => '15',
    'mail_from_email' => 'noreply@example.test',
    'site_name' => 'Example',
];
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};
SmtpTestEmail::validate('recipient@example.test', $settings);
$assert(true, 'valid recipient accepted');
foreach (['', 'not-an-email', 'a'.str_repeat('b', 250).'@example.test', "victim@example.test\r\nBcc:evil@example.test"] as $invalid) {
    $failed = false;
    try { SmtpTestEmail::validate($invalid, $settings); } catch (RuntimeException) { $failed = true; }
    $assert($failed, 'invalid recipient rejected');
}
$disabled = $settings;
$disabled['smtp_enabled'] = '0';
$failed = false;
try { SmtpTestEmail::validate('recipient@example.test', $disabled); } catch (RuntimeException) { $failed = true; }
$assert($failed, 'SMTP disabled state rejected');
$badHost = $settings;
$badHost['smtp_host'] = "smtp.example.test\r\nMAIL FROM:<evil@example.test>";
$failed = false;
try { SmtpTestEmail::validate('recipient@example.test', $badHost); } catch (RuntimeException) { $failed = true; }
$assert($failed, 'invalid SMTP settings rejected');
echo 'SMTP test email validation checks passed (' . $checks . ').' . PHP_EOL;
